<?php

namespace Tests\Unit\Packages\Services\Nginx;

use App\Models\Server;
use App\Packages\Enums\PhpVersion;
use App\Packages\Orchestratable;
use App\Packages\Services\Nginx\NginxInstallerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Tests\TestCase;

class NginxInstallerJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_extends_orchestratable(): void
    {
        $server = Server::factory()->create();
        $phpVersion = PhpVersion::PHP83;
        $job = new NginxInstallerJob($server, $phpVersion, false);
        $this->assertInstanceOf(Orchestratable::class, $job);
    }

    public function test_job_has_correct_timeout_property(): void
    {
        $server = Server::factory()->create();
        $phpVersion = PhpVersion::PHP83;
        $job = new NginxInstallerJob($server, $phpVersion, false);
        $this->assertEquals(600, $job->timeout);
    }

    public function test_job_has_correct_tries_property(): void
    {
        $server = Server::factory()->create();
        $phpVersion = PhpVersion::PHP83;
        $job = new NginxInstallerJob($server, $phpVersion, false);
        $this->assertEquals(0, $job->tries);
    }

    /**
     * Test job has correct maxExceptions property.
     */
    public function test_job_has_correct_max_exceptions_property(): void
    {
        $server = Server::factory()->create();
        $phpVersion = PhpVersion::PHP83;
        $job = new NginxInstallerJob($server, $phpVersion, false);
        $this->assertEquals(3, $job->maxExceptions);
    }

    public function test_middleware_uses_without_overlapping(): void
    {
        $server = Server::factory()->create();
        $phpVersion = PhpVersion::PHP83;
        $job = new NginxInstallerJob($server, $phpVersion, false);
        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }

    public function test_constructor_accepts_parameters(): void
    {
        $server = Server::factory()->create();
        $phpVersion = PhpVersion::PHP83;
        $job = new NginxInstallerJob($server, $phpVersion, false);
        $this->assertInstanceOf(NginxInstallerJob::class, $job);
        $this->assertEquals($server->id, $job->server->id);
    }

    public function test_server_property_is_accessible(): void
    {
        $server = Server::factory()->create();
        $phpVersion = PhpVersion::PHP83;
        $job = new NginxInstallerJob($server, $phpVersion, false);
        $this->assertInstanceOf(Server::class, $job->server);
        $this->assertEquals($server->id, $job->server->id);
    }

    public function test_php_version_property_is_accessible(): void
    {
        $server = Server::factory()->create();
        $phpVersion = PhpVersion::PHP83;
        $job = new NginxInstallerJob($server, $phpVersion, false);
        $this->assertEquals($phpVersion, $job->phpVersion);
    }

    public function test_is_provisioning_server_property_is_accessible(): void
    {
        $server = Server::factory()->create();
        $phpVersion = PhpVersion::PHP83;

        $job = new NginxInstallerJob($server, $phpVersion, true);
        $this->assertTrue($job->isProvisioningServer);

        $job2 = new NginxInstallerJob($server, $phpVersion, false);
        $this->assertFalse($job2->isProvisioningServer);
    }
}
