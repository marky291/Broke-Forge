<?php

namespace App\Jobs;

use App\Models\Server;
use App\Models\Site;
use App\Provision\Sites\ProvisionSite;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProvisionSiteJob implements ShouldQueue
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

            // Initialize the provisioner
            $provisionSite = new ProvisionSite($this->server);

            // Configure the site
            $config = [
                'domain' => $this->domain,
                'php_version' => $this->phpVersion,
                'ssl' => $this->ssl,
            ];

            $provisionSite->setConfiguration($config);

            // Run the provisioning
            $provisionSite->provision();

            // Update site status
            $site->update([
                'status' => 'active',
                'provisioned_at' => now(),
            ]);

            Log::info('Site provisioned successfully', [
                'server_id' => $this->server->id,
                'site_id' => $site->id,
                'domain' => $this->domain,
            ]);

        } catch (\Exception $e) {
            Log::error('Site provisioning failed', [
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
