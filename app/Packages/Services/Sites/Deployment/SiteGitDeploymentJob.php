<?php

namespace App\Packages\Services\Sites\Deployment;

use App\Models\Server;
use App\Models\ServerDeployment;
use App\Models\ServerSite;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Site Git Deployment Job
 *
 * Handles queued deployment execution for Git-enabled sites
 */
class SiteGitDeploymentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Server $server,
        public ServerSite $site,
        public ServerDeployment $deployment
    ) {}

    public function handle(): void
    {
        Log::info("Starting deployment for site #{$this->site->id}", [
            'deployment_id' => $this->deployment->id,
            'server_id' => $this->server->id,
        ]);

        $installer = new SiteGitDeploymentInstaller($this->server);
        $installer->setSite($this->site);
        $installer->execute($this->site, $this->deployment);

        Log::info("Deployment job completed for site #{$this->site->id}", [
            'deployment_id' => $this->deployment->id,
            'status' => $this->deployment->fresh()->status,
        ]);
    }
}
