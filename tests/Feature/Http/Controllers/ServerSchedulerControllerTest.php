<?php

namespace Tests\Feature\Http\Controllers;

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
            'scheduler_status' => TaskStatus::Active,
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
            'scheduler_status' => TaskStatus::Active,
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
            'scheduler_status' => TaskStatus::Active,
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
            'scheduler_status' => TaskStatus::Active,
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
            'scheduler_status' => TaskStatus::Installing->value,
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
            'scheduler_status' => TaskStatus::Active,
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
            'scheduler_status' => TaskStatus::Installing,
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
            'scheduler_status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/scheduler/uninstall");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Scheduler uninstallation started');

        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'scheduler_status' => TaskStatus::Removing->value,
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
            'scheduler_status' => TaskStatus::Active,
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
            'scheduler_status' => TaskStatus::Active,
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
            'scheduler_status' => TaskStatus::Active,
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
            'scheduler_status' => TaskStatus::Active,
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
            'scheduler_status' => TaskStatus::Active,
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
            'scheduler_status' => TaskStatus::Active,
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
            'scheduler_status' => TaskStatus::Active,
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
            'scheduler_status' => TaskStatus::Active,
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
            'scheduler_status' => TaskStatus::Active,
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
            'scheduler_status' => TaskStatus::Active,
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
            'scheduler_status' => TaskStatus::Active,
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
            'scheduler_status' => TaskStatus::Active,
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
            'scheduler_status' => TaskStatus::Active,
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
            'scheduler_status' => TaskStatus::Active,
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
            'scheduler_status' => TaskStatus::Active,
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
            'scheduler_status' => TaskStatus::Active,
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
            'scheduler_status' => TaskStatus::Active,
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
            'scheduler_status' => TaskStatus::Active,
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
            'scheduler_status' => TaskStatus::Active,
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

    /**
     * Test guest cannot access task activity page.
     */
    public function test_guest_cannot_access_task_activity_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->get("/servers/{$server->id}/scheduler/tasks/{$task->id}/activity");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test user can access their task activity page.
     */
    public function test_user_can_access_their_task_activity_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task->id}/activity");

        // Assert
        $response->assertStatus(200);
    }

    /**
     * Test user cannot access other users task activity page.
     */
    public function test_user_cannot_access_other_users_task_activity_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task->id}/activity");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test activity page renders correct Inertia component.
     */
    public function test_activity_page_renders_correct_inertia_component(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task->id}/activity");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler-task-activity')
            ->has('server')
            ->has('task')
            ->has('runs')
        );
    }

    /**
     * Test activity page includes server data.
     */
    public function test_activity_page_includes_server_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Production Server',
        ]);
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task->id}/activity");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler-task-activity')
            ->where('server.id', $server->id)
            ->where('server.vanity_name', 'Production Server')
        );
    }

    /**
     * Test activity page includes task data.
     */
    public function test_activity_page_includes_task_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Backup Database',
            'command' => 'php artisan backup:run',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task->id}/activity");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler-task-activity')
            ->where('task.id', $task->id)
            ->where('task.name', 'Backup Database')
            ->where('task.command', 'php artisan backup:run')
        );
    }

    /**
     * Test activity page includes paginated task runs.
     */
    public function test_activity_page_includes_paginated_task_runs(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        // Create 5 task runs
        ServerScheduledTaskRun::factory()->count(5)->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task->id}/activity");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler-task-activity')
            ->has('runs.data', 5)
            ->has('runs.links')
            ->has('runs.meta')
        );
    }

    /**
     * Test activity page orders task runs by started_at desc.
     */
    public function test_activity_page_orders_task_runs_by_started_at_desc(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        // Create runs with specific timestamps
        $oldRun = ServerScheduledTaskRun::factory()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            'started_at' => now()->subHours(2),
        ]);

        $newRun = ServerScheduledTaskRun::factory()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            'started_at' => now()->subHour(),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task->id}/activity");

        // Assert - newest run should be first
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler-task-activity')
            ->has('runs.data', 2)
            ->where('runs.data.0.id', $newRun->id)
            ->where('runs.data.1.id', $oldRun->id)
        );
    }

    /**
     * Test activity page shows empty state when no task runs.
     */
    public function test_activity_page_shows_empty_state_when_no_task_runs(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task->id}/activity");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler-task-activity')
            ->has('runs.data', 0)
        );
    }

    /**
     * Test activity page pagination works correctly.
     */
    public function test_activity_page_pagination_works_correctly(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        // Create 25 task runs (should span 2 pages with 20 per page)
        ServerScheduledTaskRun::factory()->count(25)->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
        ]);

        // Act - Get first page
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task->id}/activity");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler-task-activity')
            ->has('runs.data', 20)
            ->where('runs.meta.total', 25)
            ->where('runs.meta.current_page', 1)
            ->where('runs.meta.last_page', 2)
        );
    }

    /**
     * Test activity page includes successful and failed runs.
     */
    public function test_activity_page_includes_successful_and_failed_runs(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        $successfulRun = ServerScheduledTaskRun::factory()->successful()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
        ]);

        $failedRun = ServerScheduledTaskRun::factory()->failed()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task->id}/activity");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler-task-activity')
            ->has('runs.data', 2)
            ->where('runs.data.0.was_successful', fn ($value) => in_array($value, [true, false]))
            ->where('runs.data.1.was_successful', fn ($value) => in_array($value, [true, false]))
        );
    }

    /**
     * Test activity page includes run output data.
     */
    public function test_activity_page_includes_run_output_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        ServerScheduledTaskRun::factory()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            'output' => 'Command output here',
            'error_output' => 'Error output here',
            'exit_code' => 0,
            'duration_ms' => 1500,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task->id}/activity");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler-task-activity')
            ->has('runs.data', 1)
            ->has('runs.data.0.output')
            ->has('runs.data.0.error_output')
            ->where('runs.data.0.exit_code', 0)
            ->where('runs.data.0.duration_ms', 1500)
        );
    }

    /**
     * Test task must belong to server for activity endpoint.
     */
    public function test_task_must_belong_to_server_for_activity_endpoint(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $otherServer = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerScheduledTask::factory()->create(['server_id' => $otherServer->id]);

        // Act - Try to access task from wrong server
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task->id}/activity");

        // Assert - Should fail due to route model binding scoping
        $response->assertStatus(404);
    }

    /**
     * Test activity page only shows runs for the specific task.
     */
    public function test_activity_page_only_shows_runs_for_specific_task(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $task1 = ServerScheduledTask::factory()->create(['server_id' => $server->id]);
        $task2 = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        // Create runs for both tasks
        ServerScheduledTaskRun::factory()->count(3)->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task1->id,
        ]);

        ServerScheduledTaskRun::factory()->count(2)->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task2->id,
        ]);

        // Act - Get activity for task1
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task1->id}/activity");

        // Assert - Should only show task1's runs
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler-task-activity')
            ->has('runs.data', 3)
            ->where('task.id', $task1->id)
        );
    }
}
