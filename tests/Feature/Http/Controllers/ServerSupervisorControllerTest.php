<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\SupervisorStatus;
use App\Enums\SupervisorTaskStatus;
use App\Models\Server;
use App\Models\ServerSupervisorTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerSupervisorControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test guest cannot access supervisor page.
     */
    public function test_guest_cannot_access_supervisor_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test authenticated user can access their server supervisor page.
     */
    public function test_user_can_access_their_server_supervisor_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(200);
    }

    /**
     * Test user cannot access other users server supervisor page.
     */
    public function test_user_cannot_access_other_users_server_supervisor_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test supervisor page renders correct Inertia component.
     */
    public function test_supervisor_page_renders_correct_inertia_component(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/supervisor')
            ->has('server')
        );
    }

    /**
     * Test supervisor page includes server data.
     */
    public function test_supervisor_page_includes_server_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Production Server',
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/supervisor')
            ->where('server.id', $server->id)
            ->where('server.vanity_name', 'Production Server')
            ->where('server.supervisor_status', 'active')
        );
    }

    /**
     * Test supervisor page includes supervisor tasks.
     */
    public function test_supervisor_page_includes_supervisor_tasks(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Queue Worker',
            'command' => 'php artisan queue:work',
            'status' => SupervisorTaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/supervisor')
            ->has('server.supervisorTasks', 1)
            ->has('server.supervisorTasks.0', fn ($task) => $task
                ->where('name', 'Queue Worker')
                ->where('command', 'php artisan queue:work')
                ->where('status', 'active')
                ->etc()
            )
        );
    }

    /**
     * Test supervisor page shows multiple tasks.
     */
    public function test_supervisor_page_shows_multiple_tasks(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        ServerSupervisorTask::factory()->count(3)->create([
            'server_id' => $server->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/supervisor')
            ->has('server.supervisorTasks', 3)
        );
    }

    /**
     * Test supervisor page shows empty state when no tasks exist.
     */
    public function test_supervisor_page_shows_empty_state_when_no_tasks_exist(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/supervisor')
            ->has('server.supervisorTasks', 0)
        );
    }

    /**
     * Test supervisor page orders tasks by id.
     */
    public function test_supervisor_page_orders_tasks_by_id(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        $task1 = ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'First Task',
        ]);

        $task2 = ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Second Task',
        ]);

        $task3 = ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Third Task',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/supervisor')
            ->has('server.supervisorTasks', 3)
            ->where('server.supervisorTasks.0.id', $task1->id)
            ->where('server.supervisorTasks.1.id', $task2->id)
            ->where('server.supervisorTasks.2.id', $task3->id)
        );
    }

    /**
     * Test user can install supervisor successfully.
     */
    public function test_user_can_install_supervisor_successfully(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => null,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/install");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Supervisor installation started');

        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'supervisor_status' => SupervisorStatus::Installing->value,
        ]);
    }

    /**
     * Test supervisor installation prevents duplicate installation.
     */
    public function test_supervisor_installation_prevents_duplicate_installation(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/install");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test supervisor installation prevents installation during installing.
     */
    public function test_supervisor_installation_prevents_installation_during_installing(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Installing,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/install");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user can uninstall supervisor successfully.
     */
    public function test_user_can_uninstall_supervisor_successfully(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/uninstall");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Supervisor uninstallation started');

        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'supervisor_status' => SupervisorStatus::Uninstalling->value,
        ]);
    }

    /**
     * Test supervisor uninstall fails when not active.
     */
    public function test_supervisor_uninstall_fails_when_not_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => null,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/uninstall");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user cannot install supervisor on other users server.
     */
    public function test_user_cannot_install_supervisor_on_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $otherUser->id,
            'supervisor_status' => null,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/install");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user can create supervisor task successfully.
     */
    public function test_user_can_create_supervisor_task_successfully(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks", [
                'name' => 'Queue Worker',
                'command' => 'php artisan queue:work',
                'working_directory' => '/home/brokeforge',
                'processes' => 2,
                'user' => 'brokeforge',
                'auto_restart' => true,
                'autorestart_unexpected' => true,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Supervisor task created and installation started');

        $this->assertDatabaseHas('server_supervisor_tasks', [
            'server_id' => $server->id,
            'name' => 'Queue Worker',
            'command' => 'php artisan queue:work',
            'working_directory' => '/home/brokeforge',
            'processes' => 2,
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
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks", [
                'command' => 'php artisan queue:work',
                'working_directory' => '/home/brokeforge',
                'processes' => 1,
                'user' => 'brokeforge',
                'auto_restart' => true,
                'autorestart_unexpected' => true,
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
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks", [
                'name' => 'Queue Worker',
                'working_directory' => '/home/brokeforge',
                'processes' => 1,
                'user' => 'brokeforge',
                'auto_restart' => true,
                'autorestart_unexpected' => true,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['command']);
    }

    /**
     * Test task creation validates required working directory field.
     */
    public function test_task_creation_validates_required_working_directory(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks", [
                'name' => 'Queue Worker',
                'command' => 'php artisan queue:work',
                'processes' => 1,
                'user' => 'brokeforge',
                'auto_restart' => true,
                'autorestart_unexpected' => true,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['working_directory']);
    }

    /**
     * Test task creation validates required processes field.
     */
    public function test_task_creation_validates_required_processes(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks", [
                'name' => 'Queue Worker',
                'command' => 'php artisan queue:work',
                'working_directory' => '/home/brokeforge',
                'user' => 'brokeforge',
                'auto_restart' => true,
                'autorestart_unexpected' => true,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['processes']);
    }

    /**
     * Test task creation validates processes minimum value.
     */
    public function test_task_creation_validates_processes_minimum_value(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks", [
                'name' => 'Queue Worker',
                'command' => 'php artisan queue:work',
                'working_directory' => '/home/brokeforge',
                'processes' => 0,
                'user' => 'brokeforge',
                'auto_restart' => true,
                'autorestart_unexpected' => true,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['processes']);
    }

    /**
     * Test task creation validates processes maximum value.
     */
    public function test_task_creation_validates_processes_maximum_value(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks", [
                'name' => 'Queue Worker',
                'command' => 'php artisan queue:work',
                'working_directory' => '/home/brokeforge',
                'processes' => 21,
                'user' => 'brokeforge',
                'auto_restart' => true,
                'autorestart_unexpected' => true,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['processes']);
    }

    /**
     * Test task creation validates required user field.
     */
    public function test_task_creation_validates_required_user(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks", [
                'name' => 'Queue Worker',
                'command' => 'php artisan queue:work',
                'working_directory' => '/home/brokeforge',
                'processes' => 1,
                'auto_restart' => true,
                'autorestart_unexpected' => true,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['user']);
    }

    /**
     * Test task creation fails when supervisor not active.
     */
    public function test_task_creation_fails_when_supervisor_not_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => null,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks", [
                'name' => 'Queue Worker',
                'command' => 'php artisan queue:work',
                'working_directory' => '/home/brokeforge',
                'processes' => 1,
                'user' => 'brokeforge',
                'auto_restart' => true,
                'autorestart_unexpected' => true,
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
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks", [
                'name' => 'Unauthorized Task',
                'command' => 'php artisan test',
                'working_directory' => '/home/brokeforge',
                'processes' => 1,
                'user' => 'brokeforge',
                'auto_restart' => true,
                'autorestart_unexpected' => true,
            ]);

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user can delete supervisor task successfully.
     */
    public function test_user_can_delete_supervisor_task_successfully(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);
        $task = ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
            'status' => SupervisorTaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/supervisor/tasks/{$task->id}");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Supervisor task removal started');

        $this->assertDatabaseHas('server_supervisor_tasks', [
            'id' => $task->id,
            'status' => 'removing',
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
            'supervisor_status' => SupervisorStatus::Active,
        ]);
        $task = ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/supervisor/tasks/{$task->id}");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user can toggle task from active to inactive.
     */
    public function test_user_can_toggle_task_from_active_to_inactive(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');
    }

    /**
     * Test user can toggle task from inactive to active.
     */
    public function test_user_can_toggle_task_from_inactive_to_active(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');
    }

    /**
     * Test user cannot toggle task on other users server.
     */
    public function test_user_cannot_toggle_task_on_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $otherUser->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);
        $task = ServerSupervisorTask::factory()->active()->create([
            'server_id' => $server->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks/{$task->id}/toggle");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user can restart active task.
     */
    public function test_user_can_restart_active_task(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');
    }

    /**
     * Test user cannot restart task on other users server.
     */
    public function test_user_cannot_restart_task_on_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $otherUser->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);
        $task = ServerSupervisorTask::factory()->active()->create([
            'server_id' => $server->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks/{$task->id}/restart");

        // Assert
        $response->assertStatus(403);
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
            'supervisor_status' => SupervisorStatus::Active,
        ]);
        $task = ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
            'status' => SupervisorTaskStatus::Failed,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks/{$task->id}/retry");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Task retry started');

        $this->assertDatabaseHas('server_supervisor_tasks', [
            'id' => $task->id,
            'status' => SupervisorTaskStatus::Pending->value,
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
            'supervisor_status' => SupervisorStatus::Active,
        ]);
        $task = ServerSupervisorTask::factory()->active()->create([
            'server_id' => $server->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks/{$task->id}/retry");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('error', 'Only failed tasks can be retried');
    }

    /**
     * Test user cannot retry task on other users server.
     */
    public function test_user_cannot_retry_task_on_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $otherUser->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);
        $task = ServerSupervisorTask::factory()->failed()->create([
            'server_id' => $server->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks/{$task->id}/retry");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test supervisor page shows tasks with different statuses.
     */
    public function test_supervisor_page_shows_tasks_with_different_statuses(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        $activeTask = ServerSupervisorTask::factory()->active()->create([
            'server_id' => $server->id,
            'name' => 'Active Task',
        ]);

        $inactiveTask = ServerSupervisorTask::factory()->inactive()->create([
            'server_id' => $server->id,
            'name' => 'Inactive Task',
        ]);

        $failedTask = ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Failed Task',
            'status' => SupervisorTaskStatus::Failed,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/supervisor')
            ->has('server.supervisorTasks', 3)
            ->has('server.supervisorTasks.0', fn ($task) => $task
                ->where('id', $activeTask->id)
                ->where('status', 'active')
                ->etc()
            )
            ->has('server.supervisorTasks.1', fn ($task) => $task
                ->where('id', $inactiveTask->id)
                ->where('status', 'inactive')
                ->etc()
            )
            ->has('server.supervisorTasks.2', fn ($task) => $task
                ->where('id', $failedTask->id)
                ->where('status', 'failed')
                ->etc()
            )
        );
    }
}
