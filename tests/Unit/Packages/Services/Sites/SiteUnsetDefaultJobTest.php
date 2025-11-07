<?php

namespace Tests\Unit\Packages\Services\Sites;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Services\Sites\SiteUnsetDefaultJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MocksSshConnections;
use Tests\TestCase;

class SiteUnsetDefaultJobTest extends TestCase
{
    use MocksSshConnections, RefreshDatabase;

    /**
     * Test job updates status to removing when started.
     */
    public function test_updates_status_to_removing_when_started(): void
    {
        // Arrange - inline setup
        $server = Server::factory()->create();
        $site = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => true,
            'default_site_status' => TaskStatus::Removing,
            'php_version' => '8.4',
        ]);

        // Mock SSH commands
        $this->mockSshConnection($server, [
            'rm -f /home/brokeforge/default' => [
                'success' => true,
                'output' => '',
            ],
            'sudo service php8.4-fpm reload' => [
                'success' => true,
                'output' => 'Reloading PHP 8.4 FPM',
            ],
            'test ! -e /home/brokeforge/default' => [
                'success' => true,
                'output' => '',
            ],
        ]);

        // Create job
        $job = new SiteUnsetDefaultJob($server, $site);

        // Act
        $job->handle();

        // Assert - status should be null and is_default should be false after success
        $site->refresh();
        $this->assertNull($site->default_site_status);
        $this->assertFalse($site->is_default);
    }

    /**
     * Test job clears status and unsets is_default on success.
     */
    public function test_clears_status_and_unsets_is_default_on_success(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => true,
            'default_site_status' => TaskStatus::Removing,
            'php_version' => '8.4',
        ]);

        // Mock SSH commands
        $this->mockSshConnection($server, [
            'rm -f /home/brokeforge/default' => [
                'success' => true,
                'output' => '',
            ],
            'sudo service php8.4-fpm reload' => [
                'success' => true,
                'output' => 'Reloading PHP 8.4 FPM',
            ],
            'test ! -e /home/brokeforge/default' => [
                'success' => true,
                'output' => '',
            ],
        ]);

        $job = new SiteUnsetDefaultJob($server, $site);

        // Act
        $job->handle();

        // Assert
        $site->refresh();
        $this->assertNull($site->default_site_status);
        $this->assertFalse($site->is_default);
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
            'default_site_status' => TaskStatus::Removing,
            'php_version' => '8.4',
        ]);

        // Mock SSH commands - rm fails
        $this->mockSshConnection($server, [
            'rm -f /home/brokeforge/default' => [
                'success' => false,
                'output' => 'Permission denied',
            ],
        ]);

        $job = new SiteUnsetDefaultJob($server, $site);

        // Act & Assert - expect exception
        try {
            $job->handle();
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            // Expected
        }

        // Assert - status should be 'failed', is_default should be restored
        $site->refresh();
        $this->assertEquals(TaskStatus::Failed, $site->default_site_status);
        $this->assertTrue($site->is_default); // Rolled back
        $this->assertNotNull($site->error_log);
    }

    /**
     * Test job restores is_default flag on failure.
     */
    public function test_restores_is_default_flag_on_failure(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => true,
            'default_site_status' => TaskStatus::Removing,
            'php_version' => '8.4',
        ]);

        // Mock SSH commands - rm fails
        $this->mockSshConnection($server, [
            'rm -f /home/brokeforge/default' => [
                'success' => false,
                'output' => 'Permission denied',
            ],
        ]);

        $job = new SiteUnsetDefaultJob($server, $site);

        // Act & Assert
        try {
            $job->handle();
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            // Expected
        }

        // Assert - is_default should be restored to true
        $site->refresh();
        $this->assertTrue($site->is_default);
        $this->assertEquals(TaskStatus::Failed, $site->default_site_status);
    }

    /**
     * Test job handles failure in failed() method.
     */
    public function test_handles_failure_in_failed_method(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => true,
            'default_site_status' => TaskStatus::Removing,
        ]);

        $job = new SiteUnsetDefaultJob($server, $site);
        $exception = new \Exception('Job timeout');

        // Act
        $job->failed($exception);

        // Assert - is_default should be restored
        $site->refresh();
        $this->assertTrue($site->is_default);
        $this->assertEquals(TaskStatus::Failed, $site->default_site_status);
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
            'default_site_status' => TaskStatus::Removing,
            'php_version' => '8.4',
            'error_log' => 'Previous error',
        ]);

        // Mock SSH commands
        $this->mockSshConnection($server, [
            'rm -f /home/brokeforge/default' => [
                'success' => true,
                'output' => '',
            ],
            'sudo service php8.4-fpm reload' => [
                'success' => true,
                'output' => 'Reloading PHP 8.4 FPM',
            ],
            'test ! -e /home/brokeforge/default' => [
                'success' => true,
                'output' => '',
            ],
        ]);

        $job = new SiteUnsetDefaultJob($server, $site);

        // Act
        $job->handle();

        // Assert
        $site->refresh();
        $this->assertNull($site->error_log);
        $this->assertNull($site->default_site_status);
        $this->assertFalse($site->is_default);
    }

    /**
     * Test job uses correct PHP version in reload command.
     */
    public function test_uses_correct_php_version_in_reload_command(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => true,
            'default_site_status' => TaskStatus::Removing,
            'php_version' => '8.3',
        ]);

        // Mock SSH commands - note php8.3-fpm
        $this->mockSshConnection($server, [
            'rm -f /home/brokeforge/default' => [
                'success' => true,
                'output' => '',
            ],
            'sudo service php8.3-fpm reload' => [
                'success' => true,
                'output' => 'Reloading PHP 8.3 FPM',
            ],
            'test ! -e /home/brokeforge/default' => [
                'success' => true,
                'output' => '',
            ],
        ]);

        $job = new SiteUnsetDefaultJob($server, $site);

        // Act
        $job->handle();

        // Assert - should succeed with correct PHP version
        $site->refresh();
        $this->assertNull($site->default_site_status);
        $this->assertFalse($site->is_default);
    }
}
