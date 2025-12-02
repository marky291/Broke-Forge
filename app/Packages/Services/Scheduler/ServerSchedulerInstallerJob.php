<?php

namespace App\Packages\Services\Scheduler;

use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Server Scheduler Installation Job
 *
 * Handles queued scheduler installation on remote servers.
 * Note: This job does not track status on a specific model resource.
 */
class ServerSchedulerInstallerJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Server $server
    ) {}

    public function handle(): void
    {
        set_time_limit(0);

        Log::info("Starting scheduler installation for server #{$this->server->id}");

        try {
            $installer = new ServerSchedulerInstaller($this->server);
            $installer->execute();

            Log::info("Scheduler installation completed for server #{$this->server->id}");
        } catch (\Exception $e) {
            Log::error("Scheduler installation failed for server #{$this->server->id}", [
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
        Log::error('ServerSchedulerInstallerJob job failed', [
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
