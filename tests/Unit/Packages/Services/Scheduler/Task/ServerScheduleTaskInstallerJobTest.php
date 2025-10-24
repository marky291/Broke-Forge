<?php

namespace Tests\Unit\Packages\Services\Scheduler\Task;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerScheduledTask;
use App\Packages\Services\Scheduler\Task\ServerScheduleTaskInstallerJob;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerScheduleTaskInstallerJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_has_correct_timeout_property(): void
    {
        $server = Server::factory()->create();
        $record = ServerScheduledTask::factory()->create(['server_id' => $server->id]);
        $job = new ServerScheduleTaskInstallerJob($server, $record);
        $this->assertEquals(600, $job->timeout);
    }

    public function test_job_has_correct_tries_property(): void
    {
        $server = Server::factory()->create();
        $record = ServerScheduledTask::factory()->create(['server_id' => $server->id]);
        $job = new ServerScheduleTaskInstallerJob($server, $record);
        $this->assertEquals(0, $job->tries);
    }

    /**
     * Test job has correct maxExceptions property.
     */
    public function test_job_has_correct_max_exceptions_property(): void
    {
        $server = Server::factory()->create();
        $record = ServerScheduledTask::factory()->create(['server_id' => $server->id]);
        $job = new ServerScheduleTaskInstallerJob($server, $record);
        $this->assertEquals(3, $job->maxExceptions);
    }

    public function test_middleware_configured_with_without_overlapping(): void
    {
        $server = Server::factory()->create();
        $record = ServerScheduledTask::factory()->create(['server_id' => $server->id]);
        $job = new ServerScheduleTaskInstallerJob($server, $record);
        $middleware = $job->middleware();
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(\Illuminate\Queue\Middleware\WithoutOverlapping::class, $middleware[0]);
    }

    public function test_constructor_accepts_server_and_task(): void
    {
        $server = Server::factory()->create();
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);
        $job = new ServerScheduleTaskInstallerJob($server, $task);
        $this->assertInstanceOf(ServerScheduleTaskInstallerJob::class, $job);
        $this->assertEquals($server->id, $job->server->id);
        $this->assertEquals($task->id, $job->serverScheduledTask->id);
    }

    public function test_failed_method_updates_status_to_failed(): void
    {
        $server = Server::factory()->create();
        $record = ServerScheduledTask::factory()->create(['server_id' => $server->id]);
        $job = new ServerScheduleTaskInstallerJob($server, $record);
        $exception = new Exception('Operation failed');
        $job->failed($exception);
        $record->refresh();
        $this->assertEquals(TaskStatus::Failed, $record->status);
    }

    public function test_failed_method_stores_error_log(): void
    {
        $server = Server::factory()->create();
        $record = ServerScheduledTask::factory()->create(['server_id' => $server->id, 'error_log' => null]);
        $job = new ServerScheduleTaskInstallerJob($server, $record);
        $errorMessage = 'Test error message';
        $exception = new Exception($errorMessage);
        $job->failed($exception);
        $record->refresh();
        $this->assertEquals($errorMessage, $record->error_log);
    }

    public function test_failed_method_handles_missing_records_gracefully(): void
    {
        $server = Server::factory()->create();
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        $job = new ServerScheduleTaskInstallerJob($server, $task);
        $taskId = $task->id;
        $task->delete(); // Now fresh() will return null

        $exception = new Exception('Test error');
        $job->failed($exception);
        $this->assertDatabaseMissing('server_scheduled_tasks', ['id' => $taskId]);
    }
}
