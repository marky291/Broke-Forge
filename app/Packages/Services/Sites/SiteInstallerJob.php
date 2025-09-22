<?php

namespace App\Packages\Services\Sites;

use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job for installing sites on servers
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
        try {
            // Create the site record
            $site = $this->server->sites()->create([
                'domain' => $this->domain,
                'document_root' => "/var/www/{$this->domain}/public",
                'nginx_config_path' => "/etc/nginx/sites-available/{$this->domain}",
                'php_version' => $this->phpVersion,
                'ssl_enabled' => $this->ssl,
                'status' => 'provisioning',
            ]);

            // Initialize the installer
            $siteInstaller = new SiteInstaller($this->server);

            // Configure the site
            $config = [
                'domain' => $this->domain,
                'php_version' => $this->phpVersion,
                'ssl' => $this->ssl,
            ];

            // Run the installation with new pattern
            $updatedSite = $siteInstaller->execute($config);

            // Update site status
            $site->update([
                'status' => 'active',
                'provisioned_at' => now(),
            ]);

            Log::info('Site installed successfully', [
                'server_id' => $this->server->id,
                'site_id' => $site->id,
                'domain' => $this->domain,
            ]);

        } catch (\Exception $e) {
            Log::error('Site installation failed', [
                'server_id' => $this->server->id,
                'domain' => $this->domain,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update site status if it exists
            if (isset($site)) {
                $site->update(['status' => 'failed']);
            }

            throw $e;
        }
    }
}
