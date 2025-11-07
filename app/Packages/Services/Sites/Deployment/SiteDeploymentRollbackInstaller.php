<?php

namespace App\Packages\Services\Sites\Deployment;

use App\Models\ServerDeployment;
use App\Models\ServerSite;
use App\Packages\Core\Base\PackageInstaller;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Site Deployment Rollback Installer
 *
 * Rolls back a site to a previous deployment by updating the 'current' symlink
 */
class SiteDeploymentRollbackInstaller extends PackageInstaller implements \App\Packages\Core\Base\SitePackage
{
    /**
     * Execute rollback to a previous deployment
     */
    public function execute(ServerSite $site, ServerDeployment $targetDeployment): void
    {
        if (! $targetDeployment->canRollback()) {
            throw new RuntimeException('Cannot rollback to this deployment - deployment path not found or deployment failed.');
        }

        if ($site->active_deployment_id === $targetDeployment->id) {
            throw new RuntimeException('This deployment is already active.');
        }

        Log::info("Starting rollback for site #{$site->id}", [
            'site_id' => $site->id,
            'current_deployment_id' => $site->active_deployment_id,
            'target_deployment_id' => $targetDeployment->id,
        ]);

        $this->install($this->commands($site, $targetDeployment));

        Log::info("Rollback completed successfully for site #{$site->id}", [
            'deployment_id' => $targetDeployment->id,
        ]);
    }

    /**
     * Generate SSH commands for rollback execution
     */
    protected function commands(ServerSite $site, ServerDeployment $targetDeployment): array
    {
        $siteSymlink = $site->getSiteSymlink();
        $deploymentPath = $targetDeployment->deployment_path;
        $deploymentId = $targetDeployment->id;

        // Extract just the deployment directory name from the full path
        $deploymentDirName = basename($deploymentPath);

        return [
            // Verify deployment directory exists
            function () use ($deploymentPath) {
                $checkCommand = sprintf('test -d %s', escapeshellarg($deploymentPath));

                $process = $this->server->ssh('brokeforge')->execute($checkCommand);

                if (! $process->isSuccessful()) {
                    Log::error('Deployment directory not found', [
                        'deployment_path' => $deploymentPath,
                    ]);

                    throw new RuntimeException("Deployment directory not found: {$deploymentPath}");
                }
            },

            // Atomically swap the site symlink to the target deployment
            function () use ($site, $siteSymlink, $deploymentDirName) {
                $symlinkCommand = sprintf(
                    'ln -sfn deployments/%s/%s %s',
                    escapeshellarg($site->domain),
                    $deploymentDirName,
                    escapeshellarg($siteSymlink)
                );

                $process = $this->server->ssh('brokeforge')->execute($symlinkCommand);

                if (! $process->isSuccessful()) {
                    Log::error('Failed to update site symlink', [
                        'site_symlink' => $siteSymlink,
                        'deployment_dir' => $deploymentDirName,
                    ]);

                    throw new RuntimeException('Failed to update site symlink');
                }

                Log::info('Site symlink updated to target deployment', [
                    'deployment_id' => $deploymentDirName,
                ]);
            },

            // Reload PHP-FPM to pick up any changes
            function () use ($site) {
                $reloadCommand = sprintf(
                    'sudo service php%s-fpm reload',
                    $site->php_version
                );

                $process = $this->server->ssh('brokeforge')->execute($reloadCommand);

                if (! $process->isSuccessful()) {
                    Log::warning('Failed to reload PHP-FPM during rollback', [
                        'site_id' => $site->id,
                    ]);
                    // Don't throw - this is not critical
                }
            },

            // Update site's active deployment
            function () use ($site, $targetDeployment) {
                $site->update([
                    'active_deployment_id' => $targetDeployment->id,
                    'last_deployed_at' => now(),
                ]);

                Log::info('Site active deployment updated', [
                    'site_id' => $site->id,
                    'active_deployment_id' => $targetDeployment->id,
                ]);
            },
        ];
    }
}
