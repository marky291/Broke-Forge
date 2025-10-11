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
 * Accepts either array data (creates DB record) or existing task model (uses existing record).
 */
class ServerScheduleTaskInstallerJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Server  $server  The server to configure
     * @param  array|ServerScheduledTask  $taskDataOrModel  Task data array or existing task model
     */
    public function __construct(
        public Server $server,
        public array|ServerScheduledTask $taskDataOrModel
    ) {}

    public function handle(): void
    {
        set_time_limit(0);

        $taskName = is_array($this->taskDataOrModel)
            ? $this->taskDataOrModel['name']
            : $this->taskDataOrModel->name;

        Log::info("Starting scheduled task installation for server #{$this->server->id}", [
            'task_name' => $taskName,
        ]);

        try {
            // Create installer instance
            $installer = new ServerScheduleTaskInstaller($this->server, $this->taskDataOrModel);

            // Execute installation
            $installer->execute();

            Log::info("Scheduled task '{$taskName}' installed successfully on server #{$this->server->id}");

        } catch (Exception $e) {
            Log::error("Scheduled task installation failed for server #{$this->server->id}", [
                'task_name' => $taskName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
