<?php

namespace App\Packages\Services\Supervisor;

use App\Enums\SupervisorStatus;
use App\Models\Server;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Supervisor Removal Job
 *
 * Handles queued supervisor removal from remote servers
 */
class SupervisorRemoverJob implements ShouldQueue
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
                'error_log' => $e->getMessage(),
            ]);

            Log::error("Supervisor removal failed for server #{$this->server->id}", [
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
            'supervisor_status' => SupervisorStatus::Failed,
            'error_log' => $exception->getMessage(),
        ]);

        Log::error('SupervisorRemoverJob job failed', [
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
