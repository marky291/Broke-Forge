<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\SchedulerStatus;
use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerScheduledTask;
use App\Models\ServerScheduledTaskRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerSchedulerControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test guest cannot access scheduler page.
     */
    public function test_guest_cannot_access_scheduler_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test authenticated user can access their server scheduler page.
     */
    public function test_user_can_access_their_server_scheduler_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
    }

    /**
     * Test user cannot access other users server scheduler page.
     */
    public function test_user_cannot_access_other_users_server_scheduler_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test scheduler page renders correct Inertia component.
     */
    public function test_scheduler_page_renders_correct_inertia_component(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->has('server')
        );
    }

    /**
     * Test scheduler page includes server data.
     */
    public function test_scheduler_page_includes_server_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Production Server',
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->where('server.id', $server->id)
            ->where('server.vanity_name', 'Production Server')
            ->where('server.scheduler_status', 'active')
        );
    }

    /**
     * Test scheduler page includes scheduled tasks.
     */
    public function test_scheduler_page_includes_scheduled_tasks(): void
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
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->has('server.scheduledTasks', 1)
            ->has('server.scheduledTasks.0', fn ($task) => $task
                ->where('name', 'Daily Backup')
                ->where('command', 'php artisan backup:run')
                ->where('status', 'active')
                ->etc()
            )
        );
    }

    /**
     * Test scheduler page shows multiple tasks.
     */
    public function test_scheduler_page_shows_multiple_tasks(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        ServerScheduledTask::factory()->count(3)->create([
            'server_id' => $server->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->has('server.scheduledTasks', 3)
        );
    }

    /**
     * Test scheduler page shows empty state when no tasks exist.
     */
    public function test_scheduler_page_shows_empty_state_when_no_tasks_exist(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->has('server.scheduledTasks', 0)
        );
    }

    /**
     * Test user can install scheduler successfully.
     */
    public function test_user_can_install_scheduler_successfully(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => null,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/scheduler/install");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Scheduler installation started');

        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'scheduler_status' => SchedulerStatus::Installing->value,
        ]);
    }

    /**
     * Test scheduler installation prevents duplicate installation.
     */
    public function test_scheduler_installation_prevents_duplicate_installation(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/scheduler/install");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test scheduler installation prevents installation during installing.
     */
    public function test_scheduler_installation_prevents_installation_during_installing(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Installing,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/scheduler/install");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user can uninstall scheduler successfully.
     */
    public function test_user_can_uninstall_scheduler_successfully(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/scheduler/uninstall");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Scheduler uninstallation started');

        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'scheduler_status' => SchedulerStatus::Uninstalling->value,
        ]);
    }

    /**
     * Test scheduler uninstall fails when not active.
     */
    public function test_scheduler_uninstall_fails_when_not_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => null,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/scheduler/uninstall");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user cannot install scheduler on other users server.
     */
    public function test_user_cannot_install_scheduler_on_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $otherUser->id,
            'scheduler_status' => null,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/scheduler/install");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user can create scheduled task successfully.
     */
    public function test_user_can_create_scheduled_task_successfully(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/scheduler/tasks", [
                'name' => 'Daily Backup',
                'command' => 'php artisan backup:run',
                'frequency' => 'daily',
                'send_notifications' => false,
                'timeout' => 300,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Scheduled task created and installation started');

        $this->assertDatabaseHas('server_scheduled_tasks', [
            'server_id' => $server->id,
            'name' => 'Daily Backup',
            'command' => 'php artisan backup:run',
            'frequency' => 'daily',
            'timeout' => 300,
        ]);
    }

    /**
     * Test task creation validates required name field.
     */
    public function test_task_creation_validates_required_name(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/scheduler/tasks", [
                'command' => 'php artisan test',
                'frequency' => 'daily',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['name']);
    }

    /**
     * Test task creation validates required command field.
     */
    public function test_task_creation_validates_required_command(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/scheduler/tasks", [
                'name' => 'Test Task',
                'frequency' => 'daily',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['command']);
    }

    /**
     * Test task creation validates required frequency field.
     */
    public function test_task_creation_validates_required_frequency(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/scheduler/tasks", [
                'name' => 'Test Task',
                'command' => 'php artisan test',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['frequency']);
    }

    /**
     * Test task creation validates frequency enum values.
     */
    public function test_task_creation_validates_frequency_enum_values(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/scheduler/tasks", [
                'name' => 'Test Task',
                'command' => 'php artisan test',
                'frequency' => 'invalid',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['frequency']);
    }

    /**
     * Test task creation requires cron expression for custom frequency.
     */
    public function test_task_creation_requires_cron_expression_for_custom_frequency(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/scheduler/tasks", [
                'name' => 'Custom Task',
                'command' => 'php artisan test',
                'frequency' => 'custom',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['cron_expression']);
    }

    /**
     * Test task creation accepts valid custom cron expression.
     */
    public function test_task_creation_accepts_valid_custom_cron_expression(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/scheduler/tasks", [
                'name' => 'Custom Task',
                'command' => 'php artisan test',
                'frequency' => 'custom',
                'cron_expression' => '0 6 * * *',
                'timeout' => 300,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('server_scheduled_tasks', [
            'server_id' => $server->id,
            'name' => 'Custom Task',
            'frequency' => 'custom',
            'cron_expression' => '0 6 * * *',
        ]);
    }

    /**
     * Test task creation fails when scheduler not active.
     */
    public function test_task_creation_fails_when_scheduler_not_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => null,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/scheduler/tasks", [
                'name' => 'Test Task',
                'command' => 'php artisan test',
                'frequency' => 'daily',
            ]);

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user cannot create task for other users server.
     */
    public function test_user_cannot_create_task_for_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $otherUser->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/scheduler/tasks", [
                'name' => 'Unauthorized Task',
                'command' => 'php artisan test',
                'frequency' => 'daily',
            ]);

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user can delete scheduled task successfully.
     */
    public function test_user_can_delete_scheduled_task_successfully(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $task = ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/scheduler/tasks/{$task->id}");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Scheduled task removal started');

        $this->assertDatabaseHas('server_scheduled_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Pending->value,
        ]);
    }

    /**
     * Test user cannot delete task for other users server.
     */
    public function test_user_cannot_delete_task_for_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $otherUser->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $task = ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/scheduler/tasks/{$task->id}");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user can toggle task from active to paused.
     */
    public function test_user_can_toggle_task_from_active_to_paused(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $task = ServerScheduledTask::factory()->active()->create([
            'server_id' => $server->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/scheduler/tasks/{$task->id}/toggle");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('server_scheduled_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Paused->value,
        ]);
    }

    /**
     * Test user can toggle task from paused to pending.
     */
    public function test_user_can_toggle_task_from_paused_to_pending(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $task = ServerScheduledTask::factory()->paused()->create([
            'server_id' => $server->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/scheduler/tasks/{$task->id}/toggle");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('server_scheduled_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Pending->value,
        ]);
    }

    /**
     * Test user can retry failed task.
     */
    public function test_user_can_retry_failed_task(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $task = ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Failed,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/scheduler/tasks/{$task->id}/retry");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Task retry started');

        $this->assertDatabaseHas('server_scheduled_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Pending->value,
        ]);
    }

    /**
     * Test retry fails for non-failed tasks.
     */
    public function test_retry_fails_for_non_failed_tasks(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $task = ServerScheduledTask::factory()->active()->create([
            'server_id' => $server->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/scheduler/tasks/{$task->id}/retry");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('error', 'Only failed tasks can be retried');
    }

    /**
     * Test user can manually run active task.
     */
    public function test_user_can_manually_run_active_task(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');
    }

    /**
     * Test manual run fails when scheduler not active.
     */
    public function test_manual_run_fails_when_scheduler_not_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => null,
        ]);
        $task = ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/scheduler/tasks/{$task->id}/run");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test scheduler page includes task run history.
     */
    public function test_scheduler_page_includes_task_run_history(): void
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
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->has('server.recentTaskRuns.data', 1)
        );
    }

    /**
     * Test scheduler page includes successful task runs.
     */
    public function test_scheduler_page_includes_successful_task_runs(): void
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
            'output' => 'Success output',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->has('server.recentTaskRuns.data', 1)
            ->where('server.recentTaskRuns.data.0.was_successful', true)
            ->where('server.recentTaskRuns.data.0.exit_code', 0)
        );
    }

    /**
     * Test scheduler page includes failed task runs.
     */
    public function test_scheduler_page_includes_failed_task_runs(): void
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

        ServerScheduledTaskRun::factory()->failed()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            'exit_code' => 1,
            'error_output' => 'Error occurred',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->has('server.recentTaskRuns.data', 1)
            ->where('server.recentTaskRuns.data.0.was_successful', false)
            ->where('server.recentTaskRuns.data.0.exit_code', 1)
        );
    }

    /**
     * Test scheduler page shows tasks with different frequencies.
     */
    public function test_scheduler_page_shows_tasks_with_different_frequencies(): void
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

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->has('server.scheduledTasks', 2)
            ->where('server.scheduledTasks.0.frequency', 'daily')
            ->where('server.scheduledTasks.1.frequency', 'hourly')
        );
    }

    /**
     * Test scheduler page shows tasks with different statuses.
     */
    public function test_scheduler_page_shows_tasks_with_different_statuses(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        $activeTask = ServerScheduledTask::factory()->active()->create([
            'server_id' => $server->id,
            'name' => 'Active Task',
        ]);

        $pausedTask = ServerScheduledTask::factory()->paused()->create([
            'server_id' => $server->id,
            'name' => 'Paused Task',
        ]);

        $failedTask = ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Failed Task',
            'status' => TaskStatus::Failed,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->has('server.scheduledTasks', 3)
            ->has('server.scheduledTasks.0', fn ($task) => $task
                ->where('id', $activeTask->id)
                ->where('status', 'active')
                ->etc()
            )
            ->has('server.scheduledTasks.1', fn ($task) => $task
                ->where('id', $pausedTask->id)
                ->where('status', 'paused')
                ->etc()
            )
            ->has('server.scheduledTasks.2', fn ($task) => $task
                ->where('id', $failedTask->id)
                ->where('status', 'failed')
                ->etc()
            )
        );
    }
}
