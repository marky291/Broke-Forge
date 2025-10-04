<?php

namespace App\Packages\Services\Scheduler;

use App\Enums\SchedulerStatus;
use App\Models\Server;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Server Scheduler Framework Installation Job
 *
 * Handles queued scheduler framework installation on remote servers
 */
class ServerSchedulerInstallerJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Server $server
    ) {}

    public function handle(): void
    {
        set_time_limit(0);

        Log::info("Starting scheduler framework installation for server #{$this->server->id}");

        try {
            // Mark as installing
            $this->server->update([
                'scheduler_status' => SchedulerStatus::Installing,
            ]);

            // Create installer instance
            $installer = new ServerSchedulerInstaller($this->server);

            // Execute installation
            $installer->execute();

            Log::info("Scheduler framework installation completed for server #{$this->server->id}");

        } catch (Exception $e) {
            // Mark as failed
            $this->server->update([
                'scheduler_status' => SchedulerStatus::Failed,
            ]);

            Log::error("Scheduler framework installation failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
