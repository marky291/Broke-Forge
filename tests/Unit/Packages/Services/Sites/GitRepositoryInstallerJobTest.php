<?php

namespace Tests\Unit\Packages\Services\Sites;

use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Enums\GitStatus;
use App\Packages\Services\Sites\Git\GitRepositoryInstaller;
use App\Packages\Services\Sites\Git\GitRepositoryInstallerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class GitRepositoryInstallerJobTest extends TestCase
{
    use RefreshDatabase;

    private Server $server;
    private ServerSite $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = Server::factory()->create();
        $this->site = ServerSite::factory()->create([
            'server_id' => $this->server->id,
            'git_status' => GitStatus::NotInstalled,
        ]);
    }

    public function test_job_implements_should_queue(): void
    {
        $job = new GitRepositoryInstallerJob($this->server, $this->site, []);

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    public function test_job_uses_queueable_trait(): void
    {
        $job = new GitRepositoryInstallerJob($this->server, $this->site, []);

        $this->assertContains(\Illuminate\Foundation\Queue\Queueable::class, class_uses($job));
    }

    public function test_job_configuration(): void
    {
        $job = new GitRepositoryInstallerJob($this->server, $this->site, []);

        $this->assertEquals(600, $job->timeout);
        $this->assertEquals(3, $job->tries);
        $this->assertEquals([30, 60, 120], $job->backoff());
    }

    public function test_constructor_sets_properties(): void
    {
        $config = ['repository' => 'user/repo', 'branch' => 'main'];
        $job = new GitRepositoryInstallerJob($this->server, $this->site, $config);

        $reflection = new \ReflectionClass($job);

        $serverProperty = $reflection->getProperty('server');
        $serverProperty->setAccessible(true);
        $this->assertSame($this->server, $serverProperty->getValue($job));

        $siteProperty = $reflection->getProperty('site');
        $siteProperty->setAccessible(true);
        $this->assertSame($this->site, $siteProperty->getValue($job));

        $configProperty = $reflection->getProperty('configuration');
        $configProperty->setAccessible(true);
        $this->assertEquals($config, $configProperty->getValue($job));
    }

    public function test_handle_skips_if_cannot_install_git(): void
    {
        $this->site->update(['git_status' => GitStatus::Installed]);

        $job = new GitRepositoryInstallerJob($this->server, $this->site, []);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Git installation skipped') &&
                       $context['site_id'] === $this->site->id &&
                       $context['current_status'] === GitStatus::Installed->value;
            });

        $job->handle();

        // Status should remain unchanged
        $this->site->refresh();
        $this->assertEquals(GitStatus::Installed, $this->site->git_status);
    }

    public function test_handle_installs_git_successfully(): void
    {
        $this->markTestSkipped('This test requires refactoring to avoid Mockery overload');
        return;
        $config = [
            'repository' => 'user/repo',
            'branch' => 'main',
        ];

        $job = new GitRepositoryInstallerJob($this->server, $this->site, $config);

        $mockInstaller = Mockery::mock('overload:' . GitRepositoryInstaller::class);
        $mockInstaller->shouldReceive('execute')
            ->once()
            ->with($this->site, $config);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) use ($config) {
                return str_contains($message, 'Git repository installed successfully') &&
                       $context['server_id'] === $this->server->id &&
                       $context['site_id'] === $this->site->id &&
                       $context['repository'] === $config['repository'] &&
                       $context['branch'] === $config['branch'];
            });

        $job->handle();

        $this->site->refresh();
        $this->assertEquals(GitStatus::Installed, $this->site->git_status);
        $this->assertNotNull($this->site->git_installed_at);
        $this->assertArrayHasKey('git_repository', $this->site->configuration);
        $this->assertEquals($config, $this->site->configuration['git_repository']);
    }

    public function test_handle_updates_status_to_installing(): void
    {
        $this->markTestSkipped('This test requires refactoring to avoid Mockery overload');
        return;
        $config = ['repository' => 'user/repo'];
        $job = new GitRepositoryInstallerJob($this->server, $this->site, $config);

        $mockInstaller = Mockery::mock('overload:' . GitRepositoryInstaller::class);
        $mockInstaller->shouldReceive('execute')->once();

        Log::shouldReceive('info')->once();

        $job->handle();

        // We can't easily capture the intermediate status change to Installing
        // because it happens during the handle method execution
        // The final status should be Installed
        $this->site->refresh();
        $this->assertEquals(GitStatus::Installed, $this->site->git_status);
    }

    public function test_handle_marks_as_failed_on_exception(): void
    {
        $this->markTestSkipped('This test requires refactoring to avoid Mockery overload');
        return;
        $config = ['repository' => 'user/repo'];
        $exception = new \Exception('Installation failed');

        $job = new GitRepositoryInstallerJob($this->server, $this->site, $config);

        $mockInstaller = Mockery::mock('overload:' . GitRepositoryInstaller::class);
        $mockInstaller->shouldReceive('execute')
            ->once()
            ->andThrow($exception);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) use ($config) {
                return str_contains($message, 'Git repository installation failed') &&
                       $context['repository'] === $config['repository'];
            });

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Installation failed');

        $job->handle();

        $this->site->refresh();
        $this->assertEquals(GitStatus::Failed, $this->site->git_status);
        $this->assertArrayHasKey('git_repository', $this->site->configuration);
    }

    public function test_failed_method_logs_permanent_failure(): void
    {
        $config = ['repository' => 'user/repo'];
        $exception = new \Exception('Permanent failure');

        $job = new GitRepositoryInstallerJob($this->server, $this->site, $config);

        // Set attempts
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('attempts');
        if ($method) {
            $job->attempts = 3;
        }

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) use ($config, $exception) {
                return str_contains($message, 'Git repository installation permanently failed') &&
                       $context['repository'] === $config['repository'] &&
                       $context['error'] === $exception->getMessage();
            });

        $job->failed($exception);

        $this->site->refresh();
        $this->assertEquals(GitStatus::Failed, $this->site->git_status);
    }

    public function test_should_retry_returns_false_for_validation_errors(): void
    {
        $job = new GitRepositoryInstallerJob($this->server, $this->site, []);

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('shouldRetry');
        $method->setAccessible(true);

        $exception = new \InvalidArgumentException('Invalid repository');
        $this->assertFalse($method->invoke($job, $exception));
    }

    public function test_should_retry_returns_false_for_deleted_server(): void
    {
        $this->server->delete();

        $job = new GitRepositoryInstallerJob($this->server, $this->site, []);

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('shouldRetry');
        $method->setAccessible(true);

        $exception = new \Exception('Some error');
        $this->assertFalse($method->invoke($job, $exception));
    }

    public function test_should_retry_returns_true_when_attempts_less_than_tries(): void
    {
        $job = new GitRepositoryInstallerJob($this->server, $this->site, []);

        // Mock attempts method to return 1
        $job->attempts = 1;

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('shouldRetry');
        $method->setAccessible(true);

        $exception = new \Exception('Temporary error');
        $this->assertTrue($method->invoke($job, $exception));
    }

    public function test_update_git_status_updates_site(): void
    {
        $job = new GitRepositoryInstallerJob($this->server, $this->site, []);

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('updateGitStatus');
        $method->setAccessible(true);

        $method->invoke($job, GitStatus::Installing);

        $this->site->refresh();
        $this->assertEquals(GitStatus::Installing, $this->site->git_status);
    }

    public function test_handle_merges_configuration_correctly(): void
    {
        $this->markTestSkipped('This test requires refactoring to avoid Mockery overload');
        return;
        $this->site->update([
            'configuration' => ['existing' => 'value'],
        ]);

        $config = ['repository' => 'user/repo'];
        $job = new GitRepositoryInstallerJob($this->server, $this->site, $config);

        $mockInstaller = Mockery::mock('overload:' . GitRepositoryInstaller::class);
        $mockInstaller->shouldReceive('execute')->once();

        Log::shouldReceive('info')->once();

        $job->handle();

        $this->site->refresh();
        $this->assertEquals('value', $this->site->configuration['existing']);
        $this->assertEquals($config, $this->site->configuration['git_repository']);
    }
}