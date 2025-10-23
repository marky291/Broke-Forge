<?php

namespace App\Packages\Services\Sites\Deployment;

use App\Enums\DeploymentStatus;
use App\Models\Server;
use App\Models\ServerDeployment;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Site Git Deployment Job
 *
 * Handles queued deployment execution for Git-enabled sites with real-time status updates
 */
class SiteGitDeploymentJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 0;

    /**
     * The number of exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    public function __construct(
        public Server $server,
        public int $deploymentId  // ← Receives deployment record ID
    ) {}

    public function handle(): void
    {
        // Set no time limit for long-running deployment process
        set_time_limit(0);

        // Load the deployment record from database
        $deployment = ServerDeployment::findOrFail($this->deploymentId);
        $site = $deployment->site;

        Log::info('Starting deployment', [
            'deployment_id' => $deployment->id,
            'server_id' => $this->server->id,
            'site_id' => $site->id,
        ]);

        try {
            // ✅ UPDATE: pending → running
            $deployment->update([
                'status' => DeploymentStatus::Running,
                'started_at' => now(),
            ]);
            // Model event broadcasts automatically via Reverb

            // Create installer instance
            $installer = new SiteGitDeploymentInstaller($this->server);
            $installer->setSite($site);

            // Execute deployment
            $installer->execute($site, $deployment);

            // ✅ UPDATE: running → success
            $deployment->update([
                'status' => DeploymentStatus::Success,
                'completed_at' => now(),
            ]);
            // Model event broadcasts automatically via Reverb

            Log::info('Deployment completed successfully', [
                'deployment_id' => $deployment->id,
                'server_id' => $this->server->id,
                'site_id' => $site->id,
            ]);

        } catch (Exception $e) {
            // ✅ UPDATE: any → failed
            $deployment->update([
                'status' => DeploymentStatus::Failed,
                'completed_at' => now(),
                'error_output' => $e->getMessage(),
            ]);
            // Model event broadcasts automatically via Reverb

            Log::error('Deployment failed', [
                'deployment_id' => $deployment->id,
                'server_id' => $this->server->id,
                'site_id' => $site->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;  // Re-throw for Laravel's retry mechanism
        }
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

    public function failed(\Throwable $exception): void
    {
        $deployment = ServerDeployment::find($this->deploymentId);

        if ($deployment) {
            $deployment->update([
                'status' => DeploymentStatus::Failed,
                'completed_at' => now(),
                'error_output' => $exception->getMessage(),
            ]);
        }

        Log::error('SiteGitDeploymentJob failed', [
            'deployment_id' => $this->deploymentId,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
