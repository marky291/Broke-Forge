<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Controllers\Concerns\PreparesSiteData;
use App\Http\Requests\StoreSupervisorTaskRequest;
use App\Http\Requests\UpdateSupervisorTaskRequest;
use App\Http\Resources\ServerResource;
use App\Models\Server;
use App\Models\ServerSupervisor;
use App\Models\ServerSupervisorTask;
use App\Packages\Services\Supervisor\SupervisorInstallerJob;
use App\Packages\Services\Supervisor\SupervisorRemoverJob;
use App\Packages\Services\Supervisor\Task\SupervisorTaskInstaller;
use App\Packages\Services\Supervisor\Task\SupervisorTaskInstallerJob;
use App\Packages\Services\Supervisor\Task\SupervisorTaskRemoverJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ServerSupervisorController extends Controller
{
    use PreparesSiteData;

    /**
     * Install supervisor on the server
     */
    public function install(Server $server): RedirectResponse
    {
        // Authorization
        Gate::authorize('install', [ServerSupervisor::class, $server]);

        // Audit log
        Log::info('Supervisor installation initiated', [
            'user_id' => auth()->id(),
            'server_id' => $server->id,
            'ip_address' => request()->ip(),
        ]);

        // Update supervisor status immediately for UI feedback
        $server->update([
            'supervisor_status' => TaskStatus::Installing,
        ]);

        // Dispatch supervisor installation job
        SupervisorInstallerJob::dispatch($server);

        return back()
            ->with('success', 'Supervisor installation started');
    }

    /**
     * Uninstall supervisor from the server
     */
    public function uninstall(Server $server): RedirectResponse
    {
        // Authorization
        Gate::authorize('uninstall', [ServerSupervisor::class, $server]);

        // Audit log
        Log::warning('Supervisor uninstallation initiated', [
            'user_id' => auth()->id(),
            'server_id' => $server->id,
            'task_count' => $server->supervisorTasks()->count(),
            'ip_address' => request()->ip(),
        ]);

        // Update status to 'removing' immediately for UI feedback
        $server->update([
            'supervisor_status' => TaskStatus::Removing,
        ]);

        // Dispatch supervisor removal job
        SupervisorRemoverJob::dispatch($server);

        return back()
            ->with('success', 'Supervisor uninstallation started');
    }

    /**
     * Create a new supervisor task
     */
    public function storeTask(StoreSupervisorTaskRequest $request, Server $server): RedirectResponse
    {
        // Authorization
        Gate::authorize('createTask', [ServerSupervisor::class, $server]);

        // ✅ CREATE RECORD FIRST with 'pending' status (default from migration)
        $task = $server->supervisorTasks()->create($request->validated());

        // Audit log
        Log::info('Supervisor task created', [
            'user_id' => auth()->id(),
            'server_id' => $server->id,
            'task_id' => $task->id,
            'task_name' => $task->name,
            'command' => $task->command,
            'ip_address' => request()->ip(),
        ]);

        // ✅ THEN dispatch job with task model
        SupervisorTaskInstallerJob::dispatch($server, $task);

        return back()
            ->with('success', 'Supervisor task created and installation started');
    }

    /**
     * Update an existing supervisor task
     */
    public function updateTask(UpdateSupervisorTaskRequest $request, Server $server, ServerSupervisorTask $supervisorTask): RedirectResponse
    {
        // Authorization
        Gate::authorize('updateTask', [ServerSupervisor::class, $server]);

        // Store old sanitized name for cleanup
        $oldSanitizedName = $this->sanitizeTaskName($supervisorTask->name);

        // Audit log
        Log::info('Supervisor task updated', [
            'user_id' => auth()->id(),
            'server_id' => $server->id,
            'task_id' => $supervisorTask->id,
            'old_name' => $supervisorTask->name,
            'new_name' => $request->input('name'),
            'ip_address' => request()->ip(),
        ]);

        // Update the task in database
        $supervisorTask->update($request->validated());

        // Get new sanitized name
        $newSanitizedName = $this->sanitizeTaskName($supervisorTask->name);

        try {
            // Stop the old task
            $this->executeSupervisorctl($server, "stop {$oldSanitizedName} || true");

            // Remove old config file if name changed
            if ($oldSanitizedName !== $newSanitizedName) {
                $ssh = $server->ssh('root');
                $ssh->disableStrictHostKeyChecking()->execute("rm -f /etc/supervisor/conf.d/{$oldSanitizedName}.conf");
            }

            // Reinstall task with new configuration
            $installer = new SupervisorTaskInstaller($server, $supervisorTask);
            $installer->execute();

            return back()
                ->with('success', 'Supervisor task updated successfully');
        } catch (\Exception $e) {
            Log::error('Failed to update supervisor task', [
                'task_id' => $supervisorTask->id,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->with('error', 'Failed to update supervisor task: '.$e->getMessage());
        }
    }

    /**
     * Delete a supervisor task
     */
    public function destroyTask(Server $server, ServerSupervisorTask $supervisorTask): RedirectResponse
    {
        // Authorization
        Gate::authorize('deleteTask', [ServerSupervisor::class, $server]);

        // Audit log
        Log::warning('Supervisor task deletion initiated', [
            'user_id' => auth()->id(),
            'server_id' => $server->id,
            'task_id' => $supervisorTask->id,
            'task_name' => $supervisorTask->name,
            'command' => $supervisorTask->command,
            'ip_address' => request()->ip(),
        ]);

        // ✅ UPDATE status to 'pending' (broadcasts automatically via model event)
        $supervisorTask->update(['status' => TaskStatus::Pending]);

        // ✅ THEN dispatch job with task model
        SupervisorTaskRemoverJob::dispatch($server, $supervisorTask);

        return back()
            ->with('success', 'Supervisor task removal started');
    }

    /**
     * Toggle task status (active/inactive)
     */
    public function toggleTask(Server $server, ServerSupervisorTask $supervisorTask): RedirectResponse
    {
        // Authorization
        Gate::authorize('toggleTask', [ServerSupervisor::class, $server]);

        // Toggle status
        $newStatus = $supervisorTask->status === TaskStatus::Active ? TaskStatus::Paused : TaskStatus::Active;

        // Get sanitized task name for supervisor commands
        $sanitizedName = $this->sanitizeTaskName($supervisorTask->name);

        // Execute supervisorctl command to stop/start
        if ($newStatus === TaskStatus::Paused) {
            // Stop the task
            $this->executeSupervisorctl($server, "stop {$sanitizedName}");
            $supervisorTask->update(['status' => TaskStatus::Paused]);
        } else {
            // Start the task
            $this->executeSupervisorctl($server, "start {$sanitizedName}");
            $supervisorTask->update(['status' => TaskStatus::Active]);
        }

        return back()
            ->with('success', "Task {$newStatus->value}");
    }

    /**
     * Restart a supervisor task
     */
    public function restartTask(Server $server, ServerSupervisorTask $supervisorTask): RedirectResponse
    {
        // Authorization
        Gate::authorize('restartTask', [ServerSupervisor::class, $server]);

        // Audit log
        Log::info('Supervisor task restart triggered', [
            'user_id' => auth()->id(),
            'server_id' => $server->id,
            'task_id' => $supervisorTask->id,
            'task_name' => $supervisorTask->name,
            'ip_address' => request()->ip(),
        ]);

        // Get sanitized task name for supervisor commands
        $sanitizedName = $this->sanitizeTaskName($supervisorTask->name);

        // Execute supervisorctl restart command
        $this->executeSupervisorctl($server, "restart {$sanitizedName}");

        return back()
            ->with('success', 'Task restarted');
    }

    /**
     * Retry a failed supervisor task
     */
    public function retryTask(Server $server, ServerSupervisorTask $supervisorTask): RedirectResponse
    {
        // Authorization
        Gate::authorize('updateTask', [ServerSupervisor::class, $server]);

        // Only allow retry for failed tasks
        if ($supervisorTask->status !== TaskStatus::Failed) {
            return back()
                ->with('error', 'Only failed tasks can be retried');
        }

        // Audit log
        Log::info('Supervisor task retry initiated', [
            'user_id' => auth()->id(),
            'server_id' => $server->id,
            'task_id' => $supervisorTask->id,
            'task_name' => $supervisorTask->name,
            'ip_address' => request()->ip(),
        ]);

        // ✅ Reset status to 'pending' to trigger reinstallation
        $supervisorTask->update(['status' => TaskStatus::Pending]);

        // ✅ Dispatch job with task model
        SupervisorTaskInstallerJob::dispatch($server, $supervisorTask);

        return back()
            ->with('success', 'Task retry started');
    }

    /**
     * Show logs for a supervisor task
     */
    public function showLogs(Server $server, ServerSupervisorTask $supervisorTask): Response
    {
        // Authorization
        Gate::authorize('view', [ServerSupervisor::class, $server]);

        // Audit log
        Log::info('Supervisor task logs viewed', [
            'user_id' => auth()->id(),
            'server_id' => $server->id,
            'task_id' => $supervisorTask->id,
            'task_name' => $supervisorTask->name,
            'ip_address' => request()->ip(),
        ]);

        $logs = [];
        $error = null;

        try {
            $ssh = $server->ssh('root');
            $sanitizedName = $this->sanitizeTaskName($supervisorTask->name);

            // Determine log paths (use fallback if not set in database)
            $stdoutLogfile = $supervisorTask->stdout_logfile ?? "/var/log/supervisor/{$sanitizedName}-stdout.log";
            $stderrLogfile = $supervisorTask->stderr_logfile ?? "/var/log/supervisor/{$sanitizedName}-stderr.log";

            // Fetch stdout logs (last 500 lines)
            $stdoutResult = $ssh->disableStrictHostKeyChecking()->execute(
                "tail -n 500 {$stdoutLogfile} 2>&1 || echo 'Log file not found'"
            );

            if ($stdoutResult->isSuccessful()) {
                $stdoutOutput = $stdoutResult->getOutput();
                if (! empty($stdoutOutput)) {
                    $stdoutLines = explode("\n", trim($stdoutOutput));
                    foreach ($stdoutLines as $line) {
                        if (! empty($line) && $line !== 'Log file not found') {
                            $logs[] = [
                                'source' => 'stdout',
                                'content' => $line,
                            ];
                        }
                    }
                }
            }

            // Fetch stderr logs (last 500 lines)
            $stderrResult = $ssh->disableStrictHostKeyChecking()->execute(
                "tail -n 500 {$stderrLogfile} 2>&1 || echo 'Log file not found'"
            );

            if ($stderrResult->isSuccessful()) {
                $stderrOutput = $stderrResult->getOutput();
                if (! empty($stderrOutput)) {
                    $stderrLines = explode("\n", trim($stderrOutput));
                    foreach ($stderrLines as $line) {
                        if (! empty($line) && $line !== 'Log file not found') {
                            $logs[] = [
                                'source' => 'stderr',
                                'content' => $line,
                            ];
                        }
                    }
                }
            }

            if (empty($logs)) {
                $error = 'No logs available yet. The task may not have produced any output.';
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch supervisor task logs', [
                'task_id' => $supervisorTask->id,
                'error' => $e->getMessage(),
            ]);

            $error = 'Failed to fetch logs: '.$e->getMessage();
        }

        return Inertia::render('servers/tasks', [
            'server' => new ServerResource($server),
            'viewingSupervisorLogs' => true,
            'supervisorTaskLogs' => [
                'task_id' => $supervisorTask->id,
                'task_name' => $supervisorTask->name,
                'logs' => $logs,
                'error' => $error,
            ],
        ]);
    }

    /**
     * Show remote status for a supervisor task
     */
    public function showStatus(Server $server, ServerSupervisorTask $supervisorTask): Response
    {
        // Authorization
        Gate::authorize('view', [ServerSupervisor::class, $server]);

        // Audit log
        Log::info('Supervisor task status viewed', [
            'user_id' => auth()->id(),
            'server_id' => $server->id,
            'task_id' => $supervisorTask->id,
            'task_name' => $supervisorTask->name,
            'ip_address' => request()->ip(),
        ]);

        $status = null;
        $error = null;

        try {
            $sanitizedName = $this->sanitizeTaskName($supervisorTask->name);
            $ssh = $server->ssh('root');

            // Execute supervisorctl status command
            $statusResult = $ssh->disableStrictHostKeyChecking()->execute(
                "supervisorctl status {$sanitizedName} 2>&1"
            );

            if ($statusResult->isSuccessful()) {
                $statusOutput = $statusResult->getOutput();
                if (! empty($statusOutput)) {
                    // Parse supervisor status output
                    // Example: "queue-worker:queue-worker_00   RUNNING   pid 1234, uptime 2 days, 5:32:10"
                    $parsed = $this->parseSupervisorStatus($statusOutput);
                    $status = [
                        'raw_output' => $statusOutput,
                        'parsed' => $parsed,
                    ];
                } else {
                    $error = 'No status information available.';
                }
            } else {
                $error = 'No status information available.';
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch supervisor task status', [
                'task_id' => $supervisorTask->id,
                'error' => $e->getMessage(),
            ]);

            $error = 'Failed to fetch status: '.$e->getMessage();
        }

        return Inertia::render('servers/tasks', [
            'server' => new ServerResource($server),
            'viewingSupervisorStatus' => true,
            'supervisorTaskStatus' => [
                'task_id' => $supervisorTask->id,
                'task_name' => $supervisorTask->name,
                'status' => $status,
                'error' => $error,
            ],
        ]);
    }

    /**
     * Parse supervisorctl status output
     */
    private function parseSupervisorStatus(string $output): ?array
    {
        // Example output: "queue-worker:queue-worker_00   RUNNING   pid 1234, uptime 2 days, 5:32:10"
        $pattern = '/^(\S+)\s+(RUNNING|STOPPED|STARTING|STOPPING|BACKOFF|FATAL|EXITED|UNKNOWN)(?:\s+pid\s+(\d+))?,?\s*(?:uptime\s+(.+))?$/im';

        if (preg_match($pattern, $output, $matches)) {
            return [
                'name' => $matches[1] ?? null,
                'state' => $matches[2] ?? null,
                'pid' => $matches[3] ?? null,
                'uptime' => $matches[4] ?? null,
            ];
        }

        return null;
    }

    /**
     * Execute a supervisorctl command on the remote server
     */
    private function executeSupervisorctl(Server $server, string $command): void
    {
        $ssh = $server->ssh('root');
        $ssh->disableStrictHostKeyChecking()->execute("supervisorctl {$command}");
    }

    /**
     * Sanitize task name for supervisor config and commands
     */
    private function sanitizeTaskName(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9-_]/', '_', $name);
    }
}
