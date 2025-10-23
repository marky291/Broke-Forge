<?php

namespace Tests\Unit\Packages\Services\Monitoring;

use App\Models\Server;
use App\Packages\Services\Monitoring\ServerMonitoringRemoverJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerMonitoringRemoverJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_has_correct_timeout_property(): void
    {
        $server = Server::factory()->create();
        $job = new ServerMonitoringRemoverJob($server);
        $this->assertEquals(600, $job->timeout);
    }

    public function test_job_has_correct_tries_property(): void
    {
        $server = Server::factory()->create();
        $job = new ServerMonitoringRemoverJob($server);
        $this->assertEquals(0, $job->tries);
    }

    /**
     * Test job has correct maxExceptions property.
     */
    public function test_job_has_correct_max_exceptions_property(): void
    {
        $server = Server::factory()->create();
        $job = new ServerMonitoringRemoverJob($server);
        $this->assertEquals(3, $job->maxExceptions);
    }

    public function test_middleware_configured_with_without_overlapping(): void
    {
        $server = Server::factory()->create();
        $job = new ServerMonitoringRemoverJob($server);
        $middleware = $job->middleware();
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(\Illuminate\Queue\Middleware\WithoutOverlapping::class, $middleware[0]);
    }

    public function test_constructor_accepts_parameters(): void
    {
        $server = Server::factory()->create();
        $job = new ServerMonitoringRemoverJob($server);
        $this->assertInstanceOf(ServerMonitoringRemoverJob::class, $job);
        $this->assertEquals($server->id, $job->server->id);
    }
}
