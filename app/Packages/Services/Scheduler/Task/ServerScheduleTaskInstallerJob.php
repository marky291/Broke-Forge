<?php

namespace App\Packages\Services\Scheduler\Task;

use App\Models\Server;
use App\Models\ServerScheduledTask;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Server Scheduled Task Installation Job
 *
 * Handles queued task installation on remote servers.
 * Each job instance handles ONE scheduled task only.
 * For multiple tasks, dispatch multiple job instances.
 */
class ServerScheduleTaskInstallerJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Server  $server  The server to configure
     * @param  int  $taskId  The ServerScheduledTask ID to install
     */
    public function __construct(
        public Server $server,
        public int $taskId
    ) {}

    public function handle(): void
    {
        set_time_limit(0);

        // Load the scheduled task
        $task = ServerScheduledTask::findOrFail($this->taskId);

        Log::info("Starting scheduled task installation for server #{$this->server->id}", [
            'task_id' => $task->id,
            'task_name' => $task->name,
            'command' => $task->command,
        ]);

        try {
            // Update status to 'installing'
            $task->update(['status' => 'installing']);

            // Create installer instance with existing task model
            $installer = new ServerScheduleTaskInstaller($this->server, $task);

            // Execute installation
            $installer->execute();

            // Update status to 'active' on success
            $task->update(['status' => 'active']);

            Log::info("Scheduled task '{$task->name}' installed successfully on server #{$this->server->id}");

        } catch (Exception $e) {
            // Update status to 'failed' on error
            $task->update(['status' => 'failed']);

            Log::error("Scheduled task installation failed for server #{$this->server->id}", [
                'task_id' => $task->id,
                'task_name' => $task->name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
