<?php

namespace Tests\Feature\Inertia\Servers;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerScheduledTask;
use App\Models\ServerSupervisorTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TasksTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test tasks page renders correct Inertia component.
     */
    public function test_tasks_page_renders_correct_component(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/tasks");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/tasks')
        );
    }

    /**
     * Test tasks page provides server data in Inertia props.
     */
    public function test_tasks_page_provides_server_data_in_props(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Tasks Server',
            'public_ip' => '192.168.1.100',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/tasks");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/tasks')
            ->has('server')
            ->where('server.id', $server->id)
            ->where('server.vanity_name', 'Tasks Server')
        );
    }

    /**
     * Test tasks page displays scheduled tasks.
     */
    public function test_tasks_page_displays_scheduled_tasks(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => TaskStatus::Active,
        ]);

        ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Backup Database',
            'command' => 'php artisan backup:run',
            'frequency' => 'daily',
        ]);

        ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Clear Cache',
            'command' => 'php artisan cache:clear',
            'frequency' => 'hourly',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/tasks");

        // Assert - tasks are ordered by latest() so Clear Cache (created second) comes second
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/tasks')
            ->has('server.scheduledTasks', 2)
            ->where('server.scheduledTasks.0.name', 'Backup Database')
            ->where('server.scheduledTasks.1.name', 'Clear Cache')
        );
    }

    /**
     * Test tasks page displays supervisor tasks (background workers).
     */
    public function test_tasks_page_displays_supervisor_tasks(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => TaskStatus::Active,
        ]);

        ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Queue Worker',
            'command' => 'php artisan queue:work',
            'processes' => 3,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/tasks");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/tasks')
            ->has('server.supervisorTasks', 1)
            ->where('server.supervisorTasks.0.name', 'Queue Worker')
        );
    }

    /**
     * Test tasks page displays both scheduled and supervisor tasks together.
     */
    public function test_tasks_page_displays_both_task_types_together(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => TaskStatus::Active,
            'supervisor_status' => TaskStatus::Active,
        ]);

        // Create scheduled task
        ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Backup Database',
        ]);

        // Create supervisor task
        ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Queue Worker',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/tasks");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/tasks')
            ->has('server.scheduledTasks', 1)
            ->has('server.supervisorTasks', 1)
            ->where('server.scheduledTasks.0.name', 'Backup Database')
            ->where('server.supervisorTasks.0.name', 'Queue Worker')
        );
    }

    /**
     * Test tasks page shows empty state when no tasks exist.
     */
    public function test_tasks_page_shows_empty_state_when_no_tasks(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/tasks");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/tasks')
            ->has('server.scheduledTasks', 0)
            ->has('server.supervisorTasks', 0)
        );
    }

    /**
     * Test tasks page includes scheduler installation status.
     */
    public function test_tasks_page_includes_scheduler_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => TaskStatus::Installing,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/tasks");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/tasks')
            ->where('server.scheduler_status', TaskStatus::Installing->value)
        );
    }

    /**
     * Test tasks page includes supervisor installation status.
     */
    public function test_tasks_page_includes_supervisor_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/tasks");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/tasks')
            ->where('server.supervisor_status', TaskStatus::Active->value)
        );
    }

    /**
     * Test tasks page includes task status information.
     */
    public function test_tasks_page_includes_task_statuses(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => TaskStatus::Active,
            'supervisor_status' => TaskStatus::Active,
        ]);

        ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Active,
        ]);

        ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/tasks");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/tasks')
            ->has('server.scheduledTasks', 1)
            ->has('server.supervisorTasks', 1)
            ->where('server.scheduledTasks.0.status', TaskStatus::Active->value)
            ->where('server.supervisorTasks.0.status', TaskStatus::Active->value)
        );
    }

    /**
     * Test guest cannot access tasks page.
     */
    public function test_guest_cannot_access_tasks_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->get("/servers/{$server->id}/tasks");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test user cannot access other users server tasks page.
     */
    public function test_user_cannot_access_other_users_server_tasks_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/tasks");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test tasks page displays correct data structure for scheduled tasks.
     */
    public function test_tasks_page_scheduled_tasks_data_structure(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => TaskStatus::Active,
        ]);

        ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Test Task',
            'command' => 'php artisan test',
            'frequency' => 'daily',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/tasks");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/tasks')
            ->has('server.scheduledTasks.0', fn ($task) => $task
                ->has('id')
                ->has('name')
                ->has('command')
                ->has('frequency')
                ->has('status')
                ->etc()
            )
        );
    }

    /**
     * Test tasks page displays correct data structure for supervisor tasks.
     */
    public function test_tasks_page_supervisor_tasks_data_structure(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => TaskStatus::Active,
        ]);

        ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Worker Task',
            'command' => 'php artisan queue:work',
            'processes' => 2,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/tasks");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/tasks')
            ->has('server.supervisorTasks.0', fn ($task) => $task
                ->has('id')
                ->has('name')
                ->has('command')
                ->has('processes')
                ->has('status')
                ->etc()
            )
        );
    }

    /**
     * Test tasks page shows scheduler not installed when status is null.
     */
    public function test_tasks_page_shows_scheduler_not_installed(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => null,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/tasks");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/tasks')
            ->where('server.scheduler_status', null)
        );
    }

    /**
     * Test tasks page shows supervisor not installed when status is null.
     */
    public function test_tasks_page_shows_supervisor_not_installed(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => null,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/tasks");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/tasks')
            ->where('server.supervisor_status', null)
        );
    }
}
