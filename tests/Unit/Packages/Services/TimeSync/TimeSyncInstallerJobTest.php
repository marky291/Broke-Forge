<?php

namespace Tests\Unit\Packages\Services\TimeSync;

use App\Models\Server;
use App\Packages\Services\TimeSync\TimeSyncInstallerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeSyncInstallerJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test job has correct timeout property.
     */
    public function test_job_has_correct_timeout_property(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act
        $job = new TimeSyncInstallerJob($server);

        // Assert
        $this->assertEquals(600, $job->timeout);
    }

    /**
     * Test job has correct tries property.
     */
    public function test_job_has_correct_tries_property(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act
        $job = new TimeSyncInstallerJob($server);

        // Assert
        $this->assertEquals(0, $job->tries);
    }

    /**
     * Test job has correct maxExceptions property.
     */
    public function test_job_has_correct_max_exceptions_property(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act
        $job = new TimeSyncInstallerJob($server);

        // Assert
        $this->assertEquals(3, $job->maxExceptions);
    }

    /**
     * Test middleware is configured with WithoutOverlapping.
     */
    public function test_middleware_configured_with_without_overlapping(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act
        $job = new TimeSyncInstallerJob($server);
        $middleware = $job->middleware();

        // Assert
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(\Illuminate\Queue\Middleware\WithoutOverlapping::class, $middleware[0]);
    }

    /**
     * Test job constructor accepts server.
     */
    public function test_constructor_accepts_server(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act
        $job = new TimeSyncInstallerJob($server);

        // Assert
        $this->assertInstanceOf(TimeSyncInstallerJob::class, $job);
        $this->assertEquals($server->id, $job->server->id);
    }

    /**
     * Test job uses shared lock key to prevent concurrent package operations.
     */
    public function test_job_uses_shared_lock_key(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $job = new TimeSyncInstallerJob($server);

        // Act
        $middleware = $job->middleware();

        // Assert
        $this->assertCount(1, $middleware);
        $overlappingMiddleware = $middleware[0];

        // Use reflection to check the lock key includes server ID
        $reflection = new \ReflectionClass($overlappingMiddleware);
        $property = $reflection->getProperty('key');
        $property->setAccessible(true);
        $key = $property->getValue($overlappingMiddleware);

        // Should use the shared package lock key format
        $this->assertEquals("package:action:{$server->id}", $key);
    }
}
