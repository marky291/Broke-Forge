<?php

namespace App\Packages\Services\Sites\Framework\WordPress;

use App\Enums\TaskStatus;
use App\Models\ServerSite;
use App\Packages\Services\Sites\Framework\BaseFrameworkInstaller;

/**
 * WordPress Installation Job
 *
 * Handles queued WordPress installation on remote servers with real-time status updates.
 */
class WordPressInstallerJob extends BaseFrameworkInstaller
{
    /**
     * Get framework-specific installation steps for WordPress.
     */
    protected function getFrameworkSteps(ServerSite $site): array
    {
        return [
            ['name' => 'Initializing deployment', 'description' => 'Creating deployment directories and structure'],
            ['name' => 'Configuring Nginx', 'description' => 'Setting up web server configuration'],
            ['name' => 'Downloading WordPress', 'description' => 'Fetching latest WordPress version'],
            ['name' => 'Extracting WordPress files', 'description' => 'Unpacking WordPress to deployment directory'],
            ['name' => 'Generating wp-config.php', 'description' => 'Creating WordPress configuration file'],
            ['name' => 'Configuring database', 'description' => 'Setting up database connection'],
            ['name' => 'Finalizing installation', 'description' => 'Setting permissions and activating site'],
        ];
    }

    /**
     * Execute WordPress-specific installation logic.
     */
    protected function installFramework(ServerSite $site): void
    {
        $deploymentPath = '';

        // Step 1: Initialize deployment
        $this->updateInstallationStep($site, 1, TaskStatus::Installing);
        $commands = $this->createDeploymentDirectory($site, $deploymentPath);
        $this->executeCommands($commands);
        $this->updateInstallationStep($site, 1, TaskStatus::Success);

        // Update document_root for WordPress before configuring Nginx
        // WordPress doesn't use a separate /public directory - all files go in the root
        $siteSymlink = "/home/brokeforge/{$site->domain}";
        $site->update(['document_root' => $siteSymlink]);

        // Step 2: Configure Nginx
        $this->updateInstallationStep($site, 2, TaskStatus::Installing);
        $commands = $this->configureNginx($site);
        $this->executeCommands($commands);
        $this->updateInstallationStep($site, 2, TaskStatus::Success);

        // Step 3: Download WordPress
        $this->updateInstallationStep($site, 3, TaskStatus::Installing);
        $this->executeCommands([
            'cd /tmp && wget -q https://wordpress.org/latest.tar.gz',
        ]);
        $this->updateInstallationStep($site, 3, TaskStatus::Success);

        // Step 4: Extract WordPress
        $this->updateInstallationStep($site, 4, TaskStatus::Installing);
        $this->executeCommands([
            'cd /tmp && tar -xzf latest.tar.gz',
            "mv /tmp/wordpress/* {$deploymentPath}/",
            'rm -rf /tmp/wordpress /tmp/latest.tar.gz',
        ]);
        $this->updateInstallationStep($site, 4, TaskStatus::Success);

        // Step 5: Generate wp-config.php
        $this->updateInstallationStep($site, 5, TaskStatus::Installing);
        $configGenerator = new WordPressConfigGenerator;
        $wpConfigContent = $configGenerator->generate($site);
        $wpConfigContent = str_replace('$', '\$', $wpConfigContent);

        $this->executeCommands([
            "cat > {$deploymentPath}/wp-config.php << 'WP_CONFIG_EOF'\n{$wpConfigContent}\nWP_CONFIG_EOF",
        ]);
        $this->updateInstallationStep($site, 5, TaskStatus::Success);

        // Step 6: Configure database (set permissions)
        $this->updateInstallationStep($site, 6, TaskStatus::Installing);
        $brokeforgeCredential = $this->server->credentials()
            ->where('user', 'brokeforge')
            ->first();
        $appUser = $brokeforgeCredential?->getUsername() ?: 'brokeforge';

        $this->executeCommands([
            "chown -R {$appUser}:{$appUser} {$deploymentPath}",
            "chmod -R 755 {$deploymentPath}",
        ]);
        $this->updateInstallationStep($site, 6, TaskStatus::Success);

        // Step 7: Finalize installation
        $this->updateInstallationStep($site, 7, TaskStatus::Installing);
        $commands = $this->createSymlink($site, $deploymentPath);
        $this->executeCommands($commands);
        $this->updateInstallationStep($site, 7, TaskStatus::Success);
    }

    /**
     * Get human-readable operation name for logging.
     */
    protected function getOperationName(): string
    {
        return 'WordPress installation';
    }
}
