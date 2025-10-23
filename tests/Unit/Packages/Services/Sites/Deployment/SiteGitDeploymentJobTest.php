<?php

namespace Tests\Unit\Packages\Services\Sites\Deployment;

use App\Enums\DeploymentStatus;
use App\Models\Server;
use App\Models\ServerDeployment;
use App\Packages\Services\Sites\Deployment\SiteGitDeploymentJob;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteGitDeploymentJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_has_correct_timeout_property(): void
    {
        $server = Server::factory()->create();
        $deployment = ServerDeployment::factory()->create(['server_id' => $server->id]);
        $job = new SiteGitDeploymentJob($server, $deployment->id);
        $this->assertEquals(600, $job->timeout);
    }

    public function test_job_has_correct_tries_property(): void
    {
        $server = Server::factory()->create();
        $deployment = ServerDeployment::factory()->create(['server_id' => $server->id]);
        $job = new SiteGitDeploymentJob($server, $deployment->id);
        $this->assertEquals(3, $job->tries);
    }

    public function test_middleware_configured_with_without_overlapping(): void
    {
        $server = Server::factory()->create();
        $deployment = ServerDeployment::factory()->create(['server_id' => $server->id]);
        $job = new SiteGitDeploymentJob($server, $deployment->id);
        $middleware = $job->middleware();
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(\Illuminate\Queue\Middleware\WithoutOverlapping::class, $middleware[0]);
    }

    public function test_constructor_accepts_server_and_deployment_id(): void
    {
        $server = Server::factory()->create();
        $deploymentId = 123;
        $job = new SiteGitDeploymentJob($server, $deploymentId);
        $this->assertInstanceOf(SiteGitDeploymentJob::class, $job);
        $this->assertEquals($server->id, $job->server->id);
        $this->assertEquals($deploymentId, $job->deploymentId);
    }

    public function test_failed_method_updates_status_to_failed(): void
    {
        $server = Server::factory()->create();
        $deployment = ServerDeployment::factory()->create(['server_id' => $server->id]);
        $job = new SiteGitDeploymentJob($server, $deployment->id);
        $exception = new Exception('Operation failed');
        $job->failed($exception);
        $deployment->refresh();
        $this->assertEquals(DeploymentStatus::Failed->value, $deployment->status);
    }

    public function test_failed_method_stores_error_output(): void
    {
        $server = Server::factory()->create();
        $deployment = ServerDeployment::factory()->create(['server_id' => $server->id, 'error_output' => null]);
        $job = new SiteGitDeploymentJob($server, $deployment->id);
        $errorMessage = 'Test error message';
        $exception = new Exception($errorMessage);
        $job->failed($exception);
        $deployment->refresh();
        $this->assertEquals($errorMessage, $deployment->error_output);
    }

    public function test_failed_method_handles_missing_records_gracefully(): void
    {
        $server = Server::factory()->create();
        $nonExistentId = 99999;
        $job = new SiteGitDeploymentJob($server, $nonExistentId);
        $exception = new Exception('Test error');
        $job->failed($exception);
        $this->assertDatabaseMissing('server_deployments', ['id' => $nonExistentId]);
    }
}
