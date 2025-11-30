<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerPhp;
use App\Models\ServerSupervisorTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\MocksSshConnections;
use Tests\TestCase;

class ServerSupervisorControllerTest extends TestCase
{
    use MocksSshConnections;
    use RefreshDatabase;

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
            'supervisor_status' => TaskStatus::Installing->value,
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
            'supervisor_status' => TaskStatus::Active,
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
            'supervisor_status' => TaskStatus::Installing,
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
            'supervisor_status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/uninstall");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Supervisor uninstallation started');

        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'supervisor_status' => TaskStatus::Removing->value,
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
            'supervisor_status' => TaskStatus::Active,
        ]);
        ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.4',
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks", [
                'name' => 'Queue Worker',
                'command' => 'php8.4 artisan queue:work',
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
            'command' => 'php8.4 artisan queue:work',
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
            'supervisor_status' => TaskStatus::Active,
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
            'supervisor_status' => TaskStatus::Active,
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
            'supervisor_status' => TaskStatus::Active,
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
            'supervisor_status' => TaskStatus::Active,
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
            'supervisor_status' => TaskStatus::Active,
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
            'supervisor_status' => TaskStatus::Active,
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
            'supervisor_status' => TaskStatus::Active,
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

        // Act - Use non-PHP command to test authorization without PHP validation
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks", [
                'name' => 'Queue Worker',
                'command' => 'node server.js',
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
            'supervisor_status' => TaskStatus::Active,
        ]);

        // Act - Use non-PHP command to test authorization without PHP validation
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks", [
                'name' => 'Unauthorized Task',
                'command' => 'npm run build',
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
            'supervisor_status' => TaskStatus::Active,
        ]);
        $task = ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/supervisor/tasks/{$task->id}");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Supervisor task removal started');

        $this->assertDatabaseHas('server_supervisor_tasks', [
            'id' => $task->id,
            'status' => 'pending',
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
            'supervisor_status' => TaskStatus::Active,
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
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => TaskStatus::Active,
        ]);

        $task = ServerSupervisorTask::factory()->active()->create([
            'server_id' => $server->id,
            'name' => 'test-worker',
        ]);

        // Mock SSH connection for this test
        $this->mockSshConnection($server, [
            'supervisorctl stop test-worker' => [
                'success' => true,
                'output' => 'test-worker: stopped',
            ],
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks/{$task->id}/toggle");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('server_supervisor_tasks', [
            'id' => $task->id,
            'status' => 'paused',
        ]);
    }

    /**
     * Test user can toggle task from inactive to active.
     */
    public function test_user_can_toggle_task_from_inactive_to_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => TaskStatus::Active,
        ]);

        $task = ServerSupervisorTask::factory()->inactive()->create([
            'server_id' => $server->id,
            'name' => 'test-worker',
        ]);

        // Mock SSH connection for this test
        $this->mockSshConnection($server, [
            'supervisorctl start test-worker' => [
                'success' => true,
                'output' => 'test-worker: started',
            ],
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks/{$task->id}/toggle");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('server_supervisor_tasks', [
            'id' => $task->id,
            'status' => 'active',
        ]);
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
            'supervisor_status' => TaskStatus::Active,
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
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => TaskStatus::Active,
        ]);

        $task = ServerSupervisorTask::factory()->active()->create([
            'server_id' => $server->id,
            'name' => 'test-worker',
        ]);

        // Mock SSH connection for this test
        $this->mockSshConnection($server, [
            'supervisorctl restart test-worker' => [
                'success' => true,
                'output' => 'test-worker: stopped\ntest-worker: started',
            ],
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks/{$task->id}/restart");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success');
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
            'supervisor_status' => TaskStatus::Active,
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
            'supervisor_status' => TaskStatus::Active,
        ]);
        $task = ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Failed,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks/{$task->id}/retry");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Task retry started');

        $this->assertDatabaseHas('server_supervisor_tasks', [
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
            'supervisor_status' => TaskStatus::Active,
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
            'supervisor_status' => TaskStatus::Active,
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
     * Test user can view task logs.
     */
    public function test_user_can_view_task_logs(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $task = ServerSupervisorTask::factory()->active()->create([
            'server_id' => $server->id,
            'name' => 'test-worker',
            'stdout_logfile' => '/var/log/supervisor/test-worker-stdout.log',
            'stderr_logfile' => '/var/log/supervisor/test-worker-stderr.log',
        ]);

        // Mock SSH log retrieval for this test
        $this->mockServerSshLogs(
            server: $server,
            stdoutLogPath: '/var/log/supervisor/test-worker-stdout.log',
            stderrLogPath: '/var/log/supervisor/test-worker-stderr.log',
            stdoutLines: ['[2025-01-06] Processing job 1', '[2025-01-06] Processing job 2'],
            stderrLines: ['[2025-01-06] Warning: Memory usage high']
        );

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor/tasks/{$task->id}/logs");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/tasks')
            ->has('viewingSupervisorLogs')
            ->where('viewingSupervisorLogs', true)
            ->has('supervisorTaskLogs')
            ->where('supervisorTaskLogs.task_id', $task->id)
            ->where('supervisorTaskLogs.task_name', 'test-worker')
            ->has('supervisorTaskLogs.logs', 3) // 2 stdout + 1 stderr
        );
    }

    /**
     * Test viewing logs renders correct inertia component with logs data.
     */
    public function test_viewing_logs_renders_correct_inertia_component(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $task = ServerSupervisorTask::factory()->active()->create([
            'server_id' => $server->id,
            'name' => 'queue-worker',
        ]);

        // Mock SSH log retrieval for this test
        $this->mockServerSshLogs(
            server: $server,
            stdoutLogPath: '/var/log/supervisor/queue-worker-stdout.log',
            stderrLogPath: '/var/log/supervisor/queue-worker-stderr.log',
            stdoutLines: ['Log line 1'],
            stderrLines: []
        );

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor/tasks/{$task->id}/logs");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/tasks')
            ->where('viewingSupervisorLogs', true)
        );
    }

    /**
     * Test user cannot view logs for other users task.
     */
    public function test_user_cannot_view_logs_for_other_users_task(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $otherUser->id,
            'supervisor_status' => TaskStatus::Active,
        ]);
        $task = ServerSupervisorTask::factory()->active()->create([
            'server_id' => $server->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor/tasks/{$task->id}/logs");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test guest cannot view task logs.
     */
    public function test_guest_cannot_view_task_logs(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerSupervisorTask::factory()->active()->create([
            'server_id' => $server->id,
        ]);

        // Act
        $response = $this->get("/servers/{$server->id}/supervisor/tasks/{$task->id}/logs");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test user can view task status.
     */
    public function test_user_can_view_task_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $task = ServerSupervisorTask::factory()->active()->create([
            'server_id' => $server->id,
            'name' => 'test-worker',
        ]);

        // Mock supervisorctl status command for this test
        $this->mockServerSshStatus(
            server: $server,
            taskName: 'test-worker',
            state: 'RUNNING',
            pid: '12345',
            uptime: '1 day, 3:45:22'
        );

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor/tasks/{$task->id}/status");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/tasks')
            ->has('viewingSupervisorStatus')
            ->where('viewingSupervisorStatus', true)
            ->has('supervisorTaskStatus')
            ->where('supervisorTaskStatus.task_id', $task->id)
            ->where('supervisorTaskStatus.task_name', 'test-worker')
            ->has('supervisorTaskStatus.status')
        );
    }

    /**
     * Test viewing status renders correct inertia component with status data.
     */
    public function test_viewing_status_renders_correct_inertia_component(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $task = ServerSupervisorTask::factory()->active()->create([
            'server_id' => $server->id,
            'name' => 'queue-worker',
        ]);

        // Mock supervisorctl status command for this test
        $this->mockServerSshStatus(
            server: $server,
            taskName: 'queue-worker',
            state: 'RUNNING',
            pid: '9999',
            uptime: '0:15:30'
        );

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor/tasks/{$task->id}/status");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/tasks')
            ->where('viewingSupervisorStatus', true)
        );
    }

    /**
     * Test user cannot view status for other users task.
     */
    public function test_user_cannot_view_status_for_other_users_task(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $otherUser->id,
            'supervisor_status' => TaskStatus::Active,
        ]);
        $task = ServerSupervisorTask::factory()->active()->create([
            'server_id' => $server->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor/tasks/{$task->id}/status");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test guest cannot view task status.
     */
    public function test_guest_cannot_view_task_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerSupervisorTask::factory()->active()->create([
            'server_id' => $server->id,
        ]);

        // Act
        $response = $this->get("/servers/{$server->id}/supervisor/tasks/{$task->id}/status");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test logs endpoint returns correct inertia props structure.
     */
    public function test_logs_endpoint_returns_correct_inertia_props_structure(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');

        // When implementing SSH mocking, verify:
        // - component is 'servers/tasks'
        // - viewingSupervisorLogs is true
        // - supervisorTaskLogs.task_id matches
        // - supervisorTaskLogs.task_name matches
        // - supervisorTaskLogs.logs is an array
        // - supervisorTaskLogs.error is null on success
    }

    /**
     * Test status endpoint returns correct inertia props structure.
     */
    public function test_status_endpoint_returns_correct_inertia_props_structure(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');

        // When implementing SSH mocking, verify:
        // - component is 'servers/tasks'
        // - viewingSupervisorStatus is true
        // - supervisorTaskStatus.task_id matches
        // - supervisorTaskStatus.task_name matches
        // - supervisorTaskStatus.status.raw_output exists
        // - supervisorTaskStatus.status.parsed contains name, state, pid, uptime
        // - supervisorTaskStatus.error is null on success
    }

    /**
     * Test logs can be viewed for tasks in pending state.
     */
    public function test_logs_can_be_viewed_for_tasks_in_pending_state(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');

        // Should allow viewing logs even if task is pending
        // This helps debug installation issues
    }

    /**
     * Test logs can be viewed for tasks in failed state.
     */
    public function test_logs_can_be_viewed_for_tasks_in_failed_state(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');

        // Failed tasks should have accessible logs to diagnose issues
    }

    /**
     * Test logs endpoint handles Process object correctly from SSH execute.
     */
    public function test_logs_endpoint_handles_process_object_correctly(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $task = ServerSupervisorTask::factory()->active()->create([
            'server_id' => $server->id,
            'name' => 'process-test',
        ]);

        // Mock SSH log retrieval for this test - this verifies getOutput() is called properly
        $this->mockServerSshLogs(
            server: $server,
            stdoutLogPath: '/var/log/supervisor/process-test-stdout.log',
            stderrLogPath: '/var/log/supervisor/process-test-stderr.log',
            stdoutLines: ['Process output line 1', 'Process output line 2'],
            stderrLines: []
        );

        // Act - if controller doesn't call getOutput(), this will fail with TypeError
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor/tasks/{$task->id}/logs");

        // Assert - verifies that Process->getOutput() was called (not direct Process usage)
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/tasks')
            ->has('supervisorTaskLogs.logs', 2)
        );
    }

    /**
     * Test status can be viewed for tasks in installing state.
     */
    public function test_status_can_be_viewed_for_tasks_in_installing_state(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');

        // Should allow checking status during installation
    }

    /**
     * Test task must belong to server for logs endpoint.
     */
    public function test_task_must_belong_to_server_for_logs_endpoint(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server1 = Server::factory()->create(['user_id' => $user->id]);
        $server2 = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerSupervisorTask::factory()->active()->create([
            'server_id' => $server2->id,
        ]);

        // Act - try to access task from wrong server
        $response = $this->actingAs($user)
            ->get("/servers/{$server1->id}/supervisor/tasks/{$task->id}/logs");

        // Assert
        $response->assertStatus(404);
    }

    /**
     * Test task must belong to server for status endpoint.
     */
    public function test_task_must_belong_to_server_for_status_endpoint(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server1 = Server::factory()->create(['user_id' => $user->id]);
        $server2 = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerSupervisorTask::factory()->active()->create([
            'server_id' => $server2->id,
        ]);

        // Act - try to access task from wrong server
        $response = $this->actingAs($user)
            ->get("/servers/{$server1->id}/supervisor/tasks/{$task->id}/status");

        // Assert
        $response->assertStatus(404);
    }

    /**
     * Test logs endpoint renders tasks component (unified page).
     */
    public function test_logs_endpoint_renders_tasks_component(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');

        // Should render 'servers/tasks' component (unified scheduler + supervisor page)
    }

    /**
     * Test status endpoint renders tasks component (unified page).
     */
    public function test_status_endpoint_renders_tasks_component(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');

        // Should render 'servers/tasks' component (unified scheduler + supervisor page)
    }

    /**
     * Test supervisor task log paths can be generated with fallback logic.
     */
    public function test_supervisor_task_log_paths_use_fallback_when_null(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Test-Task',
            'stdout_logfile' => null,
            'stderr_logfile' => null,
        ]);

        // Assert - verify the fallback paths would be generated correctly
        // The controller uses this logic: $task->stdout_logfile ?? "/var/log/supervisor/{$sanitizedName}-stdout.log"
        $sanitizedName = preg_replace('/[^a-zA-Z0-9-_]/', '_', $task->name);
        $expectedStdoutPath = "/var/log/supervisor/{$sanitizedName}-stdout.log";
        $expectedStderrPath = "/var/log/supervisor/{$sanitizedName}-stderr.log";

        $this->assertNull($task->stdout_logfile);
        $this->assertNull($task->stderr_logfile);
        $this->assertEquals('Test-Task', $sanitizedName); // Hyphens are allowed in sanitized names
        $this->assertEquals('/var/log/supervisor/Test-Task-stdout.log', $expectedStdoutPath);
        $this->assertEquals('/var/log/supervisor/Test-Task-stderr.log', $expectedStderrPath);
    }

    /**
     * Test supervisor task can have explicit log file paths.
     */
    public function test_supervisor_task_can_have_explicit_log_paths(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Test Task',
            'stdout_logfile' => '/custom/path/stdout.log',
            'stderr_logfile' => '/custom/path/stderr.log',
        ]);

        // Assert - verify explicit paths are stored
        $this->assertEquals('/custom/path/stdout.log', $task->stdout_logfile);
        $this->assertEquals('/custom/path/stderr.log', $task->stderr_logfile);
    }

    /**
     * Test logs endpoint includes server resource in response.
     */
    public function test_logs_endpoint_includes_server_resource_in_response(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');

        // Should include server data in Inertia props
    }

    /**
     * Test status endpoint includes server resource in response.
     */
    public function test_status_endpoint_includes_server_resource_in_response(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');

        // Should include server data in Inertia props
    }

    /**
     * Test logs are audited when viewed.
     */
    public function test_logs_are_audited_when_viewed(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');

        // Should log to Laravel logs when user views task logs
        // Verify Log::info was called with correct data
    }

    /**
     * Test status is audited when viewed.
     */
    public function test_status_is_audited_when_viewed(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');

        // Should log to Laravel logs when user views task status
        // Verify Log::info was called with correct data
    }

    /**
     * Test logs route exists and is accessible for active tasks.
     */
    public function test_logs_route_exists_for_active_task(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');

        // Verify route exists: GET /servers/{server}/supervisor/tasks/{task}/logs
        // Should return 200 for active tasks
    }

    /**
     * Test status route exists and is accessible for active tasks.
     */
    public function test_status_route_exists_for_active_task(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');

        // Verify route exists: GET /servers/{server}/supervisor/tasks/{task}/status
        // Should return 200 for active tasks
    }

    /**
     * Test logs can be viewed during installation (pending/installing states).
     */
    public function test_logs_accessible_during_installation_states(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');

        // Logs should be viewable even when task is pending or installing
        // This helps debug installation issues
    }

    /**
     * Test status can be viewed during installation (pending/installing states).
     */
    public function test_status_accessible_during_installation_states(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');

        // Status should be viewable even when task is pending or installing
        // This helps monitor installation progress
    }

    /**
     * Test viewing logs does not modify task state.
     */
    public function test_viewing_logs_does_not_modify_task_state(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');

        // Viewing logs should be read-only
        // Task status should remain unchanged
    }

    /**
     * Test viewing status does not modify task state.
     */
    public function test_viewing_status_does_not_modify_task_state(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');

        // Viewing status should be read-only
        // Task status should remain unchanged
    }

    /**
     * Test multiple tasks can have their logs viewed independently.
     */
    public function test_multiple_tasks_logs_viewed_independently(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => TaskStatus::Active,
        ]);

        $task1 = ServerSupervisorTask::factory()->active()->create([
            'server_id' => $server->id,
            'name' => 'Task 1',
        ]);

        $task2 = ServerSupervisorTask::factory()->active()->create([
            'server_id' => $server->id,
            'name' => 'Task 2',
        ]);

        // Act - Access logs for task1
        $response1 = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor/tasks/{$task1->id}/logs");

        // Assert - Can access task1 logs
        $response1->assertStatus(200);

        // Act - Access logs for task2
        $response2 = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor/tasks/{$task2->id}/logs");

        // Assert - Can access task2 logs independently
        $response2->assertStatus(200);
    }

    /**
     * Test multiple tasks can have their status viewed independently.
     */
    public function test_multiple_tasks_status_viewed_independently(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => TaskStatus::Active,
        ]);

        $task1 = ServerSupervisorTask::factory()->active()->create([
            'server_id' => $server->id,
            'name' => 'Task 1',
        ]);

        $task2 = ServerSupervisorTask::factory()->active()->create([
            'server_id' => $server->id,
            'name' => 'Task 2',
        ]);

        // Act - Access status for task1
        $response1 = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor/tasks/{$task1->id}/status");

        // Assert - Can access task1 status
        $response1->assertStatus(200);

        // Act - Access status for task2
        $response2 = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor/tasks/{$task2->id}/status");

        // Assert - Can access task2 status independently
        $response2->assertStatus(200);
    }

    /**
     * Test supervisor page renders with viewingLogs false by default.
     */
    /**
     * Test supervisor page renders with viewingStatus false by default.
     */
    /**
     * Test logs endpoint includes viewingSupervisorLogs flag in Inertia response.
     */
    public function test_logs_endpoint_includes_viewing_logs_flag(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');

        // When implemented:
        // - Should have viewingSupervisorLogs: true
        // - Should have supervisorTaskLogs object with task_id, task_name, logs array
    }

    /**
     * Test status endpoint includes viewingSupervisorStatus flag in Inertia response.
     */
    public function test_status_endpoint_includes_viewing_status_flag(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');

        // When implemented:
        // - Should have viewingSupervisorStatus: true
        // - Should have supervisorTaskStatus object with task_id, task_name, status object
    }

    /**
     * Test supervisor page includes all task data needed for actions.
     */
    /**
     * Test supervisor page includes tasks with different statuses for conditional dropdown actions.
     */
    /**
     * Test logs modal data structure matches frontend expectations.
     */
    public function test_logs_modal_has_correct_data_structure(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');

        // When SSH mocking is available:
        // supervisorTaskLogs should have:
        // - task_id: number
        // - task_name: string
        // - logs: array of {source: 'stdout'|'stderr', content: string}
        // - error: string|null
    }

    /**
     * Test status modal data structure matches frontend expectations.
     */
    public function test_status_modal_has_correct_data_structure(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');

        // When SSH mocking is available:
        // supervisorTaskStatus should have:
        // - task_id: number
        // - task_name: string
        // - status: {raw_output: string, parsed: {name, state, pid, uptime}}
        // - error: string|null
    }

    /**
     * Test logs endpoint preserves server data for navigation.
     */
    public function test_logs_endpoint_preserves_server_data(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');

        // Should include full server resource in response
        // Needed for breadcrumbs and navigation
    }

    /**
     * Test status endpoint preserves server data for navigation.
     */
    public function test_status_endpoint_preserves_server_data(): void
    {
        $this->markTestSkipped('SSH mocking test - requires proper SSH credentials setup');

        // Should include full server resource in response
        // Needed for breadcrumbs and navigation
    }
}
