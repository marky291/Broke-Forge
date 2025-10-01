<?php

namespace App\Packages\Services\Database\MySQL;

use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * MySQL Database Removal Job
 *
 * Handles queued MySQL database removal from remote servers
 */
class MySqlRemoverJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Server $server
    ) {}

    public function handle(): void
    {
        Log::info("Starting MySQL database removal for server #{$this->server->id}");

        try {
            // Create remover instance
            $remover = new MySqlRemover($this->server);

            // Execute removal - the remover handles all cleanup
            $remover->execute();

            Log::info("MySQL database removal completed for server #{$this->server->id}");
        } catch (\Exception $e) {
            Log::error("MySQL database removal failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
