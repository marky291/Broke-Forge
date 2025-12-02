<?php

namespace App\Packages\Services\Supervisor;

use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Supervisor Removal Job
 *
 * Handles queued supervisor removal from remote servers.
 * Note: This job does not track status on a specific model resource.
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
            $remover = new SupervisorRemover($this->server);
            $remover->execute();

            Log::info("Supervisor removal completed for server #{$this->server->id}");
        } catch (\Exception $e) {
            Log::error("Supervisor removal failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

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
        Log::error('SupervisorRemoverJob job failed', [
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
