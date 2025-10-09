<?php

namespace App\Http\Controllers;

use App\Enums\SupervisorStatus;
use App\Http\Controllers\Concerns\PreparesSiteData;
use App\Http\Requests\StoreSupervisorTaskRequest;
use App\Http\Requests\UpdateSupervisorTaskRequest;
use App\Models\Server;
use App\Models\ServerSupervisor;
use App\Models\ServerSupervisorTask;
use App\Packages\Services\Supervisor\SupervisorInstallerJob;
use App\Packages\Services\Supervisor\SupervisorRemoverJob;
use App\Packages\Services\Supervisor\Task\SupervisorTaskInstaller;
use App\Packages\Services\Supervisor\Task\SupervisorTaskRemover;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ServerSupervisorController extends Controller
{
    use PreparesSiteData;

    /**
     * Display supervisor page with installation status and tasks
     */
    public function index(Server $server): Response
    {
        // Authorization
        Gate::authorize('view', [ServerSupervisor::class, $server]);

        // Eager load relationships to avoid N+1 queries
        $server->load(['supervisorTasks']);

        return Inertia::render('servers/supervisor', [
            'server' => $server->only(['id', 'vanity_name', 'provider', 'public_ip', 'ssh_port', 'private_ip', 'connection', 'monitoring_status', 'supervisor_status', 'supervisor_installed_at', 'supervisor_uninstalled_at', 'created_at', 'updated_at']),
            'tasks' => $server->supervisorTasks,
            'latestMetrics' => $this->getLatestMetrics($server),
        ]);
    }

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
            'supervisor_status' => SupervisorStatus::Installing,
        ]);

        // Dispatch supervisor installation job
        SupervisorInstallerJob::dispatch($server);

        return redirect()
            ->route('servers.supervisor', $server)
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

        // Update status to 'uninstalling' immediately for UI feedback
        $server->update([
            'supervisor_status' => SupervisorStatus::Uninstalling,
        ]);

        // Dispatch supervisor removal job
        SupervisorRemoverJob::dispatch($server);

        return redirect()
            ->route('servers.supervisor', $server)
            ->with('success', 'Supervisor uninstallation started');
    }

    /**
     * Create a new supervisor task
     */
    public function storeTask(StoreSupervisorTaskRequest $request, Server $server): RedirectResponse
    {
        // Authorization
        Gate::authorize('createTask', [ServerSupervisor::class, $server]);

        // Create the task
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

        // Install task immediately
        $installer = new SupervisorTaskInstaller($server, $task);
        $installer->execute();

        return redirect()
            ->route('servers.supervisor', $server)
            ->with('success', 'Supervisor task created and installed');
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
                $ssh = $server->createSshConnection(\App\Packages\Enums\CredentialType::Root);
                $ssh->disableStrictHostKeyChecking()->execute("rm -f /etc/supervisor/conf.d/{$oldSanitizedName}.conf");
            }

            // Reinstall task with new configuration
            $installer = new SupervisorTaskInstaller($server, $supervisorTask);
            $installer->execute();

            return redirect()
                ->route('servers.supervisor', $server)
                ->with('success', 'Supervisor task updated successfully');
        } catch (\Exception $e) {
            Log::error('Failed to update supervisor task', [
                'task_id' => $supervisorTask->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('servers.supervisor', $server)
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
        Log::warning('Supervisor task deleted', [
            'user_id' => auth()->id(),
            'server_id' => $server->id,
            'task_id' => $supervisorTask->id,
            'task_name' => $supervisorTask->name,
            'command' => $supervisorTask->command,
            'ip_address' => request()->ip(),
        ]);

        // Remove task
        $remover = new SupervisorTaskRemover($server, $supervisorTask);
        $remover->execute();

        // Delete from database
        $supervisorTask->delete();

        return redirect()
            ->route('servers.supervisor', $server)
            ->with('success', 'Supervisor task removed');
    }

    /**
     * Toggle task status (active/inactive)
     */
    public function toggleTask(Server $server, ServerSupervisorTask $supervisorTask): RedirectResponse
    {
        // Authorization
        Gate::authorize('toggleTask', [ServerSupervisor::class, $server]);

        // Toggle status
        $newStatus = $supervisorTask->status === 'active' ? 'inactive' : 'active';

        // Get sanitized task name for supervisor commands
        $sanitizedName = $this->sanitizeTaskName($supervisorTask->name);

        // Execute supervisorctl command to stop/start
        if ($newStatus === 'inactive') {
            // Stop the task
            $this->executeSupervisorctl($server, "stop {$sanitizedName}");
            $supervisorTask->update(['status' => 'inactive']);
        } else {
            // Start the task
            $this->executeSupervisorctl($server, "start {$sanitizedName}");
            $supervisorTask->update(['status' => 'active']);
        }

        return redirect()
            ->route('servers.supervisor', $server)
            ->with('success', "Task {$newStatus}");
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

        return redirect()
            ->route('servers.supervisor', $server)
            ->with('success', 'Task restarted');
    }

    /**
     * Execute a supervisorctl command on the remote server
     */
    private function executeSupervisorctl(Server $server, string $command): void
    {
        $ssh = $server->createSshConnection(\App\Packages\Enums\CredentialType::Root);
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
