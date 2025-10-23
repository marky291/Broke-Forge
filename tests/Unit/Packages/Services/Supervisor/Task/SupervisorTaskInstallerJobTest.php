<?php

namespace Tests\Unit\Packages\Services\Supervisor\Task;

use App\Enums\SupervisorTaskStatus;
use App\Models\Server;
use App\Models\ServerSupervisorTask;
use App\Packages\Services\Supervisor\Task\SupervisorTaskInstallerJob;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorTaskInstallerJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_has_correct_timeout_property(): void
    {
        $server = Server::factory()->create();
        $record = ServerSupervisorTask::factory()->create(['server_id' => $server->id]);
        $job = new SupervisorTaskInstallerJob($server, $record->id);
        $this->assertEquals(600, $job->timeout);
    }

    public function test_job_has_correct_tries_property(): void
    {
        $server = Server::factory()->create();
        $record = ServerSupervisorTask::factory()->create(['server_id' => $server->id]);
        $job = new SupervisorTaskInstallerJob($server, $record->id);
        $this->assertEquals(3, $job->tries);
    }

    public function test_middleware_configured_with_without_overlapping(): void
    {
        $server = Server::factory()->create();
        $record = ServerSupervisorTask::factory()->create(['server_id' => $server->id]);
        $job = new SupervisorTaskInstallerJob($server, $record->id);
        $middleware = $job->middleware();
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(\Illuminate\Queue\Middleware\WithoutOverlapping::class, $middleware[0]);
    }

    public function test_constructor_accepts_server_and_id(): void
    {
        $server = Server::factory()->create();
        $recordId = 123;
        $job = new SupervisorTaskInstallerJob($server, $recordId);
        $this->assertInstanceOf(SupervisorTaskInstallerJob::class, $job);
        $this->assertEquals($server->id, $job->server->id);
        $this->assertEquals($recordId, $job->taskId);
    }

    public function test_failed_method_updates_status_to_failed(): void
    {
        $server = Server::factory()->create();
        $record = ServerSupervisorTask::factory()->create(['server_id' => $server->id]);
        $job = new SupervisorTaskInstallerJob($server, $record->id);
        $exception = new Exception('Operation failed');
        $job->failed($exception);
        $record->refresh();
        $this->assertEquals(SupervisorTaskStatus::Failed, $record->status);
    }

    public function test_failed_method_stores_error_log(): void
    {
        $server = Server::factory()->create();
        $record = ServerSupervisorTask::factory()->create(['server_id' => $server->id, 'error_log' => null]);
        $job = new SupervisorTaskInstallerJob($server, $record->id);
        $errorMessage = 'Test error message';
        $exception = new Exception($errorMessage);
        $job->failed($exception);
        $record->refresh();
        $this->assertEquals($errorMessage, $record->error_log);
    }

    public function test_failed_method_handles_missing_records_gracefully(): void
    {
        $server = Server::factory()->create();
        $nonExistentId = 99999;
        $job = new SupervisorTaskInstallerJob($server, $nonExistentId);
        $exception = new Exception('Test error');
        $job->failed($exception);
        $this->assertDatabaseMissing('server_supervisor_tasks', ['id' => $nonExistentId]);
    }
}
