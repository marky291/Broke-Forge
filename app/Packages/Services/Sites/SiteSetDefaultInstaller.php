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

        // Generate new Nginx default config with correct public directory
        $nginxConfig = $this->generateNginxConfig($site);
        $nginxConfigPath = '/etc/nginx/sites-available/default';

        return [
            // Swap symlink atomically (relative path for proper symlink resolution)
            "ln -sfn {$sourcePath} /home/{$appUser}/default",

            // Update Nginx default config with framework-specific public directory
            "cat > {$nginxConfigPath} << 'NGINX_CONFIG_EOF'\n{$nginxConfig}\nNGINX_CONFIG_EOF",

            // Reload PHP-FPM to apply changes
            "sudo service php{$site->php_version}-fpm reload",

            // Reload Nginx to apply config changes
            'sudo systemctl reload nginx',

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

    /**
     * Generate Nginx default config with framework-specific public directory.
     */
    private function generateNginxConfig(ServerSite $site): string
    {
        $appUser = config('app.ssh_user', str_replace(' ', '', strtolower(config('app.name'))));

        // Load framework relationship if not already loaded
        if (! $site->relationLoaded('siteFramework')) {
            $site->load('siteFramework');
        }

        // Get framework-specific public directory (empty string for WordPress, '/public' for others)
        $publicDirectory = $site->siteFramework->getPublicDirectory();

        return view('nginx.default', [
            'appUser' => $appUser,
            'phpVersion' => $site->php_version,
            'publicDirectory' => $publicDirectory,
        ])->render();
    }
}
