<?php

namespace Tests\Inertia\Servers;

use App\Enums\SchedulerStatus;
use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerScheduledTask;
use App\Models\ServerScheduledTaskRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SchedulerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test scheduler page renders correct Inertia component.
     */
    public function test_scheduler_page_renders_correct_component(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
        );
    }

    /**
     * Test scheduler page provides server data in Inertia props.
     */
    public function test_scheduler_page_provides_server_data_in_props(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Test Server',
            'public_ip' => '192.168.1.100',
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->has('server')
            ->where('server.id', $server->id)
            ->where('server.vanity_name', 'Test Server')
            ->where('server.public_ip', '192.168.1.100')
            ->where('server.scheduler_status', 'active')
        );
    }

    /**
     * Test scheduler page includes scheduled tasks array.
     */
    public function test_scheduler_page_includes_scheduled_tasks_array(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Daily Backup',
            'command' => 'php artisan backup:run',
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->has('server.scheduledTasks', 1)
            ->where('server.scheduledTasks.0.name', 'Daily Backup')
            ->where('server.scheduledTasks.0.command', 'php artisan backup:run')
            ->where('server.scheduledTasks.0.status', 'active')
        );
    }

    /**
     * Test scheduler page shows empty state when no tasks.
     */
    public function test_scheduler_page_shows_empty_state_when_no_tasks(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->has('server.scheduledTasks', 0)
        );
    }

    /**
     * Test scheduler page includes task frequency information.
     */
    public function test_scheduler_page_includes_task_frequency_information(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        ServerScheduledTask::factory()->daily()->create([
            'server_id' => $server->id,
            'name' => 'Daily Task',
        ]);

        ServerScheduledTask::factory()->hourly()->create([
            'server_id' => $server->id,
            'name' => 'Hourly Task',
        ]);

        ServerScheduledTask::factory()->custom('*/15 * * * *')->create([
            'server_id' => $server->id,
            'name' => 'Custom Task',
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->has('server.scheduledTasks', 3)
            ->where('server.scheduledTasks.0.frequency', 'daily')
            ->where('server.scheduledTasks.1.frequency', 'hourly')
            ->where('server.scheduledTasks.2.frequency', 'custom')
            ->where('server.scheduledTasks.2.cron_expression', '*/15 * * * *')
        );
    }

    /**
     * Test scheduler page includes task status badges.
     */
    public function test_scheduler_page_includes_task_status_badges(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        ServerScheduledTask::factory()->active()->create([
            'server_id' => $server->id,
        ]);

        ServerScheduledTask::factory()->paused()->create([
            'server_id' => $server->id,
        ]);

        ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Pending,
        ]);

        ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Failed,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->has('server.scheduledTasks', 4)
        );
    }

    /**
     * Test scheduler page includes task timeout information.
     */
    public function test_scheduler_page_includes_task_timeout_information(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
            'timeout' => 600,
            'send_notifications' => true,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->has('server.scheduledTasks', 1)
            ->where('server.scheduledTasks.0.timeout', 600)
            ->where('server.scheduledTasks.0.send_notifications', true)
        );
    }

    /**
     * Test scheduler page includes recent task runs.
     */
    public function test_scheduler_page_includes_recent_task_runs(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $task = ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
        ]);

        ServerScheduledTaskRun::factory()->successful()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            'exit_code' => 0,
            'duration_ms' => 1500,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->has('server.recentTaskRuns.data', 1)
            ->where('server.recentTaskRuns.data.0.server_scheduled_task_id', $task->id)
            ->where('server.recentTaskRuns.data.0.exit_code', 0)
            ->where('server.recentTaskRuns.data.0.was_successful', true)
            ->where('server.recentTaskRuns.data.0.duration_ms', 1500)
        );
    }

    /**
     * Test scheduler page includes successful and failed task runs.
     */
    public function test_scheduler_page_includes_successful_and_failed_task_runs(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $task = ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
        ]);

        ServerScheduledTaskRun::factory()->successful()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            'output' => 'Task completed successfully',
        ]);

        ServerScheduledTaskRun::factory()->failed()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            'exit_code' => 1,
            'error_output' => 'Command failed',
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->has('server.recentTaskRuns.data', 2)
        );
    }

    /**
     * Test scheduler page includes task run output data for modal.
     */
    public function test_scheduler_page_includes_task_run_output_data_for_modal(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $task = ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
        ]);

        ServerScheduledTaskRun::factory()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            'exit_code' => 0,
            'output' => 'Command output here',
            'error_output' => null,
            'duration_ms' => 2500,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->has('server.recentTaskRuns.data', 1)
            ->has('server.recentTaskRuns.data.0', fn ($run) => $run
                ->where('output', 'Command output here')
                ->where('error_output', null)
                ->where('duration_ms', 2500)
                ->has('started_at')
                ->has('completed_at')
                ->etc()
            )
        );
    }

    /**
     * Test scheduler page shows installing state.
     */
    public function test_scheduler_page_shows_installing_state(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Installing,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->where('server.scheduler_status', 'installing')
        );
    }

    /**
     * Test scheduler page shows uninstalling state.
     */
    public function test_scheduler_page_shows_uninstalling_state(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Uninstalling,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->where('server.scheduler_status', 'uninstalling')
        );
    }

    /**
     * Test scheduler page shows not installed state.
     */
    public function test_scheduler_page_shows_not_installed_state(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => null,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->where('server.scheduler_status', null)
        );
    }

    /**
     * Test Inertia form submission creates task.
     */
    public function test_inertia_form_submission_creates_task(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        // Act - simulate Inertia form POST
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/scheduler/tasks", [
                'name' => 'New Task',
                'command' => 'php artisan test',
                'frequency' => 'daily',
                'timeout' => 300,
                'send_notifications' => false,
            ]);

        // Assert - redirects with success message
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Scheduled task created and installation started');

        // Verify database
        $this->assertDatabaseHas('server_scheduled_tasks', [
            'server_id' => $server->id,
            'name' => 'New Task',
            'command' => 'php artisan test',
        ]);
    }

    /**
     * Test Inertia form validation errors are returned.
     */
    public function test_inertia_form_validation_errors_returned(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        // Act - submit invalid data
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/scheduler/tasks", [
                'name' => '',
                'command' => '',
            ]);

        // Assert - validation errors in session
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['name', 'command', 'frequency']);
    }

    /**
     * Test Inertia form validates custom cron expression.
     */
    public function test_inertia_form_validates_custom_cron_expression(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        // Act - submit custom frequency without cron expression
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/scheduler/tasks", [
                'name' => 'Custom Task',
                'command' => 'php artisan test',
                'frequency' => 'custom',
                'timeout' => 300,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['cron_expression']);
    }

    /**
     * Test scheduler page includes pagination data for task runs.
     */
    public function test_scheduler_page_includes_pagination_data_for_task_runs(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $task = ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
        ]);

        // Create 5 task runs
        ServerScheduledTaskRun::factory()->count(5)->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->has('server.recentTaskRuns.data')
            ->has('server.recentTaskRuns.meta')
            ->has('server.recentTaskRuns.links')
        );
    }

    /**
     * Test scheduler page includes task last run timestamp.
     */
    public function test_scheduler_page_includes_task_last_run_timestamp(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        $lastRunAt = now()->subHours(2);
        ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
            'last_run_at' => $lastRunAt,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->has('server.scheduledTasks', 1)
            ->has('server.scheduledTasks.0.last_run_at')
        );
    }

    /**
     * Test scheduler page includes multiple tasks with varied configurations.
     */
    public function test_scheduler_page_includes_multiple_tasks_with_varied_configurations(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        ServerScheduledTask::factory()->daily()->create([
            'server_id' => $server->id,
            'name' => 'Daily Backup',
            'timeout' => 600,
        ]);

        ServerScheduledTask::factory()->hourly()->create([
            'server_id' => $server->id,
            'name' => 'Hourly Cleanup',
            'timeout' => 300,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->has('server.scheduledTasks', 2)
            ->where('server.scheduledTasks.0.name', 'Daily Backup')
            ->where('server.scheduledTasks.0.frequency', 'daily')
            ->where('server.scheduledTasks.0.timeout', 600)
            ->where('server.scheduledTasks.1.name', 'Hourly Cleanup')
            ->where('server.scheduledTasks.1.frequency', 'hourly')
            ->where('server.scheduledTasks.1.timeout', 300)
        );
    }

    /**
     * Test scheduler page task run includes timestamps for modal display.
     */
    public function test_scheduler_page_task_run_includes_timestamps_for_modal_display(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $task = ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
        ]);

        $startedAt = now()->subMinutes(5);
        $completedAt = now()->subMinutes(4);

        ServerScheduledTaskRun::factory()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'duration_ms' => 60000,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->has('server.recentTaskRuns.data', 1)
            ->has('server.recentTaskRuns.data.0.started_at')
            ->has('server.recentTaskRuns.data.0.completed_at')
        );
    }

    /**
     * Test scheduler page provides user authentication state.
     */
    public function test_scheduler_page_provides_user_authentication_state(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/scheduler");

        // Assert - user data shared with Inertia
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('auth.user')
            ->where('auth.user.name', 'John Doe')
            ->where('auth.user.email', 'john@example.com')
        );
    }
}
