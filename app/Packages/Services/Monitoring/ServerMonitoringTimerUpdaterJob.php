<?php

namespace App\Packages\Services\Monitoring;

use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Server Monitoring Timer Update Job
 *
 * Handles queued monitoring timer interval updates on remote servers
 */
class ServerMonitoringTimerUpdaterJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Server $server,
        public int $intervalSeconds
    ) {}

    public function handle(): void
    {
        Log::info("Starting monitoring timer update for server #{$this->server->id}", [
            'interval_seconds' => $this->intervalSeconds,
            'interval_minutes' => $this->intervalSeconds / 60,
        ]);

        try {
            // Create updater instance
            $updater = new ServerMonitoringTimerUpdater($this->server);

            // Execute timer update
            $updater->execute($this->intervalSeconds);

            Log::info("Monitoring timer update completed for server #{$this->server->id}");
        } catch (\Exception $e) {
            Log::error("Monitoring timer update failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
