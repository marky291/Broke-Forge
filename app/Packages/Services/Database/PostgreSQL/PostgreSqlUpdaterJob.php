<?php

namespace App\Packages\Services\Database\PostgreSQL;

use App\Enums\DatabaseStatus;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PostgreSqlUpdaterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Server $server,
        public string $targetVersion
    ) {}

    public function handle(): void
    {
        Log::info("Starting PostgreSQL update for server #{$this->server->id} to version {$this->targetVersion}");

        try {
            $updater = new PostgreSqlUpdater($this->server);
            $updater->execute($this->targetVersion);

            Log::info("PostgreSQL update completed for server #{$this->server->id}");
        } catch (\Exception $e) {
            Log::error("PostgreSQL update failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->server->databases()->latest()->first()?->update([
                'status' => DatabaseStatus::Failed->value,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
