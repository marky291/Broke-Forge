<?php

namespace Tests\Unit\Packages\Services\Sites\Framework\WordPress;

use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Services\Sites\Framework\WordPress\WordPressInstallerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WordPressInstallerJobTest extends TestCase
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
        $job = new WordPressInstallerJob($server, $site->id);

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
        $job = new WordPressInstallerJob($server, $site->id);

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
        $job = new WordPressInstallerJob($server, $site->id);

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
        $job = new WordPressInstallerJob($server, $site->id);

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
        $job = new WordPressInstallerJob($server, $site->id);

        // Assert
        $this->assertInstanceOf(WordPressInstallerJob::class, $job);
        $this->assertEquals($server->id, $job->server->id);
        $this->assertEquals($site->id, $job->siteId);
    }

    /**
     * Test get framework steps returns correct number of steps.
     */
    public function test_get_framework_steps_returns_correct_number_of_steps(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->wordpress()->create([
            'server_id' => $server->id,
        ]);
        $job = new WordPressInstallerJob($server, $site->id);

        // Act - use reflection to access protected method
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('getFrameworkSteps');
        $method->setAccessible(true);
        $steps = $method->invoke($job, $site);

        // Assert
        $this->assertIsArray($steps);
        $this->assertCount(7, $steps);
    }

    /**
     * Test framework steps have correct structure.
     */
    public function test_framework_steps_have_correct_structure(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->wordpress()->create([
            'server_id' => $server->id,
        ]);
        $job = new WordPressInstallerJob($server, $site->id);

        // Act
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('getFrameworkSteps');
        $method->setAccessible(true);
        $steps = $method->invoke($job, $site);

        // Assert - verify first step structure
        $this->assertArrayHasKey('name', $steps[0]);
        $this->assertArrayHasKey('description', $steps[0]);
        $this->assertEquals('Initializing deployment', $steps[0]['name']);
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
        $job = new WordPressInstallerJob($server, $site->id);
        $exception = new \Exception('Installation failed');

        // Act
        $job->failed($exception);

        // Assert
        $site->refresh();
        $this->assertEquals('failed', $site->status);
        $this->assertEquals('Installation failed', $site->error_log);
    }

    /**
     * Test failed method handles missing records gracefully.
     */
    public function test_failed_method_handles_missing_records_gracefully(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $job = new WordPressInstallerJob($server, 99999);
        $exception = new \Exception('Installation failed');

        // Act & Assert - should not throw exception
        $job->failed($exception);

        // If we reach here, the test passed (no exception thrown)
        $this->assertTrue(true);
    }

    /**
     * Test failed method marks current step as failed in installation_state.
     */
    public function test_failed_method_marks_current_step_as_failed(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'status' => 'installing',
            'installation_state' => collect([
                1 => 'success',
                2 => 'installing', // Currently on step 2
                3 => 'pending',
            ]),
        ]);
        $job = new WordPressInstallerJob($server, $site->id);

        // Simulate being on step 2 by accessing protected property via reflection
        $reflection = new \ReflectionClass($job);
        $property = $reflection->getProperty('currentStep');
        $property->setAccessible(true);
        $property->setValue($job, 2);

        $exception = new \Exception('Step 2 failed');

        // Act
        $job->failed($exception);

        // Assert
        $site->refresh();
        $this->assertEquals('failed', $site->status);
        $this->assertEquals('failed', $site->installation_state->get(2));
        $this->assertEquals('Step 2 failed', $site->error_log);
    }

    /**
     * Test nginx configuration properly escapes double quotes for WordPress sites.
     */
    public function test_nginx_configuration_escapes_double_quotes(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'wordpress.test',
            'document_root' => '/home/brokeforge/wordpress.test/public',
            'php_version' => '8.2',
            'ssl_enabled' => false,
        ]);
        $job = new WordPressInstallerJob($server, $site->id);

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
}
