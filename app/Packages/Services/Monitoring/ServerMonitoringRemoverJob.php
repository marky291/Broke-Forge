<?php

namespace App\Packages\Services\Monitoring;

use App\Enums\MonitoringStatus;
use App\Models\Server;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Server Monitoring Removal Job
 *
 * Handles queued monitoring removal from remote servers
 */
class ServerMonitoringRemoverJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

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
                'monitoring_status' => MonitoringStatus::Uninstalling,
            ]);

            // Create remover instance
            $remover = new ServerMonitoringRemover($this->server);

            // Execute removal
            $remover->execute();

            Log::info("Monitoring removal completed for server #{$this->server->id}");

        } catch (Exception $e) {
            // Mark as failed
            $this->server->update([
                'monitoring_status' => MonitoringStatus::Failed,
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
                'monitoring_status' => MonitoringStatus::Failed,
            ]);
        }

        Log::error('ServerMonitoringRemoverJob job failed', [
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
