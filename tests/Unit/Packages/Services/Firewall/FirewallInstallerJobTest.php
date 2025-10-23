<?php

namespace Tests\Unit\Packages\Services\Firewall;

use App\Models\Server;
use App\Packages\Services\Firewall\FirewallInstallerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirewallInstallerJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_has_correct_timeout_property(): void
    {
        $server = Server::factory()->create();
        $job = new FirewallInstallerJob($server);
        $this->assertEquals(600, $job->timeout);
    }

    public function test_job_has_correct_tries_property(): void
    {
        $server = Server::factory()->create();
        $job = new FirewallInstallerJob($server);
        $this->assertEquals(3, $job->tries);
    }

    public function test_middleware_configured_with_without_overlapping(): void
    {
        $server = Server::factory()->create();
        $job = new FirewallInstallerJob($server);
        $middleware = $job->middleware();
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(\Illuminate\Queue\Middleware\WithoutOverlapping::class, $middleware[0]);
    }

    public function test_constructor_accepts_parameters(): void
    {
        $server = Server::factory()->create();
        $job = new FirewallInstallerJob($server);
        $this->assertInstanceOf(FirewallInstallerJob::class, $job);
        $this->assertEquals($server->id, $job->server->id);
    }
}
