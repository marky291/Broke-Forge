<?php

namespace App\Packages\Services\Sites\Git;

use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Enums\GitStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Git Repository Installation Job
 *
 * Handles queued Git repository installation on sites
 */
class GitRepositoryInstallerJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Server $server,
        protected ServerSite $site,
        protected array $configuration
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting Git repository installation for site #{$this->site->id} on server #{$this->server->id}", [
            'repository' => $this->configuration['repository'] ?? 'unknown',
            'branch' => $this->configuration['branch'] ?? 'unknown',
        ]);

        try {
            // Create installer instance
            $installer = new GitRepositoryInstaller($this->server);

            // Set the site for event tracking
            $installer->setSite($this->site);

            // Execute installation - the installer handles all logic, validation, and database tracking
            $installer->execute($this->site, $this->configuration);

            // Update git status to installed and site status to active on success
            $this->site->update([
                'git_status' => GitStatus::Installed,
                'git_installed_at' => now(),
                'status' => 'active',
                'provisioned_at' => now(),
            ]);

            Log::info("Git repository installation completed for site #{$this->site->id} on server #{$this->server->id}");
        } catch (\Exception $e) {
            // Update git status to failed on error
            $this->site->update([
                'git_status' => GitStatus::Failed,
            ]);

            Log::error("Git repository installation failed for site #{$this->site->id} on server #{$this->server->id}", [
                'repository' => $this->configuration['repository'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
