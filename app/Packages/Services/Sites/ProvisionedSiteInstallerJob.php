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
class ProvisionedSiteInstallerJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Server $server,
        public string $domain,
        public string $phpVersion,
        public bool $ssl,
        public ?string $gitRepository = null,
        public ?string $gitBranch = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting site installation for domain {$this->domain} on server #{$this->server->id}", [
            'php_version' => $this->phpVersion,
            'ssl_enabled' => $this->ssl,
            'git_repository' => $this->gitRepository,
            'git_branch' => $this->gitBranch,
        ]);

        try {
            // Create installer instance
            $installer = new ProvisionedSiteInstaller($this->server);

            // Configure the site
            $config = [
                'domain' => $this->domain,
                'php_version' => $this->phpVersion,
                'ssl' => $this->ssl,
            ];

            // Add git repository configuration if provided
            if ($this->gitRepository) {
                $config['git_repository'] = [
                    'provider' => 'github',
                    'repository' => $this->gitRepository,
                    'branch' => $this->gitBranch ?? 'main',
                ];
            }

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
