<?php

namespace Tests\Unit\Models;

use App\Enums\ScheduleFrequency;
use App\Enums\TaskStatus;
use App\Events\ServerUpdated;
use App\Models\Server;
use App\Models\ServerScheduledTask;
use App\Models\ServerScheduledTaskRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ServerScheduledTaskTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that isActive returns true when status is Active.
     */
    public function test_is_active_returns_true_when_status_is_active(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->active()->create();

        // Act & Assert
        $this->assertTrue($task->isActive());
    }

    /**
     * Test that isActive returns false when status is not Active.
     */
    public function test_is_active_returns_false_when_status_is_not_active(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->paused()->create();

        // Act & Assert
        $this->assertFalse($task->isActive());
    }

    /**
     * Test that isPaused returns true when status is Paused.
     */
    public function test_is_paused_returns_true_when_status_is_paused(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->paused()->create();

        // Act & Assert
        $this->assertTrue($task->isPaused());
    }

    /**
     * Test that isPaused returns false when status is not Paused.
     */
    public function test_is_paused_returns_false_when_status_is_not_paused(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->active()->create();

        // Act & Assert
        $this->assertFalse($task->isPaused());
    }

    /**
     * Test that isFailed returns true when status is Failed.
     */
    public function test_is_failed_returns_true_when_status_is_failed(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create(['status' => TaskStatus::Failed]);

        // Act & Assert
        $this->assertTrue($task->isFailed());
    }

    /**
     * Test that isFailed returns false when status is not Failed.
     */
    public function test_is_failed_returns_false_when_status_is_not_failed(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->active()->create();

        // Act & Assert
        $this->assertFalse($task->isFailed());
    }

    /**
     * Test that getCronExpression returns custom expression when frequency is Custom.
     */
    public function test_get_cron_expression_returns_custom_expression_for_custom_frequency(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->custom('*/5 * * * *')->create();

        // Act
        $cronExpression = $task->getCronExpression();

        // Assert
        $this->assertEquals('*/5 * * * *', $cronExpression);
    }

    /**
     * Test that getCronExpression returns enum expression when frequency is not Custom.
     */
    public function test_get_cron_expression_returns_enum_expression_for_hourly_frequency(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->hourly()->create();

        // Act
        $cronExpression = $task->getCronExpression();

        // Assert
        $this->assertEquals('0 * * * *', $cronExpression); // Hourly cron
    }

    /**
     * Test that getCronExpression returns enum expression for daily frequency.
     */
    public function test_get_cron_expression_returns_enum_expression_for_daily_frequency(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->daily()->create();

        // Act
        $cronExpression = $task->getCronExpression();

        // Assert
        $this->assertEquals('0 0 * * *', $cronExpression); // Daily cron
    }

    /**
     * Test that getCronExpression returns fallback when Custom frequency has null cron_expression.
     */
    public function test_get_cron_expression_returns_fallback_when_custom_frequency_has_null_expression(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create([
            'frequency' => ScheduleFrequency::Custom,
            'cron_expression' => null,
        ]);

        // Act
        $cronExpression = $task->getCronExpression();

        // Assert
        $this->assertEquals('* * * * *', $cronExpression); // Fallback
    }

    /**
     * Test that getCronExpression works for all standard frequencies.
     */
    public function test_get_cron_expression_works_for_all_standard_frequencies(): void
    {
        // Test Minutely
        $minutely = ServerScheduledTask::factory()->create([
            'frequency' => ScheduleFrequency::Minutely,
            'cron_expression' => null,
        ]);
        $this->assertEquals('* * * * *', $minutely->getCronExpression());

        // Test Weekly
        $weekly = ServerScheduledTask::factory()->create([
            'frequency' => ScheduleFrequency::Weekly,
            'cron_expression' => null,
        ]);
        $this->assertEquals('0 0 * * 0', $weekly->getCronExpression());

        // Test Monthly
        $monthly = ServerScheduledTask::factory()->create([
            'frequency' => ScheduleFrequency::Monthly,
            'cron_expression' => null,
        ]);
        $this->assertEquals('0 0 1 * *', $monthly->getCronExpression());
    }

    /**
     * Test that task belongs to a server.
     */
    public function test_task_belongs_to_server(): void
    {
        // Arrange
        $server = Server::factory()->create(['vanity_name' => 'test-server']);
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        // Act
        $relatedServer = $task->server;

        // Assert
        $this->assertInstanceOf(Server::class, $relatedServer);
        $this->assertEquals($server->id, $relatedServer->id);
        $this->assertEquals('test-server', $relatedServer->vanity_name);
    }

    /**
     * Test that task has many runs.
     */
    public function test_task_has_many_runs(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create();
        ServerScheduledTaskRun::factory()->count(3)->create([
            'server_scheduled_task_id' => $task->id,
        ]);

        // Act
        $runs = $task->runs;

        // Assert
        $this->assertCount(3, $runs);
        $this->assertInstanceOf(ServerScheduledTaskRun::class, $runs->first());
    }

    /**
     * Test that latestRun returns the most recent run.
     */
    public function test_latest_run_returns_most_recent_run(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create();

        // Create runs with different timestamps
        ServerScheduledTaskRun::factory()->create([
            'server_scheduled_task_id' => $task->id,
            'started_at' => now()->subHours(3),
        ]);
        ServerScheduledTaskRun::factory()->create([
            'server_scheduled_task_id' => $task->id,
            'started_at' => now()->subHours(2),
        ]);
        $latestRun = ServerScheduledTaskRun::factory()->create([
            'server_scheduled_task_id' => $task->id,
            'started_at' => now()->subHour(),
        ]);

        // Act
        $retrievedLatestRun = $task->latestRun();

        // Assert
        $this->assertNotNull($retrievedLatestRun);
        $this->assertEquals($latestRun->id, $retrievedLatestRun->id);
    }

    /**
     * Test that latestRun returns null when no runs exist.
     */
    public function test_latest_run_returns_null_when_no_runs_exist(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create();

        // Act
        $latestRun = $task->latestRun();

        // Assert
        $this->assertNull($latestRun);
    }

    /**
     * Test that frequency is cast to ScheduleFrequency enum.
     */
    public function test_frequency_is_cast_to_schedule_frequency_enum(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->daily()->create();

        // Act & Assert
        $this->assertInstanceOf(ScheduleFrequency::class, $task->frequency);
        $this->assertEquals(ScheduleFrequency::Daily, $task->frequency);
    }

    /**
     * Test that status is cast to TaskStatus enum.
     */
    public function test_status_is_cast_to_task_status_enum(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->active()->create();

        // Act & Assert
        $this->assertInstanceOf(TaskStatus::class, $task->status);
        $this->assertEquals(TaskStatus::Active, $task->status);
    }

    /**
     * Test that last_run_at is cast to datetime.
     */
    public function test_last_run_at_is_cast_to_datetime(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create(['last_run_at' => now()]);

        // Act & Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $task->last_run_at);
    }

    /**
     * Test that next_run_at is cast to datetime.
     */
    public function test_next_run_at_is_cast_to_datetime(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create(['next_run_at' => now()]);

        // Act & Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $task->next_run_at);
    }

    /**
     * Test that send_notifications is cast to boolean.
     */
    public function test_send_notifications_is_cast_to_boolean(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create(['send_notifications' => '1']);

        // Act
        $sendNotifications = $task->send_notifications;

        // Assert
        $this->assertIsBool($sendNotifications);
        $this->assertTrue($sendNotifications);
    }

    /**
     * Test that timeout is cast to integer.
     */
    public function test_timeout_is_cast_to_integer(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create(['timeout' => '300']);

        // Act
        $timeout = $task->timeout;

        // Assert
        $this->assertIsInt($timeout);
        $this->assertEquals(300, $timeout);
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
        ServerScheduledTask::factory()->create(['server_id' => $server->id]);

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
        $task = ServerScheduledTask::factory()->active()->create();
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
        $task = ServerScheduledTask::factory()->create();
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
        $active = ServerScheduledTask::factory()->active()->create();
        $this->assertTrue($active->isActive());
        $this->assertFalse($active->isPaused());
        $this->assertFalse($active->isFailed());

        // Paused task
        $paused = ServerScheduledTask::factory()->paused()->create();
        $this->assertFalse($paused->isActive());
        $this->assertTrue($paused->isPaused());
        $this->assertFalse($paused->isFailed());

        // Failed task
        $failed = ServerScheduledTask::factory()->create(['status' => TaskStatus::Failed]);
        $this->assertFalse($failed->isActive());
        $this->assertFalse($failed->isPaused());
        $this->assertTrue($failed->isFailed());
    }

    /**
     * Test that factory creates valid task with all required fields.
     */
    public function test_factory_creates_valid_task(): void
    {
        // Act
        $task = ServerScheduledTask::factory()->create();

        // Assert
        $this->assertInstanceOf(ServerScheduledTask::class, $task);
        $this->assertNotNull($task->server_id);
        $this->assertNotNull($task->name);
        $this->assertNotNull($task->command);
        $this->assertNotNull($task->frequency);
        $this->assertNotNull($task->status);
    }

    /**
     * Test that factory active state creates active task.
     */
    public function test_factory_active_state_creates_active_task(): void
    {
        // Act
        $task = ServerScheduledTask::factory()->active()->create();

        // Assert
        $this->assertEquals(TaskStatus::Active, $task->status);
        $this->assertTrue($task->isActive());
    }

    /**
     * Test that factory paused state creates paused task.
     */
    public function test_factory_paused_state_creates_paused_task(): void
    {
        // Act
        $task = ServerScheduledTask::factory()->paused()->create();

        // Assert
        $this->assertEquals(TaskStatus::Paused, $task->status);
        $this->assertTrue($task->isPaused());
    }

    /**
     * Test that factory daily state creates daily task.
     */
    public function test_factory_daily_state_creates_daily_task(): void
    {
        // Act
        $task = ServerScheduledTask::factory()->daily()->create();

        // Assert
        $this->assertEquals(ScheduleFrequency::Daily, $task->frequency);
        $this->assertNull($task->cron_expression);
        $this->assertEquals('0 0 * * *', $task->getCronExpression());
    }

    /**
     * Test that factory hourly state creates hourly task.
     */
    public function test_factory_hourly_state_creates_hourly_task(): void
    {
        // Act
        $task = ServerScheduledTask::factory()->hourly()->create();

        // Assert
        $this->assertEquals(ScheduleFrequency::Hourly, $task->frequency);
        $this->assertNull($task->cron_expression);
        $this->assertEquals('0 * * * *', $task->getCronExpression());
    }

    /**
     * Test that factory custom state creates custom task with cron expression.
     */
    public function test_factory_custom_state_creates_custom_task_with_expression(): void
    {
        // Act
        $task = ServerScheduledTask::factory()->custom('0 */6 * * *')->create();

        // Assert
        $this->assertEquals(ScheduleFrequency::Custom, $task->frequency);
        $this->assertEquals('0 */6 * * *', $task->cron_expression);
        $this->assertEquals('0 */6 * * *', $task->getCronExpression());
    }
}
