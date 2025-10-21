<?php

namespace App\Http\Controllers;

use App\Enums\SchedulerStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Concerns\PreparesSiteData;
use App\Http\Requests\StoreScheduledTaskRequest;
use App\Http\Requests\StoreTaskRunRequest;
use App\Http\Requests\UpdateScheduledTaskRequest;
use App\Http\Resources\ServerResource;
use App\Http\Resources\ServerScheduledTaskRunResource;
use App\Models\Server;
use App\Models\ServerScheduledTask;
use App\Models\ServerScheduledTaskRun;
use App\Packages\Services\Scheduler\ServerSchedulerInstallerJob;
use App\Packages\Services\Scheduler\ServerSchedulerRemoverJob;
use App\Packages\Services\Scheduler\Task\ServerScheduleTaskInstallerJob;
use App\Packages\Services\Scheduler\Task\ServerScheduleTaskRemoverJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ServerSchedulerController extends Controller
{
    use PreparesSiteData;

    /**
     * Display unified tasks page with scheduler and supervisor
     */
    public function tasks(Server $server): Response
    {
        $this->authorize('view', $server);

        return Inertia::render('servers/tasks', [
            'server' => new ServerResource($server),
        ]);
    }

    /**
     * Display scheduler page with installation status and tasks
     */
    public function index(Server $server): Response
    {
        $this->authorize('view', $server);

        return Inertia::render('servers/scheduler', [
            'server' => new ServerResource($server),
        ]);
    }

    /**
     * Install scheduler framework on the server
     */
    public function install(Server $server): RedirectResponse
    {
        $this->authorize('update', $server);

        // Prevent installation if already installing/active
        if ($server->scheduler_status && in_array($server->scheduler_status->value, ['installing', 'active'])) {
            abort(403, 'Scheduler is already installed or being installed on this server.');
        }

        // Audit log
        Log::info('Scheduler installation initiated', [
            'user_id' => auth()->id(),
            'server_id' => $server->id,
            'ip_address' => request()->ip(),
        ]);

        // Update scheduler status immediately for UI feedback
        $server->update([
            'scheduler_status' => SchedulerStatus::Installing,
        ]);

        // Dispatch scheduler framework installation job
        ServerSchedulerInstallerJob::dispatch($server);

        return redirect()
            ->route('servers.scheduler', $server)
            ->with('success', 'Scheduler installation started');
    }

    /**
     * Uninstall scheduler framework from the server
     */
    public function uninstall(Server $server): RedirectResponse
    {
        $this->authorize('update', $server);

        // Can only uninstall if active
        if (! $server->scheduler_status || $server->scheduler_status->value !== 'active') {
            abort(403, 'Scheduler must be active to uninstall.');
        }

        // Audit log
        Log::warning('Scheduler uninstallation initiated', [
            'user_id' => auth()->id(),
            'server_id' => $server->id,
            'task_count' => $server->scheduledTasks()->count(),
            'ip_address' => request()->ip(),
        ]);

        // Update status to 'uninstalling' immediately for UI feedback
        $server->update([
            'scheduler_status' => SchedulerStatus::Uninstalling,
        ]);

        // Remove all tasks first
        foreach ($server->scheduledTasks as $task) {
            ServerScheduleTaskRemoverJob::dispatch($server, $task->id);
        }

        // Dispatch scheduler framework removal job
        ServerSchedulerRemoverJob::dispatch($server);

        return redirect()
            ->route('servers.scheduler', $server)
            ->with('success', 'Scheduler uninstallation started');
    }

    /**
     * Create a new scheduled task
     */
    public function storeTask(StoreScheduledTaskRequest $request, Server $server): RedirectResponse
    {
        $this->authorize('update', $server);

        // Create the task with 'pending' status (default from migration)
        $task = $server->scheduledTasks()->create($request->validated());

        // Audit log
        Log::info('Scheduled task created', [
            'user_id' => auth()->id(),
            'server_id' => $server->id,
            'task_id' => $task->id,
            'task_name' => $task->name,
            'command' => $task->command,
            'frequency' => $task->frequency->value,
            'ip_address' => request()->ip(),
        ]);

        // Dispatch task installation job with task ID
        ServerScheduleTaskInstallerJob::dispatch($server, $task->id);

        return redirect()
            ->route('servers.scheduler', $server)
            ->with('success', 'Scheduled task created and installation started');
    }

    /**
     * Update an existing scheduled task
     */
    public function updateTask(UpdateScheduledTaskRequest $request, Server $server, ServerScheduledTask $scheduledTask): RedirectResponse
    {
        $this->authorize('update', $server);

        // Capture old values for audit
        $oldCommand = $scheduledTask->command;

        // Update the task and reset status to 'pending'
        $scheduledTask->update(array_merge($request->validated(), ['status' => 'pending']));

        // Audit log
        Log::info('Scheduled task updated', [
            'user_id' => auth()->id(),
            'server_id' => $server->id,
            'task_id' => $scheduledTask->id,
            'task_name' => $scheduledTask->name,
            'old_command' => $oldCommand,
            'new_command' => $scheduledTask->command,
            'ip_address' => request()->ip(),
        ]);

        // Re-install the task with updated configuration (pass task ID)
        ServerScheduleTaskRemoverJob::dispatch($server, $scheduledTask->id);
        ServerScheduleTaskInstallerJob::dispatch($server, $scheduledTask->id);

        return redirect()
            ->route('servers.scheduler', $server)
            ->with('success', 'Scheduled task updated and reinstallation started');
    }

    /**
     * Delete a scheduled task
     */
    public function destroyTask(Server $server, ServerScheduledTask $scheduledTask): RedirectResponse
    {
        $this->authorize('update', $server);

        // Audit log
        Log::warning('Scheduled task deletion initiated', [
            'user_id' => auth()->id(),
            'server_id' => $server->id,
            'task_id' => $scheduledTask->id,
            'task_name' => $scheduledTask->name,
            'command' => $scheduledTask->command,
            'ip_address' => request()->ip(),
        ]);

        // ✅ UPDATE status to 'removing' (broadcasts automatically via model event)
        $scheduledTask->update(['status' => 'removing']);

        // ✅ THEN dispatch job with task ID
        ServerScheduleTaskRemoverJob::dispatch($server, $scheduledTask->id);

        return redirect()
            ->route('servers.scheduler', $server)
            ->with('success', 'Scheduled task removal started');
    }

    /**
     * Toggle task status (active/paused)
     */
    public function toggleTask(Server $server, ServerScheduledTask $scheduledTask): RedirectResponse
    {
        // Toggle status
        $newStatus = $scheduledTask->status === TaskStatus::Active
            ? TaskStatus::Paused
            : TaskStatus::Pending; // Use Pending instead of Active to trigger reinstallation

        $scheduledTask->update(['status' => $newStatus]);

        // Remove and reinstall to update cron state
        if ($newStatus === TaskStatus::Paused) {
            ServerScheduleTaskRemoverJob::dispatch($server, $scheduledTask->id);
        } else {
            // Pass task ID instead of task model
            ServerScheduleTaskInstallerJob::dispatch($server, $scheduledTask->id);
        }

        return redirect()
            ->route('servers.scheduler', $server)
            ->with('success', "Task {$newStatus->value}");
    }

    /**
     * Retry a failed scheduled task
     */
    public function retryTask(Server $server, ServerScheduledTask $scheduledTask): RedirectResponse
    {
        $this->authorize('update', $server);

        // Only allow retry for failed tasks
        if ($scheduledTask->status !== TaskStatus::Failed) {
            return redirect()
                ->route('servers.scheduler', $server)
                ->with('error', 'Only failed tasks can be retried');
        }

        // Audit log
        Log::info('Scheduled task retry initiated', [
            'user_id' => auth()->id(),
            'server_id' => $server->id,
            'task_id' => $scheduledTask->id,
            'task_name' => $scheduledTask->name,
            'command' => $scheduledTask->command,
            'ip_address' => request()->ip(),
        ]);

        // Reset status to 'pending' to trigger reinstallation
        $scheduledTask->update(['status' => 'pending']);

        // Dispatch task installation job with task ID
        ServerScheduleTaskInstallerJob::dispatch($server, $scheduledTask->id);

        return redirect()
            ->route('servers.scheduler', $server)
            ->with('success', 'Task retry started');
    }

    /**
     * Manually run a scheduled task
     */
    public function runTask(Server $server, ServerScheduledTask $scheduledTask): RedirectResponse
    {
        $this->authorize('update', $server);

        // Scheduler must be active
        if (! $server->schedulerIsActive()) {
            abort(403, 'Scheduler must be active to run tasks.');
        }

        // Audit log
        Log::info('Scheduled task manually triggered', [
            'user_id' => auth()->id(),
            'server_id' => $server->id,
            'task_id' => $scheduledTask->id,
            'task_name' => $scheduledTask->name,
            'command' => $scheduledTask->command,
            'ip_address' => request()->ip(),
        ]);

        // Execute the task wrapper script on the remote server
        try {
            $ssh = $server->ssh('root');

            // Run the task wrapper script (which handles heartbeat and logging)
            $result = $ssh->execute("/opt/brokeforge/scheduler/tasks/{$scheduledTask->id}.sh");

            if ($result->getExitCode() === 0) {
                return redirect()
                    ->route('servers.scheduler', $server)
                    ->with('success', 'Task executed successfully');
            } else {
                return redirect()
                    ->route('servers.scheduler', $server)
                    ->with('error', 'Task execution failed with exit code: '.$result->getExitCode());
            }
        } catch (\Exception $e) {
            Log::error('Manual task execution failed', [
                'server_id' => $server->id,
                'task_id' => $scheduledTask->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('servers.scheduler', $server)
                ->with('error', 'Failed to execute task: '.$e->getMessage());
        }
    }

    /**
     * Store task run data from remote server (API endpoint - heartbeat)
     */
    public function storeTaskRun(StoreTaskRunRequest $request, Server $server): JsonResponse
    {
        $taskId = $request->validated('task_id');
        $task = ServerScheduledTask::where('server_id', $server->id)
            ->where('id', $taskId)
            ->firstOrFail();

        // Store the task run
        $taskRun = ServerScheduledTaskRun::create([
            'server_id' => $server->id,
            'server_scheduled_task_id' => $task->id,
            ...$request->validated(),
        ]);

        // Update task's last run timestamp
        $task->update([
            'last_run_at' => $taskRun->started_at,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Task run stored successfully',
            'run_id' => $taskRun->id,
        ], 201);
    }

    /**
     * Get task runs for a specific task (API endpoint)
     */
    public function getTaskRuns(Request $request, Server $server, ServerScheduledTask $scheduledTask): JsonResponse
    {
        $days = $request->integer('days', 7);

        $runs = $scheduledTask->runs()
            ->where('started_at', '>=', now()->subDays($days))
            ->orderBy('started_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ServerScheduledTaskRunResource::collection($runs),
        ]);
    }

    /**
     * Show task activity page with run history
     */
    public function showTaskActivity(Server $server, ServerScheduledTask $scheduledTask): Response
    {
        $this->authorize('view', $server);

        // Get recent task runs with pagination
        $runs = $scheduledTask->runs()
            ->orderBy('started_at', 'desc')
            ->paginate(20);

        return Inertia::render('servers/scheduler-task-activity', [
            'server' => new ServerResource($server),
            'task' => $scheduledTask,
            'runs' => ServerScheduledTaskRunResource::collection($runs),
        ]);
    }
}
