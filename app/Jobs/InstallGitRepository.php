<?php

namespace App\Jobs;

use App\Enums\GitStatus;
use App\Models\Server;
use App\Models\ServerSite;
use App\Provision\Sites\GitProvision;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class InstallGitRepository implements ShouldQueue
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
     * Execute the job to install Git repository on the site.
     */
    public function handle(): void
    {
        // Validate the site can install Git
        if (! $this->site->canInstallGitRepository()) {
            Log::warning('Git installation skipped - invalid state', [
                'site_id' => $this->site->id,
                'current_status' => $this->site->git_status?->value,
            ]);

            return;
        }

        // Mark Git installation as in progress
        $this->updateGitStatus(GitStatus::Installing);

        try {
            // Create and run the Git provisioner
            $provisioner = new GitProvision($this->server);
            $provisioner->forSite($this->site)
                ->setConfiguration($this->configuration)
                ->provision();

            // Mark Git installation as complete and store configuration
            $this->site->update([
                'git_status' => GitStatus::Installed,
                'git_installed_at' => now(),
                'configuration' => array_merge(
                    $this->site->configuration ?? [],
                    ['git_repository' => $this->configuration]
                ),
            ]);

            $this->logSuccess();
        } catch (Throwable $exception) {
            $this->handleFailure($exception);
            throw $exception; // Re-throw for retry mechanism
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(Throwable $exception): void
    {
        // Ensure status is marked as failed on final failure
        $this->updateGitStatus(GitStatus::Failed);

        Log::error('Git repository installation permanently failed', [
            'server_id' => $this->server->id,
            'site_id' => $this->site->id,
            'repository' => $this->configuration['repository'] ?? 'unknown',
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Update the Git status for the site.
     */
    protected function updateGitStatus(GitStatus $status): void
    {
        $this->site->update(['git_status' => $status]);
    }

    /**
     * Log successful installation.
     */
    protected function logSuccess(): void
    {
        Log::info('Git repository installed successfully', [
            'server_id' => $this->server->id,
            'site_id' => $this->site->id,
            'repository' => $this->configuration['repository'] ?? 'unknown',
            'branch' => $this->configuration['branch'] ?? 'unknown',
            'attempts' => $this->attempts(),
        ]);
    }

    /**
     * Handle installation failure.
     */
    protected function handleFailure(Throwable $exception): void
    {
        // Update status to failed but keep the configuration
        $this->site->update([
            'git_status' => GitStatus::Failed,
            'configuration' => array_merge(
                $this->site->configuration ?? [],
                ['git_repository' => $this->configuration]
            ),
        ]);

        Log::error('Git repository installation failed', [
            'server_id' => $this->server->id,
            'site_id' => $this->site->id,
            'repository' => $this->configuration['repository'] ?? 'unknown',
            'attempt' => $this->attempts(),
            'max_tries' => $this->tries,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Determine if the job should be retried.
     */
    public function shouldRetry(Throwable $exception): bool
    {
        // Don't retry on validation errors
        if ($exception instanceof \InvalidArgumentException) {
            return false;
        }

        // Don't retry if server is permanently disconnected
        if (! $this->server->exists || $this->server->isDeleted()) {
            return false;
        }

        return $this->attempts() < $this->tries;
    }
}
