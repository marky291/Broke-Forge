<?php

namespace App\Packages\Services\Supervisor;

use App\Enums\SupervisorStatus;
use App\Models\Server;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Supervisor Installation Job
 *
 * Handles queued supervisor installation on remote servers
 */
class SupervisorInstallerJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Server $server
    ) {}

    public function handle(): void
    {
        set_time_limit(0);

        Log::info("Starting supervisor installation for server #{$this->server->id}");

        try {
            // Mark as installing
            $this->server->update([
                'supervisor_status' => SupervisorStatus::Installing,
            ]);

            // Create installer instance
            $installer = new SupervisorInstaller($this->server);

            // Execute installation
            $installer->execute();

            Log::info("Supervisor installation completed for server #{$this->server->id}");

        } catch (Exception $e) {
            // Mark as failed
            $this->server->update([
                'supervisor_status' => SupervisorStatus::Failed,
                'error_log' => $e->getMessage(),
            ]);

            Log::error("Supervisor installation failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->server->update([
            'supervisor_status' => SupervisorStatus::Failed,
            'error_log' => $exception->getMessage(),
        ]);

        Log::error('SupervisorInstallerJob job failed', [
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
