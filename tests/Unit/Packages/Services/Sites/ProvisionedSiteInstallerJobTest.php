<?php

namespace Tests\Unit\Packages\Services\Sites;

use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Services\Sites\ProvisionedSiteInstallerJob;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProvisionedSiteInstallerJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_has_correct_timeout_property(): void
    {
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);
        $job = new ProvisionedSiteInstallerJob($server, $site->id);
        $this->assertEquals(600, $job->timeout);
    }

    public function test_job_has_correct_tries_property(): void
    {
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);
        $job = new ProvisionedSiteInstallerJob($server, $site->id);
        $this->assertEquals(3, $job->tries);
    }

    public function test_middleware_configured_with_without_overlapping(): void
    {
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);
        $job = new ProvisionedSiteInstallerJob($server, $site->id);
        $middleware = $job->middleware();
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(\Illuminate\Queue\Middleware\WithoutOverlapping::class, $middleware[0]);
    }

    public function test_constructor_accepts_server_and_site_id(): void
    {
        $server = Server::factory()->create();
        $siteId = 123;
        $job = new ProvisionedSiteInstallerJob($server, $siteId);
        $this->assertInstanceOf(ProvisionedSiteInstallerJob::class, $job);
        $this->assertEquals($server->id, $job->server->id);
        $this->assertEquals($siteId, $job->siteId);
    }

    public function test_failed_method_updates_status_to_failed(): void
    {
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);
        $job = new ProvisionedSiteInstallerJob($server, $site->id);
        $exception = new Exception('Operation failed');
        $job->failed($exception);
        $site->refresh();
        $this->assertEquals('failed', $site->status);
    }

    public function test_failed_method_stores_error_log(): void
    {
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id, 'error_log' => null]);
        $job = new ProvisionedSiteInstallerJob($server, $site->id);
        $errorMessage = 'Test error message';
        $exception = new Exception($errorMessage);
        $job->failed($exception);
        $site->refresh();
        $this->assertEquals($errorMessage, $site->error_log);
    }

    public function test_failed_method_handles_missing_records_gracefully(): void
    {
        $server = Server::factory()->create();
        $nonExistentId = 99999;
        $job = new ProvisionedSiteInstallerJob($server, $nonExistentId);
        $exception = new Exception('Test error');
        $job->failed($exception);
        $this->assertDatabaseMissing('server_sites', ['id' => $nonExistentId]);
    }
}
