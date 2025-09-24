<?php

namespace App\Packages\Services\Sites;

use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Site Installation Job
 *
 * Handles queued site installation on remote servers
 */
class SiteInstallerJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Server $server,
        public string $domain,
        public string $phpVersion,
        public bool $ssl
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting site installation for domain {$this->domain} on server #{$this->server->id}", [
            'php_version' => $this->phpVersion,
            'ssl_enabled' => $this->ssl,
        ]);

        try {
            // Create installer instance
            $installer = new SiteInstaller($this->server);

            // Configure the site
            $config = [
                'domain' => $this->domain,
                'php_version' => $this->phpVersion,
                'ssl' => $this->ssl,
            ];

            // Execute installation - the installer handles all logic and database tracking
            $installer->execute($config);

            Log::info("Site installation completed for domain {$this->domain} on server #{$this->server->id}");
        } catch (\Exception $e) {
            Log::error("Site installation failed for domain {$this->domain} on server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
