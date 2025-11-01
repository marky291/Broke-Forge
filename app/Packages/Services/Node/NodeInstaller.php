<?php

namespace App\Packages\Services\Node;

use App\Enums\TaskStatus;
use App\Models\ServerNode;
use App\Packages\Core\Base\PackageInstaller;
use App\Packages\Core\Base\ServerPackage;
use App\Packages\Enums\NodeVersion;

/**
 * Node.js Installation Class
 *
 * Handles installation of Node.js with progress tracking
 * Also installs Composer if this is the first Node installation
 */
class NodeInstaller extends PackageInstaller implements ServerPackage
{
    /**
     * The Node version being installed
     */
    private NodeVersion $installingVersion;

    /**
     * Whether this is the first Node installation (should install Composer)
     */
    private bool $shouldInstallComposer = false;

    /**
     * Mark Node installation as failed in database
     */
    protected function markResourceAsFailed(string $errorMessage): void
    {
        ServerNode::where('server_id', $this->server->id)
            ->where('version', $this->installingVersion->value)
            ->update(['status' => TaskStatus::Failed, 'error_log' => $errorMessage]);
    }

    /**
     * Execute Node.js installation with the specified version
     *
     * Note: ServerNode record should already exist with 'pending' status
     * (created by caller before dispatching NodeInstallerJob)
     */
    public function execute(NodeVersion $nodeVersion, bool $installComposer = false): void
    {
        // Store the version for this installation
        $this->installingVersion = $nodeVersion;
        $this->shouldInstallComposer = $installComposer;

        $this->install($this->commands($nodeVersion, $installComposer));
    }

    protected function commands(NodeVersion $nodeVersion, bool $installComposer): array
    {
        $commands = [
            // Update package lists and install prerequisites
            'DEBIAN_FRONTEND=noninteractive apt-get update -y',
            'DEBIAN_FRONTEND=noninteractive apt-get install -y ca-certificates curl gnupg',

            // Remove existing Node.js installations to prevent conflicts
            'apt-get remove -y nodejs || true',

            // Add NodeSource repository for the specific version
            "curl -fsSL https://deb.nodesource.com/setup_{$nodeVersion->value}.x | bash -",

            // Install Node.js
            'DEBIAN_FRONTEND=noninteractive apt-get install -y nodejs',

            // Verify Node.js installation
            'node --version',
            'npm --version',
        ];

        // Install Composer if this is the first Node installation
        if ($installComposer) {
            $commands = array_merge($commands, $this->composerCommands());
        }

        return $commands;
    }

    /**
     * Get Composer installation commands
     */
    private function composerCommands(): array
    {
        return [
            // Install prerequisites for Composer
            'DEBIAN_FRONTEND=noninteractive apt-get install -y php-cli php-zip unzip',

            // Download and install Composer
            'curl -sS https://getcomposer.org/installer | php',
            'mv composer.phar /usr/local/bin/composer',
            'chmod +x /usr/local/bin/composer',

            // Verify Composer installation
            'composer --version',
        ];
    }
}
