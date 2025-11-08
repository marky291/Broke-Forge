<?php

namespace App\Packages\Services\Sites\Framework\WordPress;

use App\Models\ServerSite;
use App\Packages\Core\Base\PackageInstaller;
use App\Packages\Core\Base\SitePackage;

/**
 * WordPress Installation Service
 *
 * Installs WordPress on a remote server via SSH commands.
 */
class WordPressInstaller extends PackageInstaller implements SitePackage
{
    /**
     * Execute WordPress installation for a site.
     */
    public function execute(ServerSite $site): void
    {
        $this->install($this->commands($site));
    }

    /**
     * Build array of commands to install WordPress.
     */
    protected function commands(ServerSite $site): array
    {
        $domain = $site->domain;
        $timestamp = now()->format('dmY-His');

        // Site root is where deployments are stored
        $siteRoot = "/home/brokeforge/deployments/{$domain}";

        // Deployment directory with timestamp
        $deploymentPath = "{$siteRoot}/{$timestamp}";

        // Site symlink is what nginx points to
        $siteSymlink = "/home/brokeforge/{$domain}";

        // Get app user
        $brokeforgeCredential = $this->server->credentials()
            ->where('user', 'brokeforge')
            ->first();
        $appUser = $brokeforgeCredential?->getUsername() ?: 'brokeforge';

        // Generate wp-config.php content
        $configGenerator = new WordPressConfigGenerator;
        $wpConfigContent = $configGenerator->generate($site);

        // Escape for heredoc
        $wpConfigContent = str_replace('$', '\$', $wpConfigContent);

        return [
            // Download WordPress
            'cd /tmp && wget -q https://wordpress.org/latest.tar.gz',

            // Extract WordPress
            'cd /tmp && tar -xzf latest.tar.gz',

            // Create deployment directory
            "mkdir -p {$deploymentPath}",

            // Move WordPress files to deployment directory
            "mv /tmp/wordpress/* {$deploymentPath}/",

            // Clean up
            'rm -rf /tmp/wordpress /tmp/latest.tar.gz',

            // Generate wp-config.php
            "cat > {$deploymentPath}/wp-config.php << 'WP_CONFIG_EOF'\n{$wpConfigContent}\nWP_CONFIG_EOF",

            // Set permissions
            "chown -R {$appUser}:{$appUser} {$deploymentPath}",
            "chmod -R 755 {$deploymentPath}",

            // Create symlink to active deployment
            "ln -sfn {$deploymentPath} {$siteSymlink}",

            // Update database status
            function () use ($site) {
                $site->update([
                    'status' => 'active',
                    'installed_at' => now(),
                ]);
            },
        ];
    }
}
