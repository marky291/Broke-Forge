<?php

namespace App\Packages\Services\Firewall;

use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Firewall Installation Job
 *
 * Handles queued UFW firewall installation on remote servers
 */
class FirewallInstallerJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Server $server
    ) {}

    public function handle(): void
    {
        Log::info("Starting UFW firewall installation for server #{$this->server->id}");

        try {
            // Create installer instance
            $installer = new FirewallInstaller($this->server);

            // Execute installation - the installer's persist() method handles database tracking
            $installer->execute();

            Log::info("UFW firewall installation completed for server #{$this->server->id}");
        } catch (\Exception $e) {
            Log::error("UFW firewall installation failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
