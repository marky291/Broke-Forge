<?php

namespace App\Packages\Services\Database\MySQL;

use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * MySQL Database Installation Job
 *
 * Handles queued MySQL database installation on remote servers
 */
class MySqlInstallerJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Server $server
    ) {}

    public function handle(): void
    {
        Log::info("Starting MySQL database installation for server #{$this->server->id}");

        try {
            // Create installer instance
            $installer = new MySqlInstaller($this->server);

            // Execute installation - the installer handles database tracking
            $installer->execute();

            Log::info("MySQL database installation completed for server #{$this->server->id}");
        } catch (\Exception $e) {
            Log::error("MySQL database installation failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
