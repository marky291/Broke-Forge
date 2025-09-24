<?php

namespace App\Packages\Services\Sites\Git;

use App\Models\Server;
use App\Models\ServerSite;
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
     * Maximum time the job may run (10 minutes).
     */
    public int $timeout = 600;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Time to wait before retrying (exponential backoff).
     */
    public function backoff(): array
    {
        return [30, 60, 120]; // 30s, 1m, 2m
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Server $server,
        protected ServerSite $site,
        protected array $configuration
    ) {
        $this->onQueue('provisioning');
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

            // Execute installation - the installer handles all logic, validation, and database tracking
            $installer->execute($this->site, $this->configuration);

            Log::info("Git repository installation completed for site #{$this->site->id} on server #{$this->server->id}");
        } catch (\Exception $e) {
            Log::error("Git repository installation failed for site #{$this->site->id} on server #{$this->server->id}", [
                'repository' => $this->configuration['repository'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
