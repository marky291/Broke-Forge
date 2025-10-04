<?php

namespace App\Packages\Services\Monitoring;

use App\Enums\MonitoringStatus;
use App\Models\Server;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Server Monitoring Installation Job
 *
 * Handles queued monitoring installation on remote servers
 */
class ServerMonitoringInstallerJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Server $server
    ) {}

    public function handle(): void
    {
        set_time_limit(0);

        Log::info("Starting monitoring installation for server #{$this->server->id}");

        try {
            // Mark as installing
            $this->server->update([
                'monitoring_status' => MonitoringStatus::Installing,
            ]);

            // Create installer instance
            $installer = new ServerMonitoringInstaller($this->server);

            // Execute installation
            $installer->execute();

            Log::info("Monitoring installation completed for server #{$this->server->id}");

        } catch (Exception $e) {
            // Mark as failed
            $this->server->update([
                'monitoring_status' => MonitoringStatus::Failed,
            ]);

            Log::error("Monitoring installation failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
