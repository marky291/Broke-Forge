<?php

namespace App\Packages\Services\Database\PostgreSQL;

use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PostgreSqlRemoverJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Server $server
    ) {}

    public function handle(): void
    {
        Log::info("Starting PostgreSQL removal for server #{$this->server->id}");

        try {
            $remover = new PostgreSqlRemover($this->server);
            $remover->execute();

            Log::info("PostgreSQL removal completed for server #{$this->server->id}");
        } catch (\Exception $e) {
            Log::error("PostgreSQL removal failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
