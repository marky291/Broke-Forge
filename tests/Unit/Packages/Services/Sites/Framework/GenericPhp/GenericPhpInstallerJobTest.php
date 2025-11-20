<?php

namespace Tests\Unit\Packages\Services\Sites\Framework\GenericPhp;

use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Services\Sites\Framework\GenericPhp\GenericPhpInstallerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenericPhpInstallerJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test job has correct timeout property.
     */
    public function test_job_has_correct_timeout_property(): void
    {
        // Arrange & Act
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);
        $job = new GenericPhpInstallerJob($server, $site->id);

        // Assert
        $this->assertEquals(600, $job->timeout);
    }

    /**
     * Test job has correct tries property.
     */
    public function test_job_has_correct_tries_property(): void
    {
        // Arrange & Act
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);
        $job = new GenericPhpInstallerJob($server, $site->id);

        // Assert
        $this->assertEquals(0, $job->tries);
    }

    /**
     * Test job has correct max exceptions property.
     */
    public function test_job_has_correct_max_exceptions_property(): void
    {
        // Arrange & Act
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);
        $job = new GenericPhpInstallerJob($server, $site->id);

        // Assert
        $this->assertEquals(1, $job->maxExceptions);
    }

    /**
     * Test middleware configured with without overlapping.
     */
    public function test_middleware_configured_with_without_overlapping(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);
        $job = new GenericPhpInstallerJob($server, $site->id);

        // Act
        $middleware = $job->middleware();

        // Assert
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(\Illuminate\Queue\Middleware\WithoutOverlapping::class, $middleware[0]);
    }

    /**
     * Test constructor accepts server and site id.
     */
    public function test_constructor_accepts_server_and_site_id(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);

        // Act
        $job = new GenericPhpInstallerJob($server, $site->id);

        // Assert
        $this->assertInstanceOf(GenericPhpInstallerJob::class, $job);
        $this->assertEquals($server->id, $job->server->id);
        $this->assertEquals($site->id, $job->siteId);
    }

    /**
     * Test nginx configuration properly escapes double quotes for Generic PHP sites.
     */
    public function test_nginx_configuration_escapes_double_quotes(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'php.test',
            'document_root' => '/home/brokeforge/php.test/public',
            'php_version' => '8.2',
            'ssl_enabled' => false,
        ]);
        $job = new GenericPhpInstallerJob($server, $site->id);

        // Act - use reflection to access protected method
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('configureNginx');
        $method->setAccessible(true);
        $commands = $method->invoke($job, $site);

        // Assert
        $this->assertIsArray($commands);
        $this->assertCount(5, $commands);

        // Verify the write config command (index 1) has escaped quotes
        $writeConfigCommand = $commands[1];
        $this->assertStringContainsString('\\"SAMEORIGIN\\"', $writeConfigCommand);
        $this->assertStringContainsString('\\"nosniff\\"', $writeConfigCommand);
        $this->assertStringContainsString('\\"1; mode=block\\"', $writeConfigCommand);
    }

    /**
     * Test failed method updates status to failed.
     */
    public function test_failed_method_updates_status_to_failed(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'status' => 'installing',
        ]);
        $job = new GenericPhpInstallerJob($server, $site->id);
        $exception = new \Exception('Installation failed');

        // Act
        $job->failed($exception);

        // Assert
        $site->refresh();
        $this->assertEquals('failed', $site->status);
        $this->assertEquals('Installation failed', $site->error_log);
    }
}
