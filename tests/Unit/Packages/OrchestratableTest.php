<?php

namespace Tests\Unit\Packages;

use App\Models\Server;
use App\Packages\Orchestratable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Tests\TestCase;

class OrchestratableTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_has_correct_timeout_property(): void
    {
        $job = $this->createTestOrchestrator();
        $this->assertEquals(600, $job->timeout);
    }

    public function test_job_has_correct_tries_property(): void
    {
        $job = $this->createTestOrchestrator();
        $this->assertEquals(0, $job->tries);
    }

    public function test_job_has_correct_max_exceptions_property(): void
    {
        $job = $this->createTestOrchestrator();
        $this->assertEquals(3, $job->maxExceptions);
    }

    public function test_middleware_uses_without_overlapping(): void
    {
        $job = $this->createTestOrchestrator();
        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }

    public function test_middleware_returns_array(): void
    {
        $job = $this->createTestOrchestrator();
        $middleware = $job->middleware();

        $this->assertIsArray($middleware);
        $this->assertCount(1, $middleware);
    }

    public function test_middleware_is_instance_of_without_overlapping(): void
    {
        $server = Server::factory()->create();
        $job = $this->createTestOrchestrator($server);
        $middleware = $job->middleware();

        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }

    public function test_server_property_is_accessible(): void
    {
        $server = Server::factory()->create();
        $job = $this->createTestOrchestrator($server);

        $this->assertInstanceOf(Server::class, $job->server);
        $this->assertEquals($server->id, $job->server->id);
    }

    public function test_job_implements_should_queue(): void
    {
        $job = $this->createTestOrchestrator();
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    public function test_job_uses_queueable_trait(): void
    {
        $job = $this->createTestOrchestrator();
        $traits = class_uses_recursive($job);

        $this->assertContains(\Illuminate\Foundation\Queue\Queueable::class, $traits);
    }

    /**
     * Create a test orchestrator instance for testing.
     */
    private function createTestOrchestrator(?Server $server = null): Orchestratable
    {
        if (! $server) {
            $server = Server::factory()->create();
        }

        return new class($server) extends Orchestratable
        {
            public function __construct(Server $server)
            {
                $this->server = $server;
            }

            public function handle(): void
            {
                // Test implementation
            }

            public function failed(\Throwable $exception): void
            {
                // Test implementation
            }
        };
    }
}
