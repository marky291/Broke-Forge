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
        Log::info("Starting MariaDB installation for server #{$this->server->id}");

        try {
            $installer = new MariaDbInstaller($this->server);
            $installer->execute();

            Log::info("MariaDB installation completed for server #{$this->server->id}");
        } catch (\Exception $e) {
            Log::error("MariaDB installation failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
