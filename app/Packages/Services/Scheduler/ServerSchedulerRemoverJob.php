<?php

namespace App\Packages\Services\Scheduler;

use App\Enums\SchedulerStatus;
use App\Models\Server;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Server Scheduler Framework Removal Job
 *
 * Handles queued scheduler framework removal from remote servers
 */
class ServerSchedulerRemoverJob implements ShouldQueue
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
    public $tries = 0;

    /**
     * The number of exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

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
                'error_log' => $e->getMessage(),
            ]);

            Log::error("Scheduler framework removal failed for server #{$this->server->id}", [
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
        $this->server->update([
            'scheduler_status' => SchedulerStatus::Failed,
            'error_log' => $exception->getMessage(),
        ]);

        Log::error('ServerSchedulerRemoverJob job failed', [
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
