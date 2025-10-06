<?php

namespace App\Packages\Services\Supervisor;

use App\Enums\SupervisorStatus;
use App\Models\Server;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Supervisor Removal Job
 *
 * Handles queued supervisor removal from remote servers
 */
class SupervisorRemoverJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Server $server
    ) {}

    public function handle(): void
    {
        set_time_limit(0);

        Log::info("Starting supervisor removal for server #{$this->server->id}");

        try {
            // Mark as uninstalling
            $this->server->update([
                'supervisor_status' => SupervisorStatus::Uninstalling,
            ]);

            // Create remover instance
            $remover = new SupervisorRemover($this->server);

            // Execute removal
            $remover->execute();

            Log::info("Supervisor removal completed for server #{$this->server->id}");

        } catch (Exception $e) {
            // Mark as failed
            $this->server->update([
                'supervisor_status' => SupervisorStatus::Failed,
            ]);

            Log::error("Supervisor removal failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
