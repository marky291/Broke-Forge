<?php

namespace App\Packages\Services\Sites\Git;

use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Enums\GitStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Git Repository Installation Job
 *
 * Handles queued Git repository installation on sites
 */
class GitRepositoryInstallerJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     */
    public $timeout = 600;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

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
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("package:action:{$this->server->id}"))->shared()
                ->releaseAfter(15)
                ->expireAfter(900),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        set_time_limit(0);

        Log::info("Starting Git repository installation for site #{$this->site->id} on server #{$this->server->id}", [
            'repository' => $this->configuration['repository'] ?? 'unknown',
            'branch' => $this->configuration['branch'] ?? 'unknown',
        ]);

        try {
            // ✅ UPDATE: pending/null → installing
            // Model event broadcasts automatically via Reverb
            $this->site->update(['git_status' => GitStatus::Installing]);

            // Create installer instance
            $installer = new GitRepositoryInstaller($this->server);

            // Set the site for event tracking
            $installer->setSite($this->site);

            // Execute installation - the installer handles all logic, validation, and database tracking
            $installer->execute($this->site, $this->configuration);

            // ✅ UPDATE: installing → installed
            // Model event broadcasts automatically via Reverb
            // Update git status to installed and site status to active on success
            $this->site->update([
                'git_status' => GitStatus::Installed,
                'git_installed_at' => now(),
                'status' => 'active',
                'provisioned_at' => now(),
            ]);

            Log::info("Git repository installation completed for site #{$this->site->id} on server #{$this->server->id}");
        } catch (\Exception $e) {
            // ✅ UPDATE: installing → failed
            // Model event broadcasts automatically via Reverb
            $this->site->update([
                'git_status' => GitStatus::Failed,
                'error_log' => $e->getMessage(),
            ]);

            Log::error("Git repository installation failed for site #{$this->site->id} on server #{$this->server->id}", [
                'repository' => $this->configuration['repository'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $site = ServerSite::find($this->site->id);

        if ($site) {
            $site->update([
                'git_status' => GitStatus::Failed,
                'error_log' => $exception->getMessage(),
            ]);
        }

        Log::error('GitRepositoryInstallerJob failed', [
            'site_id' => $this->site->id,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
