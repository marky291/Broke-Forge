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

    /**
     * Test failed() method updates status to TaskStatus::Failed.
     */
    public function test_failed_method_updates_status_to_failed(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $task = ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Installing,
        ]);

        $job = new ServerScheduleTaskInstallerJob($server, $task->id);
        $exception = new Exception('Task installation failed');

        // Act
        $job->failed($exception);

        // Assert
        $task->refresh();
        $this->assertEquals(TaskStatus::Failed, $task->status);
    }

    /**
     * Test failed() method stores error message.
     */
    public function test_failed_method_stores_error_log(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $task = ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Installing,
            'error_log' => null,
        ]);

        $job = new ServerScheduleTaskInstallerJob($server, $task->id);
        $errorMessage = 'Cron installation timeout';
        $exception = new Exception($errorMessage);

        // Act
        $job->failed($exception);

        // Assert
        $task->refresh();
        $this->assertEquals($errorMessage, $task->error_log);
    }

    /**
     * Test failed() method handles missing records gracefully.
     */
    public function test_failed_method_handles_missing_records_gracefully(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $nonExistentId = 99999;

        $job = new ServerScheduleTaskInstallerJob($server, $nonExistentId);
        $exception = new Exception('Test error');

        // Act - should not throw exception
        $job->failed($exception);

        // Assert - verify no scheduled task was created
        $this->assertDatabaseMissing('server_scheduled_tasks', [
            'id' => $nonExistentId,
        ]);
    }

    /**
     * Test catch block sets failed status immediately when exception occurs.
     */
    public function test_catch_block_sets_failed_status_immediately(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $task = ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Pending,
            'command' => 'php artisan test',
        ]);

        $job = new ServerScheduleTaskInstallerJob($server, $task->id);

        // Act & Assert - handle() will throw exception because installer cannot run
        // We expect the catch block to set status to failed
        try {
            $job->handle();
        } catch (Exception $e) {
            // Expected to throw
        }

        // Assert
        $task->refresh();
        $this->assertEquals(TaskStatus::Failed, $task->status);
        $this->assertNotNull($task->error_log);
    }

    /**
     * Test failed() method updates from any status to failed.
     */
    public function test_failed_method_updates_from_any_status_to_failed(): void
    {
        // Arrange - test from pending status
        $server = Server::factory()->create();
        $task = ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Pending,
        ]);

        $job = new ServerScheduleTaskInstallerJob($server, $task->id);
        $exception = new Exception('Scheduler configuration failed');

        // Act
        $job->failed($exception);

        // Assert
        $task->refresh();
        $this->assertEquals(TaskStatus::Failed, $task->status);
        $this->assertEquals('Scheduler configuration failed', $task->error_log);
    }

    /**
     * Test failed() method preserves scheduled task data except status and error.
     */
    public function test_failed_method_preserves_scheduled_task_data(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $task = ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Installing,
            'name' => 'Daily Backup',
            'command' => 'backup:run',
            'timeout' => 3600,
        ]);

        $job = new ServerScheduleTaskInstallerJob($server, $task->id);
        $exception = new Exception('Installation error');

        // Act
        $job->failed($exception);

        // Assert - verify other fields remain unchanged
        $task->refresh();
        $this->assertEquals('Daily Backup', $task->name);
        $this->assertEquals('backup:run', $task->command);
        $this->assertEquals(3600, $task->timeout);
        $this->assertEquals($server->id, $task->server_id);
    }

    /**
     * Test failed() method handles different exception types.
     */
    public function test_failed_method_handles_different_exception_types(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $task = ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Installing,
        ]);

        $job = new ServerScheduleTaskInstallerJob($server, $task->id);
        $exception = new \RuntimeException('SSH connection lost');

        // Act
        $job->failed($exception);

        // Assert
        $task->refresh();
        $this->assertEquals(TaskStatus::Failed, $task->status);
        $this->assertEquals('SSH connection lost', $task->error_log);
    }

    /**
     * Test failed() method clears error on subsequent success.
     */
    public function test_failed_method_error_can_be_cleared(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $task = ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Installing,
            'error_log' => null,
        ]);

        $job = new ServerScheduleTaskInstallerJob($server, $task->id);
        $exception = new Exception('Temporary failure');

        // Act - first failure
        $job->failed($exception);

        // Assert
        $task->refresh();
        $this->assertEquals(TaskStatus::Failed, $task->status);
        $this->assertEquals('Temporary failure', $task->error_log);

        // Act - simulate retry with different error
        $newException = new Exception('Second failure');
        $newJob = new ServerScheduleTaskInstallerJob($server, $task->id);
        $newJob->failed($newException);

        // Assert - error message updated
        $task->refresh();
        $this->assertEquals('Second failure', $task->error_log);
    }
}
