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
 * Handles queued task installation on remote servers
 */
class ServerScheduleTaskInstallerJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Server $server,
        public ServerScheduledTask $task
    ) {}

    public function handle(): void
    {
        set_time_limit(0);

        Log::info("Starting scheduled task installation for task #{$this->task->id} on server #{$this->server->id}");

        try {
            // Create installer instance
            $installer = new ServerScheduleTaskInstaller($this->server, $this->task);

            // Execute installation
            $installer->execute();

            Log::info("Scheduled task installation completed for task #{$this->task->id} on server #{$this->server->id}");

        } catch (Exception $e) {
            Log::error("Scheduled task installation failed for task #{$this->task->id} on server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
