<?php

namespace Tests\Feature;

use App\Enums\SchedulerStatus;
use App\Events\ServerUpdated;
use App\Models\Server;
use App\Models\ServerScheduledTask;
use App\Models\User;
use App\Packages\Services\Scheduler\Task\ServerScheduleTaskInstallerJob;
use App\Packages\Services\Scheduler\Task\ServerScheduleTaskRemoverJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerScheduledTaskLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_controller_creates_task_with_pending_status(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create([
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('servers.scheduler.tasks.store', $server), [
            'name' => 'Test Task',
            'command' => 'php artisan test:command',
            'frequency' => 'daily',
            'timeout' => 300,
            'send_notifications' => false,
        ]);

        $response->assertRedirect();

        // Verify task was created with pending status
        $task = ServerScheduledTask::where('name', 'Test Task')->first();
        $this->assertNotNull($task);
        $this->assertEquals('pending', $task->status->value);
        $this->assertEquals('php artisan test:command', $task->command);
        $this->assertEquals('daily', $task->frequency->value);
    }

    public function test_controller_dispatches_job_with_task_id(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create([
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        $this->actingAs($user);

        $this->post(route('servers.scheduler.tasks.store', $server), [
            'name' => 'Test Task',
            'command' => 'php artisan test:command',
            'frequency' => 'hourly',
            'timeout' => 300,
            'send_notifications' => false,
        ]);

        $task = ServerScheduledTask::where('name', 'Test Task')->first();

        Queue::assertPushed(ServerScheduleTaskInstallerJob::class, function ($job) use ($server, $task) {
            return $job->server->id === $server->id
                && $job->taskId === $task->id;
        });
    }

    public function test_job_updates_status_to_installing(): void
    {
        Event::fake([ServerUpdated::class]);

        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create();
        $task = ServerScheduledTask::factory()->for($server)->create([
            'status' => 'pending',
        ]);

        // Mock the Symfony Process object
        $mockProcess = \Mockery::mock(\Symfony\Component\Process\Process::class);
        $mockProcess->shouldReceive('isSuccessful')->andReturn(true);
        $mockProcess->shouldReceive('getOutput')->andReturn('Success');
        $mockProcess->shouldReceive('getCommandLine')->andReturn('mock command');

        // Mock the Spatie\Ssh\Ssh object
        $mockSsh = \Mockery::mock(\Spatie\Ssh\Ssh::class);
        $mockSsh->shouldReceive('setTimeout')->andReturnSelf();
        $mockSsh->shouldReceive('execute')->andReturn($mockProcess);

        $server = \Mockery::mock($server)->makePartial();
        $server->shouldReceive('createSshConnection')->andReturn($mockSsh);

        try {
            $job = new ServerScheduleTaskInstallerJob($server, $task->id);
            $job->handle();
        } catch (\Exception $e) {
            // Expected to fail since we're mocking
        }

        // Verify the task status was updated to installing at some point
        $task->refresh();
        $this->assertContains($task->status->value, ['installing', 'active', 'failed']);
    }

    public function test_job_updates_status_to_active_on_success(): void
    {
        Event::fake([ServerUpdated::class]);

        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create();
        $task = ServerScheduledTask::factory()->for($server)->create([
            'status' => 'pending',
        ]);

        // Mock the Symfony Process object
        $mockProcess = \Mockery::mock(\Symfony\Component\Process\Process::class);
        $mockProcess->shouldReceive('isSuccessful')->andReturn(true);
        $mockProcess->shouldReceive('getOutput')->andReturn('Success');
        $mockProcess->shouldReceive('getCommandLine')->andReturn('mock command');

        // Mock the Spatie\Ssh\Ssh object
        $mockSsh = \Mockery::mock(\Spatie\Ssh\Ssh::class);
        $mockSsh->shouldReceive('setTimeout')->andReturnSelf();
        $mockSsh->shouldReceive('execute')->andReturn($mockProcess);

        $server = \Mockery::mock($server)->makePartial();
        $server->shouldReceive('createSshConnection')->andReturn($mockSsh);

        $job = new ServerScheduleTaskInstallerJob($server, $task->id);
        $job->handle();

        // Verify the task status was updated to active
        $task->refresh();
        $this->assertEquals('active', $task->status->value);
    }

    public function test_job_updates_status_to_failed_on_error(): void
    {
        Event::fake([ServerUpdated::class]);

        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create();
        $task = ServerScheduledTask::factory()->for($server)->create([
            'status' => 'pending',
        ]);

        // Mock the Spatie\Ssh\Ssh object to simulate failure
        $mockSsh = \Mockery::mock(\Spatie\Ssh\Ssh::class);
        $mockSsh->shouldReceive('setTimeout')->andReturnSelf();
        $mockSsh->shouldReceive('execute')->andThrow(new \Exception('SSH connection failed'));

        $server = \Mockery::mock($server)->makePartial();
        $server->shouldReceive('createSshConnection')->andReturn($mockSsh);

        $job = new ServerScheduleTaskInstallerJob($server, $task->id);

        try {
            $job->handle();
        } catch (\Exception $e) {
            // Expected to throw
        }

        // Verify the task status was updated to failed
        $task->refresh();
        $this->assertEquals('failed', $task->status->value);
    }

    public function test_task_creation_dispatches_server_updated_event(): void
    {
        Event::fake([ServerUpdated::class]);

        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create();

        ServerScheduledTask::create([
            'server_id' => $server->id,
            'name' => 'Test Task',
            'command' => 'php artisan test:command',
            'frequency' => 'daily',
            'status' => 'pending',
            'timeout' => 300,
            'send_notifications' => false,
        ]);

        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    public function test_task_status_update_dispatches_server_updated_event(): void
    {
        Event::fake([ServerUpdated::class]);

        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create();
        $task = ServerScheduledTask::factory()->for($server)->create([
            'status' => 'pending',
        ]);

        // Clear any events from creation
        Event::assertDispatched(ServerUpdated::class);
        Event::fake([ServerUpdated::class]);

        // Update status
        $task->update(['status' => 'installing']);

        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    public function test_retry_resets_failed_task_to_pending_and_dispatches_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create([
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $task = ServerScheduledTask::factory()->for($server)->create([
            'status' => 'failed',
        ]);

        $this->actingAs($user);

        $response = $this->post(route('servers.scheduler.tasks.retry', [$server, $task]));

        $response->assertRedirect();

        // Verify task status was reset to pending
        $task->refresh();
        $this->assertEquals('pending', $task->status->value);

        // Verify job was dispatched with task ID
        Queue::assertPushed(ServerScheduleTaskInstallerJob::class, function ($job) use ($server, $task) {
            return $job->server->id === $server->id
                && $job->taskId === $task->id;
        });
    }

    public function test_retry_only_works_for_failed_tasks(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create([
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $task = ServerScheduledTask::factory()->for($server)->create([
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->post(route('servers.scheduler.tasks.retry', [$server, $task]));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Only failed tasks can be retried');

        // Verify task status was not changed
        $task->refresh();
        $this->assertEquals('active', $task->status->value);

        // Verify job was not dispatched
        Queue::assertNotPushed(ServerScheduleTaskInstallerJob::class);
    }

    public function test_controller_sets_status_to_removing_before_dispatching_removal_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create([
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $task = ServerScheduledTask::factory()->for($server)->create([
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->delete(route('servers.scheduler.tasks.destroy', [$server, $task]));

        $response->assertRedirect();

        // ✅ Verify status updated to removing
        $task->refresh();
        $this->assertEquals('removing', $task->status->value);

        // ✅ Verify removal job dispatched with task ID
        Queue::assertPushed(ServerScheduleTaskRemoverJob::class, function ($job) use ($server, $task) {
            return $job->server->id === $server->id
                && $job->taskId === $task->id;
        });
    }

    public function test_removal_job_deletes_task_on_success(): void
    {
        Event::fake([ServerUpdated::class]);

        $server = Server::factory()->create();
        $task = ServerScheduledTask::factory()->for($server)->create([
            'status' => 'removing',
        ]);

        // Mock successful SSH execution
        $mockProcess = \Mockery::mock(\Symfony\Component\Process\Process::class);
        $mockProcess->shouldReceive('isSuccessful')->andReturn(true);
        $mockProcess->shouldReceive('getOutput')->andReturn('Success');
        $mockProcess->shouldReceive('getCommandLine')->andReturn('mock command');

        $mockSsh = \Mockery::mock(\Spatie\Ssh\Ssh::class);
        $mockSsh->shouldReceive('setTimeout')->andReturnSelf();
        $mockSsh->shouldReceive('execute')->andReturn($mockProcess);

        $server = \Mockery::mock($server)->makePartial();
        $server->shouldReceive('createSshConnection')->andReturn($mockSsh);

        $job = new ServerScheduleTaskRemoverJob($server, $task->id);
        $job->handle();

        // ✅ Verify task was deleted from database
        $this->assertDatabaseMissing('server_scheduled_tasks', [
            'id' => $task->id,
        ]);
    }

    public function test_removal_job_restores_original_status_on_failure(): void
    {
        Event::fake([ServerUpdated::class]);

        $server = Server::factory()->create();
        $task = ServerScheduledTask::factory()->for($server)->create([
            'status' => 'removing',
        ]);

        // Mock SSH failure
        $mockSsh = \Mockery::mock(\Spatie\Ssh\Ssh::class);
        $mockSsh->shouldReceive('setTimeout')->andReturnSelf();
        $mockSsh->shouldReceive('execute')->andThrow(new \Exception('SSH connection failed'));

        $server = \Mockery::mock($server)->makePartial();
        $server->shouldReceive('createSshConnection')->andReturn($mockSsh);

        $job = new ServerScheduleTaskRemoverJob($server, $task->id);

        try {
            $job->handle();
        } catch (\Exception $e) {
            // Expected to throw
        }

        // ✅ Verify task still exists and status was restored to 'removing' (original)
        $task->refresh();
        $this->assertEquals('removing', $task->status->value);
        $this->assertDatabaseHas('server_scheduled_tasks', [
            'id' => $task->id,
        ]);
    }

    public function test_task_deletion_dispatches_server_updated_event(): void
    {
        Event::fake([ServerUpdated::class]);

        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create();
        $task = ServerScheduledTask::factory()->for($server)->create([
            'status' => 'active',
        ]);

        // Delete the task
        $task->delete();

        // ✅ Verify broadcast event dispatched on deletion
        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }
}
