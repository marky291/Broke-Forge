<?php

namespace App\Packages\Services\Sites\Deployment;

use App\Enums\TaskStatus;
use App\Models\ServerDeployment;
use App\Models\ServerSite;
use App\Packages\Core\Base\PackageInstaller;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Site Git Deployment Installer
 *
 * Executes deployment scripts for Git-enabled sites and tracks deployment history
 */
class SiteGitDeploymentInstaller extends PackageInstaller implements \App\Packages\Core\Base\SitePackage
{
    protected ?string $deploymentOutput = null;

    protected ?string $deploymentError = null;

    protected ?string $commitSha = null;

    /**
     * Execute deployment for a site
     */
    public function execute(ServerSite $site, ServerDeployment $deployment): void
    {
        // Mark deployment as running
        $deployment->update([
            'status' => TaskStatus::Updating,
            'started_at' => now(),
        ]);

        $documentRoot = $site->document_root;
        if (! $documentRoot) {
            throw new RuntimeException('Site document root not configured.');
        }

        $start = (int) (microtime(true) * 1000);

        try {
            $this->install($this->commands($documentRoot, $deployment->deployment_script, $site, $deployment));

            $duration = (int) (microtime(true) * 1000) - $start;

            // Update deployment with success
            $deployment->update([
                'status' => 'success',
                'output' => $this->deploymentOutput ?? '',
                'error_output' => null, // Don't show stderr for successful deployments
                'exit_code' => 0,
                'commit_sha' => $this->commitSha,
                'branch' => $site->getGitConfiguration()['branch'] ?? null,
                'duration_ms' => $duration,
                'completed_at' => now(),
            ]);

            // Update site's last deployed info
            $site->update([
                'last_deployment_sha' => $this->commitSha,
                'last_deployed_at' => now(),
            ]);

            Log::info("Deployment completed successfully for site #{$site->id}", [
                'deployment_id' => $deployment->id,
                'commit_sha' => $this->commitSha,
                'duration_ms' => $duration,
            ]);
        } catch (\Exception $e) {
            $duration = (int) (microtime(true) * 1000) - $start;

            // Update deployment with failure
            $deployment->update([
                'status' => 'failed',
                'output' => $this->deploymentOutput ?? '',
                'error_output' => $this->deploymentError ?: $e->getMessage(),
                'exit_code' => 1,
                'duration_ms' => $duration,
                'completed_at' => now(),
            ]);

            Log::error("Deployment failed for site #{$site->id}", [
                'deployment_id' => $deployment->id,
                'error' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);
        }
    }

    /**
     * Generate SSH commands for deployment execution
     */
    protected function commands(string $documentRoot, string $deploymentScript, ServerSite $site, ServerDeployment $deployment): array
    {
        $documentRoot = rtrim($documentRoot, '/');
        // Git repository is cloned to the site directory (parent of document root)
        $siteDirectory = dirname($documentRoot);

        return [

            // Execute deployment script and capture output
            function () use ($siteDirectory, $deploymentScript) {
                // Add safe.directory config to allow brokeforge user to access the repository
                $remoteCommand = sprintf(
                    'git config --global --add safe.directory %s && cd %s && %s',
                    escapeshellarg($siteDirectory),
                    escapeshellarg($siteDirectory),
                    $deploymentScript
                );

                $process = $this->server->ssh('brokeforge')
                    ->setTimeout(300) // 5 minute timeout for deployments
                    ->execute($remoteCommand);

                // Store output for deployment record
                $this->deploymentOutput = rtrim($process->getOutput());
                $this->deploymentError = rtrim($process->getErrorOutput());

                if (! $process->isSuccessful()) {
                    Log::warning('Deployment script exited with non-zero code.', [
                        'server_id' => $this->server->id,
                        'exit_code' => $process->getExitCode(),
                        'stderr' => $this->deploymentError,
                    ]);

                    throw new RuntimeException("Deployment failed with exit code {$process->getExitCode()}");
                }
            },

            // Capture current Git commit SHA
            function () use ($siteDirectory) {
                $remoteCommand = sprintf(
                    'git config --global --add safe.directory %s && cd %s && git rev-parse HEAD 2>/dev/null || echo ""',
                    escapeshellarg($siteDirectory),
                    escapeshellarg($siteDirectory)
                );

                $process = $this->server->ssh('brokeforge')
                    ->execute($remoteCommand);

                $this->commitSha = trim($process->getOutput()) ?: null;
            },

        ];
    }

    public function milestones(): Milestones
    {
        return new SiteGitDeploymentInstallerMilestones;
    }
}
