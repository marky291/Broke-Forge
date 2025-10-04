<?php

namespace App\Packages\Services\Scheduler;

use App\Enums\SchedulerStatus;
use App\Models\Server;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Server Scheduler Framework Removal Job
 *
 * Handles queued scheduler framework removal from remote servers
 */
class ServerSchedulerRemoverJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Server $server
    ) {}

    public function handle(): void
    {
        set_time_limit(0);

        Log::info("Starting scheduler framework removal for server #{$this->server->id}");

        try {
            // Mark as uninstalling
            $this->server->update([
                'scheduler_status' => SchedulerStatus::Uninstalling,
            ]);

            // Create remover instance
            $remover = new ServerSchedulerRemover($this->server);

            // Execute removal
            $remover->execute();

            Log::info("Scheduler framework removal completed for server #{$this->server->id}");

        } catch (Exception $e) {
            // Mark as failed
            $this->server->update([
                'scheduler_status' => SchedulerStatus::Failed,
            ]);

            Log::error("Scheduler framework removal failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
