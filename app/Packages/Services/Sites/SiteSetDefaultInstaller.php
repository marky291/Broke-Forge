<?php

namespace App\Packages\Services\Sites;

use App\Models\ServerSite;
use App\Packages\Core\Base\PackageInstaller;

/**
 * Installer for setting a site as the default site.
 * Swaps the /home/brokeforge/default symlink to point to the specified site.
 */
class SiteSetDefaultInstaller extends PackageInstaller
{
    /**
     * Execute the default site switch.
     */
    public function execute(ServerSite $site): void
    {
        $this->install($this->commands($site));
    }

    protected function commands(ServerSite $site): array
    {
        // Get app user
        $appUser = config('app.ssh_user', str_replace(' ', '', strtolower(config('app.name'))));

        // Determine source path for symlink
        $sourcePath = $this->determineSourcePath($site);

        return [
            // Swap symlink atomically (relative path for proper symlink resolution)
            "ln -sfn {$sourcePath} /home/{$appUser}/default",

            // Reload PHP-FPM to apply changes
            "sudo service php{$site->php_version}-fpm reload",

            // Verify symlink was created successfully
            "readlink /home/{$appUser}/default",
        ];
    }

    /**
     * Determine the source path for the default site symlink.
     * Returns relative path from /home/brokeforge/ directory.
     */
    private function determineSourcePath(ServerSite $site): string
    {
        // For the original provisioning default site
        if ($site->domain === 'default' && isset($site->configuration['default_deployment_path'])) {
            $deploymentPath = $site->configuration['default_deployment_path'];

            // Extract relative path (e.g., "deployments/default/07112025-120000")
            return str_replace('/home/brokeforge/', '', $deploymentPath);
        }

        // For regular sites with active deployment
        if ($site->activeDeployment && $site->activeDeployment->deployment_path) {
            $deploymentPath = $site->activeDeployment->deployment_path;

            // Extract relative path (e.g., "deployments/example.com/07112025-120000")
            return str_replace('/home/brokeforge/', '', $deploymentPath);
        }

        // Fallback: use site symlink (which points to latest deployment for git-based sites)
        return $site->domain;
    }
}
