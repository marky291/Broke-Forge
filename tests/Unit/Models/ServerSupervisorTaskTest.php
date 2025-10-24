<?php

namespace Tests\Unit\Models;

use App\Enums\TaskStatus;
use App\Events\ServerUpdated;
use App\Models\Server;
use App\Models\ServerSupervisorTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ServerSupervisorTaskTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that isActive returns true when status is Active.
     */
    public function test_is_active_returns_true_when_status_is_active(): void
    {
        // Arrange
        $task = ServerSupervisorTask::factory()->active()->create();

        // Act & Assert
        $this->assertTrue($task->isActive());
    }

    /**
     * Test that isActive returns false when status is not Active.
     */
    public function test_is_active_returns_false_when_status_is_not_active(): void
    {
        // Arrange
        $task = ServerSupervisorTask::factory()->inactive()->create();

        // Act & Assert
        $this->assertFalse($task->isActive());
    }

    /**
     * Test that isInactive returns true when status is Inactive.
     */
    public function test_is_inactive_returns_true_when_status_is_inactive(): void
    {
        // Arrange
        $task = ServerSupervisorTask::factory()->inactive()->create();

        // Act & Assert
        $this->assertTrue($task->isInactive());
    }

    /**
     * Test that isInactive returns false when status is not Inactive.
     */
    public function test_is_inactive_returns_false_when_status_is_not_inactive(): void
    {
        // Arrange
        $task = ServerSupervisorTask::factory()->active()->create();

        // Act & Assert
        $this->assertFalse($task->isInactive());
    }

    /**
     * Test that isFailed returns true when status is Failed.
     */
    public function test_is_failed_returns_true_when_status_is_failed(): void
    {
        // Arrange
        $task = ServerSupervisorTask::factory()->failed()->create();

        // Act & Assert
        $this->assertTrue($task->isFailed());
    }

    /**
     * Test that isFailed returns false when status is not Failed.
     */
    public function test_is_failed_returns_false_when_status_is_not_failed(): void
    {
        // Arrange
        $task = ServerSupervisorTask::factory()->active()->create();

        // Act & Assert
        $this->assertFalse($task->isFailed());
    }

    /**
     * Test that task belongs to a server.
     */
    public function test_task_belongs_to_server(): void
    {
        // Arrange
        $server = Server::factory()->create(['vanity_name' => 'supervisor-server']);
        $task = ServerSupervisorTask::factory()->create(['server_id' => $server->id]);

        // Act
        $relatedServer = $task->server;

        // Assert
        $this->assertInstanceOf(Server::class, $relatedServer);
        $this->assertEquals($server->id, $relatedServer->id);
        $this->assertEquals('supervisor-server', $relatedServer->vanity_name);
    }

    /**
     * Test that status is cast to SupervisorTaskStatus enum.
     */
    public function test_status_is_cast_to_supervisor_task_status_enum(): void
    {
        // Arrange
        $task = ServerSupervisorTask::factory()->active()->create();

        // Act & Assert
        $this->assertInstanceOf(TaskStatus::class, $task->status);
        $this->assertEquals(TaskStatus::Active, $task->status);
    }

    /**
     * Test that processes is cast to integer.
     */
    public function test_processes_is_cast_to_integer(): void
    {
        // Arrange
        $task = ServerSupervisorTask::factory()->create(['processes' => '4']);

        // Act
        $processes = $task->processes;

        // Assert
        $this->assertIsInt($processes);
        $this->assertEquals(4, $processes);
    }

    /**
     * Test that auto_restart is cast to boolean.
     */
    public function test_auto_restart_is_cast_to_boolean(): void
    {
        // Arrange
        $task = ServerSupervisorTask::factory()->create(['auto_restart' => '1']);

        // Act
        $autoRestart = $task->auto_restart;

        // Assert
        $this->assertIsBool($autoRestart);
        $this->assertTrue($autoRestart);
    }

    /**
     * Test that autorestart_unexpected is cast to boolean.
     */
    public function test_autorestart_unexpected_is_cast_to_boolean(): void
    {
        // Arrange
        $task = ServerSupervisorTask::factory()->create(['autorestart_unexpected' => '0']);

        // Act
        $autorestartUnexpected = $task->autorestart_unexpected;

        // Assert
        $this->assertIsBool($autorestartUnexpected);
        $this->assertFalse($autorestartUnexpected);
    }

    /**
     * Test that installed_at is cast to datetime.
     */
    public function test_installed_at_is_cast_to_datetime(): void
    {
        // Arrange
        $task = ServerSupervisorTask::factory()->create(['installed_at' => now()]);

        // Act & Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $task->installed_at);
    }

    /**
     * Test that uninstalled_at is cast to datetime.
     */
    public function test_uninstalled_at_is_cast_to_datetime(): void
    {
        // Arrange
        $task = ServerSupervisorTask::factory()->create(['uninstalled_at' => now()]);

        // Act & Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $task->uninstalled_at);
    }

    /**
     * Test that uninstalled_at can be null.
     */
    public function test_uninstalled_at_can_be_null(): void
    {
        // Arrange
        $task = ServerSupervisorTask::factory()->create(['uninstalled_at' => null]);

        // Act & Assert
        $this->assertNull($task->uninstalled_at);
    }

    /**
     * Test that ServerUpdated event is dispatched when task is created.
     */
    public function test_server_updated_event_dispatched_on_task_created(): void
    {
        // Arrange
        Event::fake([ServerUpdated::class]);
        $server = Server::factory()->create();

        // Act
        ServerSupervisorTask::factory()->create(['server_id' => $server->id]);

        // Assert
        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    /**
     * Test that ServerUpdated event is dispatched when task is updated.
     */
    public function test_server_updated_event_dispatched_on_task_updated(): void
    {
        // Arrange
        $task = ServerSupervisorTask::factory()->active()->create();
        Event::fake([ServerUpdated::class]);

        // Act
        $task->update(['status' => TaskStatus::Paused]);

        // Assert
        Event::assertDispatched(ServerUpdated::class, function ($event) use ($task) {
            return $event->serverId === $task->server_id;
        });
    }

    /**
     * Test that ServerUpdated event is dispatched when task is deleted.
     */
    public function test_server_updated_event_dispatched_on_task_deleted(): void
    {
        // Arrange
        $task = ServerSupervisorTask::factory()->create();
        Event::fake([ServerUpdated::class]);
        $serverId = $task->server_id;

        // Act
        $task->delete();

        // Assert
        Event::assertDispatched(ServerUpdated::class, function ($event) use ($serverId) {
            return $event->serverId === $serverId;
        });
    }

    /**
     * Test that all status checks are mutually exclusive.
     */
    public function test_all_status_checks_are_mutually_exclusive(): void
    {
        // Active task
        $active = ServerSupervisorTask::factory()->active()->create();
        $this->assertTrue($active->isActive());
        $this->assertFalse($active->isInactive());
        $this->assertFalse($active->isFailed());

        // Inactive task
        $inactive = ServerSupervisorTask::factory()->inactive()->create();
        $this->assertFalse($inactive->isActive());
        $this->assertTrue($inactive->isInactive());
        $this->assertFalse($inactive->isFailed());

        // Failed task
        $failed = ServerSupervisorTask::factory()->failed()->create();
        $this->assertFalse($failed->isActive());
        $this->assertFalse($failed->isInactive());
        $this->assertTrue($failed->isFailed());
    }

    /**
     * Test that factory creates valid task with all required fields.
     */
    public function test_factory_creates_valid_task(): void
    {
        // Act
        $task = ServerSupervisorTask::factory()->create();

        // Assert
        $this->assertInstanceOf(ServerSupervisorTask::class, $task);
        $this->assertNotNull($task->server_id);
        $this->assertNotNull($task->name);
        $this->assertNotNull($task->command);
        $this->assertNotNull($task->working_directory);
        $this->assertNotNull($task->processes);
        $this->assertNotNull($task->user);
        $this->assertNotNull($task->status);
    }

    /**
     * Test that factory active state creates active task.
     */
    public function test_factory_active_state_creates_active_task(): void
    {
        // Act
        $task = ServerSupervisorTask::factory()->active()->create();

        // Assert
        $this->assertEquals(TaskStatus::Active, $task->status);
        $this->assertTrue($task->isActive());
    }

    /**
     * Test that factory inactive state creates inactive task.
     */
    public function test_factory_inactive_state_creates_inactive_task(): void
    {
        // Act
        $task = ServerSupervisorTask::factory()->inactive()->create();

        // Assert
        $this->assertEquals(TaskStatus::Paused, $task->status);
        $this->assertTrue($task->isInactive());
    }

    /**
     * Test that factory failed state creates failed task.
     */
    public function test_factory_failed_state_creates_failed_task(): void
    {
        // Act
        $task = ServerSupervisorTask::factory()->failed()->create();

        // Assert
        $this->assertEquals(TaskStatus::Failed, $task->status);
        $this->assertTrue($task->isFailed());
    }

    /**
     * Test that factory command state sets specific command.
     */
    public function test_factory_command_state_sets_specific_command(): void
    {
        // Act
        $task = ServerSupervisorTask::factory()->command('php artisan custom:command')->create();

        // Assert
        $this->assertEquals('php artisan custom:command', $task->command);
    }

    /**
     * Test that factory user state sets specific user.
     */
    public function test_factory_user_state_sets_specific_user(): void
    {
        // Act
        $task = ServerSupervisorTask::factory()->user('root')->create();

        // Assert
        $this->assertEquals('root', $task->user);
    }

    /**
     * Test that task can have custom stdout and stderr log files.
     */
    public function test_task_can_have_custom_log_files(): void
    {
        // Arrange
        $task = ServerSupervisorTask::factory()->create([
            'stdout_logfile' => '/var/log/supervisor/task-stdout.log',
            'stderr_logfile' => '/var/log/supervisor/task-stderr.log',
        ]);

        // Act & Assert
        $this->assertEquals('/var/log/supervisor/task-stdout.log', $task->stdout_logfile);
        $this->assertEquals('/var/log/supervisor/task-stderr.log', $task->stderr_logfile);
    }

    /**
     * Test that task can have null log files.
     */
    public function test_task_can_have_null_log_files(): void
    {
        // Arrange
        $task = ServerSupervisorTask::factory()->create([
            'stdout_logfile' => null,
            'stderr_logfile' => null,
        ]);

        // Act & Assert
        $this->assertNull($task->stdout_logfile);
        $this->assertNull($task->stderr_logfile);
    }

    /**
     * Test that task working_directory defaults to /home/brokeforge.
     */
    public function test_task_working_directory_defaults_correctly(): void
    {
        // Act
        $task = ServerSupervisorTask::factory()->create();

        // Assert
        $this->assertEquals('/home/brokeforge', $task->working_directory);
    }

    /**
     * Test that task can have custom working directory.
     */
    public function test_task_can_have_custom_working_directory(): void
    {
        // Arrange
        $task = ServerSupervisorTask::factory()->create([
            'working_directory' => '/var/www/app',
        ]);

        // Act & Assert
        $this->assertEquals('/var/www/app', $task->working_directory);
    }

    /**
     * Test that processes must be a positive integer.
     */
    public function test_processes_is_positive_integer(): void
    {
        // Arrange
        $task = ServerSupervisorTask::factory()->create(['processes' => 3]);

        // Act & Assert
        $this->assertIsInt($task->processes);
        $this->assertGreaterThan(0, $task->processes);
    }

    /**
     * Test that auto_restart and autorestart_unexpected work together.
     */
    public function test_auto_restart_and_autorestart_unexpected_work_together(): void
    {
        // Arrange
        $task = ServerSupervisorTask::factory()->create([
            'auto_restart' => true,
            'autorestart_unexpected' => false,
        ]);

        // Act & Assert
        $this->assertTrue($task->auto_restart);
        $this->assertFalse($task->autorestart_unexpected);
    }

    /**
     * Test that status can transition from pending to active.
     */
    public function test_status_can_transition_from_pending_to_active(): void
    {
        // Arrange
        $task = ServerSupervisorTask::factory()->create(['status' => TaskStatus::Pending]);
        $this->assertEquals(TaskStatus::Pending, $task->status);

        // Act
        $task->update(['status' => TaskStatus::Active]);

        // Assert
        $this->assertEquals(TaskStatus::Active, $task->status);
        $this->assertTrue($task->isActive());
    }

    /**
     * Test that status can transition to failed.
     */
    public function test_status_can_transition_to_failed(): void
    {
        // Arrange
        $task = ServerSupervisorTask::factory()->create(['status' => TaskStatus::Installing]);
        $this->assertEquals(TaskStatus::Installing, $task->status);

        // Act
        $task->update(['status' => TaskStatus::Failed]);

        // Assert
        $this->assertEquals(TaskStatus::Failed, $task->status);
        $this->assertTrue($task->isFailed());
    }
}
