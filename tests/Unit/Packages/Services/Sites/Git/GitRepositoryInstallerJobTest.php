<?php

namespace Tests\Unit\Packages\Services\Sites\Git;

use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Enums\GitStatus;
use App\Packages\Services\Sites\Git\GitRepositoryInstallerJob;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GitRepositoryInstallerJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_has_correct_timeout_property(): void
    {
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);
        $job = new GitRepositoryInstallerJob($server, $site, []);
        $this->assertEquals(600, $job->timeout);
    }

    public function test_job_has_correct_tries_property(): void
    {
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);
        $job = new GitRepositoryInstallerJob($server, $site, []);
        $this->assertEquals(3, $job->tries);
    }

    public function test_middleware_configured_with_without_overlapping(): void
    {
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);
        $job = new GitRepositoryInstallerJob($server, $site, []);
        $middleware = $job->middleware();
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(\Illuminate\Queue\Middleware\WithoutOverlapping::class, $middleware[0]);
    }

    public function test_constructor_accepts_server_site_and_configuration(): void
    {
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);
        $configuration = ['repository' => 'test/repo'];
        $job = new GitRepositoryInstallerJob($server, $site, $configuration);
        $this->assertInstanceOf(GitRepositoryInstallerJob::class, $job);
    }

    public function test_failed_method_updates_git_status_to_failed(): void
    {
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id, 'git_status' => GitStatus::Installing]);
        $job = new GitRepositoryInstallerJob($server, $site, []);
        $exception = new Exception('Operation failed');
        $job->failed($exception);
        $site->refresh();
        $this->assertEquals(GitStatus::Failed, $site->git_status);
    }

    public function test_failed_method_stores_error_log(): void
    {
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id, 'git_status' => GitStatus::Installing, 'error_log' => null]);
        $job = new GitRepositoryInstallerJob($server, $site, []);
        $errorMessage = 'Test error message';
        $exception = new Exception($errorMessage);
        $job->failed($exception);
        $site->refresh();
        $this->assertEquals($errorMessage, $site->error_log);
    }
}
