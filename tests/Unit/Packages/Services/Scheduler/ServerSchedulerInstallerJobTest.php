<?php

namespace Tests\Unit\Packages\Services\Scheduler;

use App\Models\Server;
use App\Packages\Services\Scheduler\ServerSchedulerInstallerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerSchedulerInstallerJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_has_correct_timeout_property(): void
    {
        $server = Server::factory()->create();
        $job = new ServerSchedulerInstallerJob($server);
        $this->assertEquals(600, $job->timeout);
    }

    public function test_job_has_correct_tries_property(): void
    {
        $server = Server::factory()->create();
        $job = new ServerSchedulerInstallerJob($server);
        $this->assertEquals(0, $job->tries);
    }

    /**
     * Test job has correct maxExceptions property.
     */
    public function test_job_has_correct_max_exceptions_property(): void
    {
        $server = Server::factory()->create();
        $job = new ServerSchedulerInstallerJob($server);
        $this->assertEquals(3, $job->maxExceptions);
    }

    public function test_middleware_configured_with_without_overlapping(): void
    {
        $server = Server::factory()->create();
        $job = new ServerSchedulerInstallerJob($server);
        $middleware = $job->middleware();
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(\Illuminate\Queue\Middleware\WithoutOverlapping::class, $middleware[0]);
    }

    public function test_constructor_accepts_parameters(): void
    {
        $server = Server::factory()->create();
        $job = new ServerSchedulerInstallerJob($server);
        $this->assertInstanceOf(ServerSchedulerInstallerJob::class, $job);
        $this->assertEquals($server->id, $job->server->id);
    }
}
