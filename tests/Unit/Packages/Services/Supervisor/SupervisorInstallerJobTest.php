<?php

namespace Tests\Unit\Packages\Services\Supervisor;

use App\Models\Server;
use App\Packages\Services\Supervisor\SupervisorInstallerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorInstallerJobTest extends TestCase
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
        $job = new SupervisorInstallerJob($server);

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
        $job = new SupervisorInstallerJob($server);

        // Assert
        $this->assertEquals(3, $job->tries);
    }

    /**
     * Test middleware is configured with WithoutOverlapping.
     */
    public function test_middleware_configured_with_without_overlapping(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act
        $job = new SupervisorInstallerJob($server);
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
        $job = new SupervisorInstallerJob($server);

        // Assert
        $this->assertInstanceOf(SupervisorInstallerJob::class, $job);
        $this->assertEquals($server->id, $job->server->id);
    }
}
