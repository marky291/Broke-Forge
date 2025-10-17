<?php

namespace Tests\Feature\Inertia\Servers;

use App\Enums\SupervisorStatus;
use App\Enums\SupervisorTaskStatus;
use App\Models\Server;
use App\Models\ServerSupervisorTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test supervisor page renders correct Inertia component.
     */
    public function test_supervisor_page_renders_correct_component(): void
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
        );
    }

    /**
     * Test supervisor page provides server data in Inertia props.
     */
    public function test_supervisor_page_provides_server_data_in_props(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Production Server',
            'public_ip' => '192.168.1.100',
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/supervisor')
            ->has('server')
            ->where('server.id', $server->id)
            ->where('server.vanity_name', 'Production Server')
            ->where('server.public_ip', '192.168.1.100')
            ->where('server.supervisor_status', 'active')
        );
    }

    /**
     * Test supervisor page includes supervisor tasks array in props.
     */
    public function test_supervisor_page_includes_supervisor_tasks_in_props(): void
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
            'working_directory' => '/home/brokeforge',
            'processes' => 2,
            'user' => 'brokeforge',
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
            ->where('server.supervisorTasks.0.name', 'Queue Worker')
            ->where('server.supervisorTasks.0.command', 'php artisan queue:work')
            ->where('server.supervisorTasks.0.working_directory', '/home/brokeforge')
            ->where('server.supervisorTasks.0.processes', 2)
            ->where('server.supervisorTasks.0.user', 'brokeforge')
            ->where('server.supervisorTasks.0.status', 'active')
        );
    }

    /**
     * Test supervisor page shows correct task structure.
     */
    public function test_supervisor_page_shows_correct_task_structure(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        $task = ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Test Task',
            'auto_restart' => true,
            'autorestart_unexpected' => false,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/supervisor')
            ->has('server.supervisorTasks.0', fn ($taskProp) => $taskProp
                ->has('id')
                ->has('server_id')
                ->has('name')
                ->has('command')
                ->has('working_directory')
                ->has('processes')
                ->has('user')
                ->has('auto_restart')
                ->has('autorestart_unexpected')
                ->has('status')
                ->has('created_at')
                ->has('updated_at')
                ->etc()
            )
        );
    }

    /**
     * Test supervisor page shows empty state when no tasks exist.
     */
    public function test_supervisor_page_shows_empty_state(): void
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
     * Test supervisor page orders tasks by id ascending.
     */
    public function test_supervisor_page_orders_tasks_by_id_ascending(): void
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

        // Assert - tasks should be ordered by id (ascending)
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/supervisor')
            ->has('server.supervisorTasks', 3)
            ->where('server.supervisorTasks.0.id', $task1->id)
            ->where('server.supervisorTasks.0.name', 'First Task')
            ->where('server.supervisorTasks.1.id', $task2->id)
            ->where('server.supervisorTasks.1.name', 'Second Task')
            ->where('server.supervisorTasks.2.id', $task3->id)
            ->where('server.supervisorTasks.2.name', 'Third Task')
        );
    }

    /**
     * Test supervisor page displays pending task status.
     */
    public function test_supervisor_page_displays_pending_task_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Pending Task',
            'status' => SupervisorTaskStatus::Pending,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/supervisor')
            ->where('server.supervisorTasks.0.status', 'pending')
        );
    }

    /**
     * Test supervisor page displays installing task status.
     */
    public function test_supervisor_page_displays_installing_task_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Installing Task',
            'status' => SupervisorTaskStatus::Installing,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/supervisor')
            ->where('server.supervisorTasks.0.status', 'installing')
        );
    }

    /**
     * Test supervisor page displays active task status.
     */
    public function test_supervisor_page_displays_active_task_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        ServerSupervisorTask::factory()->active()->create([
            'server_id' => $server->id,
            'name' => 'Active Task',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/supervisor')
            ->where('server.supervisorTasks.0.status', 'active')
        );
    }

    /**
     * Test supervisor page displays inactive task status.
     */
    public function test_supervisor_page_displays_inactive_task_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        ServerSupervisorTask::factory()->inactive()->create([
            'server_id' => $server->id,
            'name' => 'Inactive Task',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/supervisor')
            ->where('server.supervisorTasks.0.status', 'inactive')
        );
    }

    /**
     * Test supervisor page displays failed task status.
     */
    public function test_supervisor_page_displays_failed_task_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        ServerSupervisorTask::factory()->failed()->create([
            'server_id' => $server->id,
            'name' => 'Failed Task',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/supervisor')
            ->where('server.supervisorTasks.0.status', 'failed')
        );
    }

    /**
     * Test supervisor page displays removing task status.
     */
    public function test_supervisor_page_displays_removing_task_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Removing Task',
            'status' => 'removing',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/supervisor')
            ->where('server.supervisorTasks.0.status', 'removing')
        );
    }

    /**
     * Test supervisor page displays multiple tasks with different statuses.
     */
    public function test_supervisor_page_displays_multiple_tasks_with_different_statuses(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        ServerSupervisorTask::factory()->active()->create([
            'server_id' => $server->id,
            'name' => 'Active Task',
        ]);

        ServerSupervisorTask::factory()->inactive()->create([
            'server_id' => $server->id,
            'name' => 'Inactive Task',
        ]);

        ServerSupervisorTask::factory()->failed()->create([
            'server_id' => $server->id,
            'name' => 'Failed Task',
        ]);

        ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Pending Task',
            'status' => SupervisorTaskStatus::Pending,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/supervisor')
            ->has('server.supervisorTasks', 4)
            ->where('server.supervisorTasks.0.status', 'active')
            ->where('server.supervisorTasks.1.status', 'inactive')
            ->where('server.supervisorTasks.2.status', 'failed')
            ->where('server.supervisorTasks.3.status', 'pending')
        );
    }

    /**
     * Test supervisor page displays supervisor not installed status.
     */
    public function test_supervisor_page_displays_supervisor_not_installed_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => null,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/supervisor')
            ->where('server.supervisor_status', null)
            ->has('server.supervisorTasks', 0)
        );
    }

    /**
     * Test supervisor page displays installing status.
     */
    public function test_supervisor_page_displays_installing_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Installing,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/supervisor')
            ->where('server.supervisor_status', 'installing')
        );
    }

    /**
     * Test supervisor page displays uninstalling status.
     */
    public function test_supervisor_page_displays_uninstalling_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Uninstalling,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/supervisor')
            ->where('server.supervisor_status', 'uninstalling')
        );
    }

    /**
     * Test supervisor page displays task with all configuration options.
     */
    public function test_supervisor_page_displays_task_with_all_configuration_options(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Complex Task',
            'command' => 'php artisan queue:work redis --sleep=3 --tries=3',
            'working_directory' => '/var/www/html',
            'processes' => 5,
            'user' => 'www-data',
            'auto_restart' => false,
            'autorestart_unexpected' => true,
            'stdout_logfile' => '/var/log/supervisor/task.log',
            'stderr_logfile' => '/var/log/supervisor/task-error.log',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/supervisor')
            ->has('server.supervisorTasks', 1)
            ->where('server.supervisorTasks.0.name', 'Complex Task')
            ->where('server.supervisorTasks.0.command', 'php artisan queue:work redis --sleep=3 --tries=3')
            ->where('server.supervisorTasks.0.working_directory', '/var/www/html')
            ->where('server.supervisorTasks.0.processes', 5)
            ->where('server.supervisorTasks.0.user', 'www-data')
            ->where('server.supervisorTasks.0.auto_restart', false)
            ->where('server.supervisorTasks.0.autorestart_unexpected', true)
            ->where('server.supervisorTasks.0.stdout_logfile', '/var/log/supervisor/task.log')
            ->where('server.supervisorTasks.0.stderr_logfile', '/var/log/supervisor/task-error.log')
        );
    }

    /**
     * Test supervisor page displays tasks with various process counts.
     */
    public function test_supervisor_page_displays_tasks_with_various_process_counts(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Single Process Task',
            'processes' => 1,
        ]);

        ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Multiple Process Task',
            'processes' => 10,
        ]);

        ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Max Process Task',
            'processes' => 20,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/supervisor')
            ->has('server.supervisorTasks', 3)
            ->where('server.supervisorTasks.0.processes', 1)
            ->where('server.supervisorTasks.1.processes', 10)
            ->where('server.supervisorTasks.2.processes', 20)
        );
    }

    /**
     * Test supervisor page includes timestamps for tasks.
     */
    public function test_supervisor_page_includes_timestamps_for_tasks(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Test Task',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/supervisor')
            ->has('server.supervisorTasks.0.created_at')
            ->has('server.supervisorTasks.0.updated_at')
        );
    }

    /**
     * Test unauthorized user cannot access supervisor page.
     */
    public function test_unauthorized_user_cannot_access_supervisor_page(): void
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
     * Test guest user redirected to login.
     */
    public function test_guest_user_redirected_to_login(): void
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
}
