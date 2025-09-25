<?php

namespace App\Packages\Services\PHP;

use App\Models\Server;
use App\Packages\Enums\PhpVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * PHP Installation Job
 *
 * Handles queued PHP installation on remote servers
 */
class PhpInstallerJob implements ShouldQueue
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

        Log::info("Starting PHP {$this->phpVersion->value} installation for server #{$this->server->id}");

        try {
            // Create installer instance
            $installer = new PhpInstaller($this->server);

            // Execute installation - the installer's persist() method handles database tracking
            $installer->execute($this->phpVersion);

            Log::info("PHP {$this->phpVersion->value} installation completed for server #{$this->server->id}");
        } catch (\Exception $e) {
            Log::error("PHP {$this->phpVersion->value} installation failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}