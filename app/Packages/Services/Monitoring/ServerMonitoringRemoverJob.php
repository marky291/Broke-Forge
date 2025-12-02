<?php

namespace App\Packages\Services\Monitoring;

use App\Enums\TaskStatus;
use App\Models\Server;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Server Monitoring Removal Job
 *
 * Handles queued monitoring removal from remote servers
 */
class ServerMonitoringRemoverJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Server $server
    ) {}

    public function handle(): void
    {
        set_time_limit(0);

        Log::info("Starting monitoring removal for server #{$this->server->id}");

        try {
            // Mark as uninstalling
            $this->server->update([
                'monitoring_status' => TaskStatus::Removing,
            ]);

            // Create remover instance
            $remover = new ServerMonitoringRemover($this->server);

            // Execute removal
            $remover->execute();

            Log::info("Monitoring removal completed for server #{$this->server->id}");

        } catch (Exception $e) {
            // Mark as failed
            $this->server->update([
                'monitoring_status' => TaskStatus::Failed,
            ]);

            Log::error("Monitoring removal failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("package:action:{$this->server->id}"))->shared()
                ->releaseAfter(15)
                ->expireAfter(900),
        ];
    }

    public function failed(\Throwable $exception): void
    {
        $this->server->refresh();

        if ($this->server) {
            $this->server->update([
                'monitoring_status' => TaskStatus::Failed,
            ]);
        }

        Log::error('ServerMonitoringRemoverJob job failed', [
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
