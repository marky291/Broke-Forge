<?php

namespace Tests\Feature\Inertia\Servers\Scheduler\Tasks;

use App\Models\Server;
use App\Models\ServerScheduledTask;
use App\Models\ServerScheduledTaskRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test task activity page renders correct Inertia component.
     */
    public function test_task_activity_page_renders_correct_component(): void
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
        );
    }

    /**
     * Test task activity page provides server data in Inertia props.
     */
    public function test_task_activity_page_provides_server_data_in_props(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Production Server',
            'public_ip' => '192.168.1.100',
        ]);
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task->id}/activity");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler-task-activity')
            ->has('server')
            ->where('server.id', $server->id)
            ->where('server.vanity_name', 'Production Server')
            ->where('server.public_ip', '192.168.1.100')
        );
    }

    /**
     * Test task activity page provides task data in Inertia props.
     */
    public function test_task_activity_page_provides_task_data_in_props(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerScheduledTask::factory()->create([
            'server_id' => $server->id,
            'name' => 'Daily Backup',
            'command' => 'php /home/brokeforge/artisan backup:run',
            'frequency' => 'daily',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task->id}/activity");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler-task-activity')
            ->has('task')
            ->where('task.id', $task->id)
            ->where('task.name', 'Daily Backup')
            ->where('task.command', 'php /home/brokeforge/artisan backup:run')
            ->where('task.frequency', 'daily')
        );
    }

    /**
     * Test task activity page includes task runs in props.
     */
    public function test_task_activity_page_includes_task_runs_in_props(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        ServerScheduledTaskRun::factory()->successful()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
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
            ->where('runs.data.0.exit_code', 0)
            ->where('runs.data.0.duration_ms', 1500)
            ->where('runs.data.0.was_successful', true)
        );
    }

    /**
     * Test task activity page shows correct task run structure.
     */
    public function test_task_activity_page_shows_correct_task_run_structure(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        ServerScheduledTaskRun::factory()->create([
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
            ->has('runs.data.0', fn ($run) => $run
                ->has('id')
                ->has('server_id')
                ->has('server_scheduled_task_id')
                ->has('started_at')
                ->has('completed_at')
                ->has('exit_code')
                ->has('output')
                ->has('error_output')
                ->has('duration_ms')
                ->has('was_successful')
                ->has('created_at')
                ->has('updated_at')
                ->etc()
            )
        );
    }

    /**
     * Test task activity page shows empty state when no runs exist.
     */
    public function test_task_activity_page_shows_empty_state(): void
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
     * Test task activity page displays successful task runs.
     */
    public function test_task_activity_page_displays_successful_task_runs(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        ServerScheduledTaskRun::factory()->successful()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            'exit_code' => 0,
            'output' => 'Task completed successfully',
            'error_output' => null,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task->id}/activity");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler-task-activity')
            ->where('runs.data.0.exit_code', 0)
            ->where('runs.data.0.was_successful', true)
            ->where('runs.data.0.output', 'Task completed successfully')
            ->where('runs.data.0.error_output', null)
        );
    }

    /**
     * Test task activity page displays failed task runs.
     */
    public function test_task_activity_page_displays_failed_task_runs(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        ServerScheduledTaskRun::factory()->failed()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            'exit_code' => 1,
            'output' => null,
            'error_output' => 'Command failed with exit code 1',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task->id}/activity");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler-task-activity')
            ->where('runs.data.0.exit_code', 1)
            ->where('runs.data.0.was_successful', false)
            ->where('runs.data.0.output', null)
            ->where('runs.data.0.error_output', 'Command failed with exit code 1')
        );
    }

    /**
     * Test task activity page orders runs by started_at descending.
     */
    public function test_task_activity_page_orders_runs_by_started_at_descending(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        $run1 = ServerScheduledTaskRun::factory()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            'started_at' => now()->subHours(3),
        ]);

        $run2 = ServerScheduledTaskRun::factory()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            'started_at' => now()->subHours(2),
        ]);

        $run3 = ServerScheduledTaskRun::factory()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            'started_at' => now()->subHours(1),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task->id}/activity");

        // Assert - runs should be ordered by started_at descending (newest first)
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler-task-activity')
            ->has('runs.data', 3)
            ->where('runs.data.0.id', $run3->id)
            ->where('runs.data.1.id', $run2->id)
            ->where('runs.data.2.id', $run1->id)
        );
    }

    /**
     * Test task activity page displays multiple task runs with different outcomes.
     */
    public function test_task_activity_page_displays_multiple_task_runs_with_different_outcomes(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        ServerScheduledTaskRun::factory()->successful()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            'started_at' => now()->subHours(3),
        ]);

        ServerScheduledTaskRun::factory()->failed()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            'started_at' => now()->subHours(2),
        ]);

        ServerScheduledTaskRun::factory()->successful()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            'started_at' => now()->subHours(1),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task->id}/activity");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler-task-activity')
            ->has('runs.data', 3)
            ->where('runs.data.0.was_successful', true)
            ->where('runs.data.1.was_successful', false)
            ->where('runs.data.2.was_successful', true)
        );
    }

    /**
     * Test task activity page includes pagination metadata.
     */
    public function test_task_activity_page_includes_pagination_metadata(): void
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
            ->where('runs.meta.total', 5)
            ->where('runs.meta.current_page', 1)
        );
    }

    /**
     * Test task activity page paginates task runs.
     */
    public function test_task_activity_page_paginates_task_runs(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        // Create 25 task runs (more than the 20 per page limit)
        ServerScheduledTaskRun::factory()->count(25)->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
        ]);

        // Act - page 1
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task->id}/activity");

        // Assert - page 1 should have 20 items
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler-task-activity')
            ->has('runs.data', 20)
            ->where('runs.meta.total', 25)
            ->where('runs.meta.current_page', 1)
            ->where('runs.meta.last_page', 2)
        );

        // Act - page 2
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task->id}/activity?page=2");

        // Assert - page 2 should have 5 items
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler-task-activity')
            ->has('runs.data', 5)
            ->where('runs.meta.current_page', 2)
        );
    }

    /**
     * Test task activity page displays task run with output.
     */
    public function test_task_activity_page_displays_task_run_with_output(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        ServerScheduledTaskRun::factory()->successful()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            'output' => 'Backup completed. Files: 1234, Size: 5.6GB',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task->id}/activity");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler-task-activity')
            ->where('runs.data.0.output', 'Backup completed. Files: 1234, Size: 5.6GB')
        );
    }

    /**
     * Test task activity page displays task run with error output.
     */
    public function test_task_activity_page_displays_task_run_with_error_output(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        ServerScheduledTaskRun::factory()->failed()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            'error_output' => 'Error: Insufficient disk space',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task->id}/activity");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler-task-activity')
            ->where('runs.data.0.error_output', 'Error: Insufficient disk space')
        );
    }

    /**
     * Test task activity page displays task run with various durations.
     */
    public function test_task_activity_page_displays_task_run_with_various_durations(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        ServerScheduledTaskRun::factory()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            'started_at' => now()->subMinutes(5),
            'duration_ms' => 500,
        ]);

        ServerScheduledTaskRun::factory()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            'started_at' => now()->subMinutes(3),
            'duration_ms' => 5000,
        ]);

        ServerScheduledTaskRun::factory()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            'started_at' => now()->subMinutes(1),
            'duration_ms' => 120000,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task->id}/activity");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler-task-activity')
            ->has('runs.data', 3)
            ->where('runs.data.0.duration_ms', 120000)
            ->where('runs.data.1.duration_ms', 5000)
            ->where('runs.data.2.duration_ms', 500)
        );
    }

    /**
     * Test task activity page displays task run with various exit codes.
     */
    public function test_task_activity_page_displays_task_run_with_various_exit_codes(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        ServerScheduledTaskRun::factory()->successful()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            'started_at' => now()->subHours(3),
            'exit_code' => 0,
        ]);

        ServerScheduledTaskRun::factory()->failed()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            'started_at' => now()->subHours(2),
            'exit_code' => 1,
        ]);

        ServerScheduledTaskRun::factory()->failed()->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            'started_at' => now()->subHours(1),
            'exit_code' => 127,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task->id}/activity");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler-task-activity')
            ->has('runs.data', 3)
            ->where('runs.data.0.exit_code', 127)
            ->where('runs.data.1.exit_code', 1)
            ->where('runs.data.2.exit_code', 0)
        );
    }

    /**
     * Test task activity page includes timestamps for task runs.
     */
    public function test_task_activity_page_includes_timestamps_for_task_runs(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $task = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        ServerScheduledTaskRun::factory()->create([
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
            ->has('runs.data.0.started_at')
            ->has('runs.data.0.completed_at')
            ->has('runs.data.0.created_at')
            ->has('runs.data.0.updated_at')
        );
    }

    /**
     * Test task activity page only shows runs for the specific task.
     */
    public function test_task_activity_page_only_shows_runs_for_specific_task(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $task1 = ServerScheduledTask::factory()->create(['server_id' => $server->id]);
        $task2 = ServerScheduledTask::factory()->create(['server_id' => $server->id]);

        // Create runs for task1
        ServerScheduledTaskRun::factory()->count(3)->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task1->id,
        ]);

        // Create runs for task2
        ServerScheduledTaskRun::factory()->count(2)->create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task2->id,
        ]);

        // Act - view task1 activity
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler/tasks/{$task1->id}/activity");

        // Assert - should only show task1 runs
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler-task-activity')
            ->has('runs.data', 3)
            ->where('runs.data.0.server_scheduled_task_id', $task1->id)
            ->where('runs.data.1.server_scheduled_task_id', $task1->id)
            ->where('runs.data.2.server_scheduled_task_id', $task1->id)
        );
    }

    /**
     * Test unauthorized user cannot access task activity page.
     */
    public function test_unauthorized_user_cannot_access_task_activity_page(): void
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
     * Test guest user redirected to login.
     */
    public function test_guest_user_redirected_to_login(): void
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
}
