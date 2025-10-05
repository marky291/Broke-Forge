<?php

namespace App\Packages\Services\Database\MariaDB;

use App\Enums\DatabaseStatus;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MariaDbUpdaterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Server $server,
        public string $targetVersion
    ) {}

    public function handle(): void
    {
        Log::info("Starting MariaDB update for server #{$this->server->id}", [
            'version' => $this->targetVersion,
        ]);

        try {
            $updater = new MariaDbUpdater($this->server);
            $updater->execute($this->targetVersion);

            Log::info("MariaDB update completed for server #{$this->server->id}");
        } catch (\Exception $e) {
            Log::error("MariaDB update failed for server #{$this->server->id}", [
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
