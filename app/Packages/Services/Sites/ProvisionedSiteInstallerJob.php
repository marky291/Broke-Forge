<?php

namespace App\Packages\Services\Sites;

use App\Models\Server;
use App\Models\ServerSite;
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
     * The number of seconds the job can run before timing out.
     */
    public $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Server $server,
        public int $siteId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        set_time_limit(0);

        // Load the site record
        $site = ServerSite::findOrFail($this->siteId);

        Log::info("Starting site installation for site #{$site->id} (domain: {$site->domain}) on server #{$this->server->id}");

        try {
            // Create installer instance
            $installer = new ProvisionedSiteInstaller($this->server);

            // Execute installation - the installer handles all logic and database tracking
            $installer->execute($this->siteId);

            Log::info("Site installation completed for site #{$site->id} on server #{$this->server->id}");
        } catch (\Exception $e) {
            // Update status to failed
            $site->update(['status' => 'failed']);

            Log::error("Site installation failed for site #{$site->id} on server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
