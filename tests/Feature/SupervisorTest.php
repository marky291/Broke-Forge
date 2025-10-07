<?php

namespace Tests\Feature;

use App\Enums\SupervisorStatus;
use App\Models\Server;
use App\Models\ServerSupervisorTask;
use App\Models\User;
use App\Packages\Services\Supervisor\SupervisorInstallerJob;
use App\Packages\Services\Supervisor\SupervisorRemoverJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Ssh\Ssh;
use Tests\TestCase;

class SupervisorTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_index_page_loads_successfully(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('servers/supervisor')
                ->has('server')
                ->has('tasks')
            );
    }

    public function test_it_dispatches_supervisor_installer_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/install")
            ->assertRedirect();

        Queue::assertPushed(SupervisorInstallerJob::class, fn ($job) => $job->server->is($server));

        $server->refresh();
        $this->assertEquals(SupervisorStatus::Installing, $server->supervisor_status);
    }

    public function test_it_dispatches_supervisor_remover_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/uninstall")
            ->assertRedirect();

        Queue::assertPushed(SupervisorRemoverJob::class, fn ($job) => $job->server->is($server));

        $server->refresh();
        $this->assertEquals(SupervisorStatus::Uninstalling, $server->supervisor_status);
    }

    public function test_it_creates_supervisor_task(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        // Mock SSH to prevent actual SSH connection
        $this->mock(Ssh::class);

        $taskData = [
            'name' => 'queue-worker',
            'command' => 'php artisan queue:work',
            'working_directory' => '/home/brokeforge',
            'processes' => 2,
            'user' => 'brokeforge',
            'auto_restart' => true,
            'autorestart_unexpected' => true,
        ];

        $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks", $taskData)
            ->assertRedirect();

        $this->assertDatabaseHas('server_supervisor_tasks', [
            'server_id' => $server->id,
            'name' => 'queue-worker',
            'command' => 'php artisan queue:work',
            'working_directory' => '/home/brokeforge',
            'processes' => 2,
            'user' => 'brokeforge',
        ]);
    }

    public function test_it_validates_supervisor_task_creation(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        // Missing required fields
        $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks", [])
            ->assertSessionHasErrors(['name', 'command', 'working_directory', 'processes', 'user']);
    }

    public function test_it_deletes_supervisor_task(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        $task = ServerSupervisorTask::factory()->create([
            'server_id' => $server->id,
        ]);

        // Mock SSH to prevent actual SSH connection
        $this->mock(Ssh::class);

        $this->actingAs($user)
            ->delete("/servers/{$server->id}/supervisor/tasks/{$task->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('server_supervisor_tasks', [
            'id' => $task->id,
        ]);
    }

    public function test_it_toggles_supervisor_task_status(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        $task = ServerSupervisorTask::factory()->active()->create([
            'server_id' => $server->id,
        ]);

        // Mock SSH to prevent actual SSH connection
        $this->mock(Ssh::class);

        // Toggle from active to inactive
        $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks/{$task->id}/toggle")
            ->assertRedirect();

        $task->refresh();
        $this->assertEquals('inactive', $task->status);

        // Toggle back to active
        $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks/{$task->id}/toggle")
            ->assertRedirect();

        $task->refresh();
        $this->assertEquals('active', $task->status);
    }

    public function test_it_restarts_supervisor_task(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        $task = ServerSupervisorTask::factory()->active()->create([
            'server_id' => $server->id,
        ]);

        // Mock SSH to prevent actual SSH connection
        $this->mock(Ssh::class);

        $this->actingAs($user)
            ->post("/servers/{$server->id}/supervisor/tasks/{$task->id}/restart")
            ->assertRedirect();
    }

    public function test_server_has_supervisor_tasks_relationship(): void
    {
        $server = Server::factory()->create();

        $task1 = ServerSupervisorTask::factory()->create(['server_id' => $server->id]);
        $task2 = ServerSupervisorTask::factory()->create(['server_id' => $server->id]);

        $this->assertCount(2, $server->supervisorTasks);
        $this->assertTrue($server->supervisorTasks->contains($task1));
        $this->assertTrue($server->supervisorTasks->contains($task2));
    }

    public function test_supervisor_status_helper_methods(): void
    {
        $server = Server::factory()->create([
            'supervisor_status' => SupervisorStatus::Active,
        ]);

        $this->assertTrue($server->supervisorIsActive());
        $this->assertFalse($server->supervisorIsInstalling());
        $this->assertFalse($server->supervisorIsFailed());

        $server->update(['supervisor_status' => SupervisorStatus::Installing]);
        $this->assertFalse($server->supervisorIsActive());
        $this->assertTrue($server->supervisorIsInstalling());

        $server->update(['supervisor_status' => SupervisorStatus::Failed]);
        $this->assertFalse($server->supervisorIsActive());
        $this->assertTrue($server->supervisorIsFailed());
    }

    public function test_supervisor_task_status_helper_methods(): void
    {
        $task = ServerSupervisorTask::factory()->active()->create();
        $this->assertTrue($task->isActive());
        $this->assertFalse($task->isInactive());
        $this->assertFalse($task->isFailed());

        $task->update(['status' => 'inactive']);
        $this->assertFalse($task->isActive());
        $this->assertTrue($task->isInactive());
        $this->assertFalse($task->isFailed());

        $task->update(['status' => 'failed']);
        $this->assertFalse($task->isActive());
        $this->assertFalse($task->isInactive());
        $this->assertTrue($task->isFailed());
    }
}
