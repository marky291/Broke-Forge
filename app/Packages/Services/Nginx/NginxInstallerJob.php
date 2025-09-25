<?php

namespace App\Packages\Services\Nginx;

use App\Models\Server;
use App\Packages\Enums\PhpVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Nginx Installation Job
 *
 * Handles queued Nginx web server installation on remote servers
 */
class NginxInstallerJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Server $server,
        public PhpVersion $phpVersion
    ) {}

    public function handle(): void
    {
        // Set no time limit for long-running installation process
        set_time_limit(0);

        Log::info("Starting Nginx installation for server #{$this->server->id} with PHP {$this->phpVersion->value}");

        try {
            // Create installer instance
            $installer = new NginxInstaller($this->server);

            // Execute installation - the installer handles all logic, database tracking, and dependencies
            $installer->execute($this->phpVersion);

            Log::info("Nginx installation completed for server #{$this->server->id}");
        } catch (\Exception $e) {
            Log::error("Nginx installation failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
