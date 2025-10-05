<?php

namespace App\Packages\Services\Database\MariaDB;

use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * MariaDB Installation Job
 *
 * Handles queued MariaDB installation on remote servers
 */
class MariaDbInstallerJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Server $server
    ) {}

    public function handle(): void
    {
        $database = $this->server->databases()->latest()->first();

        Log::info("Starting MariaDB installation for server #{$this->server->id}", [
            'version' => $database?->version ?? 'unknown',
        ]);

        try {
            $installer = new MariaDbInstaller($this->server);
            $installer->execute();

            Log::info("MariaDB installation completed for server #{$this->server->id}");
        } catch (\Exception $e) {
            Log::error("MariaDB installation failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update database status to failed so UI can show error state
            $this->server->databases()->latest()->first()?->update([
                'status' => \App\Enums\DatabaseStatus::Failed->value,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
