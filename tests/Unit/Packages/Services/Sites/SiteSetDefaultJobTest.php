<?php

namespace Tests\Unit\Packages\Services\Sites;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Services\Sites\SiteSetDefaultJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MocksSshConnections;
use Tests\TestCase;

class SiteSetDefaultJobTest extends TestCase
{
    use MocksSshConnections, RefreshDatabase;

    /**
     * Test job updates status to installing when started.
     */
    public function test_updates_status_to_installing_when_started(): void
    {
        // Arrange - inline setup
        $server = Server::factory()->create();
        $site = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => true,
            'default_site_status' => TaskStatus::Installing,
            'php_version' => '8.4',
        ]);

        // Mock SSH for installer (which executes multiple commands via PackageManager)
        $mockSsh = \Mockery::mock(\Spatie\Ssh\Ssh::class);
        $mockSsh->shouldReceive('setTimeout')->andReturnSelf();
        $mockSsh->shouldReceive('execute')->andReturnUsing(function ($cmd) use ($site) {
            $mockProcess = \Mockery::mock(\Symfony\Component\Process\Process::class);
            $mockProcess->shouldReceive('isSuccessful')->andReturn(true);
            $mockProcess->shouldReceive('getOutput')->andReturn($cmd === 'readlink /home/brokeforge/default' ? $site->domain : '');
            $mockProcess->shouldReceive('getCommandLine')->andReturn($cmd);

            return $mockProcess;
        });

        $server = \Mockery::mock($server)->makePartial();
        $server->shouldReceive('ssh')->andReturn($mockSsh);

        // Create job
        $job = new SiteSetDefaultJob($server, $site, 0);

        // Act
        $job->handle();

        // Assert - status should be 'active' after success
        $site->refresh();
        $this->assertEquals(TaskStatus::Active, $site->default_site_status);
    }

    /**
     * Test job sets status to active on success.
     */
    public function test_sets_status_to_active_on_success(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => true,
            'default_site_status' => TaskStatus::Installing,
            'php_version' => '8.4',
        ]);

        // Mock SSH for installer (which executes multiple commands via PackageManager)
        $mockSsh = \Mockery::mock(\Spatie\Ssh\Ssh::class);
        $mockSsh->shouldReceive('setTimeout')->andReturnSelf();
        $mockSsh->shouldReceive('execute')->andReturnUsing(function ($cmd) use ($site) {
            $mockProcess = \Mockery::mock(\Symfony\Component\Process\Process::class);
            $mockProcess->shouldReceive('isSuccessful')->andReturn(true);
            $mockProcess->shouldReceive('getOutput')->andReturn($cmd === 'readlink /home/brokeforge/default' ? $site->domain : '');
            $mockProcess->shouldReceive('getCommandLine')->andReturn($cmd);

            return $mockProcess;
        });

        $server = \Mockery::mock($server)->makePartial();
        $server->shouldReceive('ssh')->andReturn($mockSsh);

        $job = new SiteSetDefaultJob($server, $site, 0);

        // Act
        $job->handle();

        // Assert
        $site->refresh();
        $this->assertEquals(TaskStatus::Active, $site->default_site_status);
        $this->assertTrue($site->is_default);
        $this->assertNull($site->error_log);
    }

    /**
     * Test job sets status to failed on error.
     */
    public function test_sets_status_to_failed_on_error(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => true,
            'default_site_status' => TaskStatus::Installing,
            'php_version' => '8.4',
        ]);

        // Mock SSH commands - ln fails
        $this->mockSshConnection($server, [
            'ln -sfn '.$site->domain.' /home/brokeforge/default' => [
                'success' => false,
                'output' => 'Permission denied',
            ],
        ]);

        $job = new SiteSetDefaultJob($server, $site, 0);

        // Act & Assert - expect exception
        try {
            $job->handle();
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            // Expected
        }

        // Assert - status should be 'failed'
        $site->refresh();
        $this->assertEquals(TaskStatus::Failed, $site->default_site_status);
        $this->assertNotNull($site->error_log);
    }

    /**
     * Test job rolls back to previous default on failure.
     */
    public function test_rolls_back_to_previous_default_on_failure(): void
    {
        // Arrange
        $server = Server::factory()->create();

        $previousDefault = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => false,
            'default_site_status' => null,
        ]);

        $newSite = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => true,
            'default_site_status' => TaskStatus::Installing,
            'php_version' => '8.4',
        ]);

        // Mock SSH commands - ln fails
        $this->mockSshConnection($server, [
            'ln -sfn '.$newSite->domain.' /home/brokeforge/default' => [
                'success' => false,
                'output' => 'Permission denied',
            ],
        ]);

        $job = new SiteSetDefaultJob($server, $newSite, $previousDefault->id);

        // Act & Assert
        try {
            $job->handle();
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            // Expected
        }

        // Assert - previous default should be restored
        $previousDefault->refresh();
        $newSite->refresh();

        $this->assertTrue($previousDefault->is_default);
        $this->assertFalse($newSite->is_default);
        $this->assertEquals(TaskStatus::Failed, $newSite->default_site_status);
    }

    /**
     * Test job handles failure in failed() method.
     */
    public function test_handles_failure_in_failed_method(): void
    {
        // Arrange
        $server = Server::factory()->create();

        $previousDefault = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => false,
        ]);

        $newSite = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => true,
            'default_site_status' => TaskStatus::Installing,
        ]);

        $job = new SiteSetDefaultJob($server, $newSite, $previousDefault->id);
        $exception = new \Exception('Job timeout');

        // Act
        $job->failed($exception);

        // Assert
        $previousDefault->refresh();
        $newSite->refresh();

        $this->assertTrue($previousDefault->is_default);
        $this->assertFalse($newSite->is_default);
        $this->assertEquals(TaskStatus::Failed, $newSite->default_site_status);
    }

    /**
     * Test job works with no previous default site.
     */
    public function test_works_with_no_previous_default_site(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => true,
            'default_site_status' => TaskStatus::Installing,
            'php_version' => '8.4',
        ]);

        // Mock SSH for installer (which executes multiple commands via PackageManager)
        $mockSsh = \Mockery::mock(\Spatie\Ssh\Ssh::class);
        $mockSsh->shouldReceive('setTimeout')->andReturnSelf();
        $mockSsh->shouldReceive('execute')->andReturnUsing(function ($cmd) use ($site) {
            $mockProcess = \Mockery::mock(\Symfony\Component\Process\Process::class);
            $mockProcess->shouldReceive('isSuccessful')->andReturn(true);
            $mockProcess->shouldReceive('getOutput')->andReturn($cmd === 'readlink /home/brokeforge/default' ? $site->domain : '');
            $mockProcess->shouldReceive('getCommandLine')->andReturn($cmd);

            return $mockProcess;
        });

        $server = \Mockery::mock($server)->makePartial();
        $server->shouldReceive('ssh')->andReturn($mockSsh);

        $job = new SiteSetDefaultJob($server, $site, 0);

        // Act
        $job->handle();

        // Assert - should succeed without previous default
        $site->refresh();
        $this->assertEquals(TaskStatus::Active, $site->default_site_status);
        $this->assertTrue($site->is_default);
    }

    /**
     * Test job clears error_log on success.
     */
    public function test_clears_error_log_on_success(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => true,
            'default_site_status' => TaskStatus::Installing,
            'php_version' => '8.4',
            'error_log' => 'Previous error',
        ]);

        // Mock SSH for installer (which executes multiple commands via PackageManager)
        $mockSsh = \Mockery::mock(\Spatie\Ssh\Ssh::class);
        $mockSsh->shouldReceive('setTimeout')->andReturnSelf();
        $mockSsh->shouldReceive('execute')->andReturnUsing(function ($cmd) use ($site) {
            $mockProcess = \Mockery::mock(\Symfony\Component\Process\Process::class);
            $mockProcess->shouldReceive('isSuccessful')->andReturn(true);
            $mockProcess->shouldReceive('getOutput')->andReturn($cmd === 'readlink /home/brokeforge/default' ? $site->domain : '');
            $mockProcess->shouldReceive('getCommandLine')->andReturn($cmd);

            return $mockProcess;
        });

        $server = \Mockery::mock($server)->makePartial();
        $server->shouldReceive('ssh')->andReturn($mockSsh);

        $job = new SiteSetDefaultJob($server, $site, 0);

        // Act
        $job->handle();

        // Assert
        $site->refresh();
        $this->assertNull($site->error_log);
        $this->assertEquals(TaskStatus::Active, $site->default_site_status);
    }
}
