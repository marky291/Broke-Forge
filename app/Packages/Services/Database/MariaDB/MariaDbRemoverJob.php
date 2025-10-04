<?php

namespace App\Packages\Services\Database\MariaDB;

use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * MariaDB Removal Job
 *
 * Handles queued MariaDB removal from remote servers
 */
class MariaDbRemoverJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Server $server
    ) {}

    public function handle(): void
    {
        Log::info("Starting MariaDB removal for server #{$this->server->id}");

        try {
            $remover = new MariaDbRemover($this->server);
            $remover->execute();

            Log::info("MariaDB removal completed for server #{$this->server->id}");
        } catch (\Exception $e) {
            Log::error("MariaDB removal failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
