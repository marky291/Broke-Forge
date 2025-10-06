<?php

namespace App\Packages\Base;

use App\Models\Server;
use App\Packages\Contracts\Installer;

/**
 * Base class for package installation services
 */
abstract class PackageInstaller extends PackageManager implements Installer
{
    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    /**
     * Execute the installation process
     *
     * Each package can define its own execute method signature
     * based on its specific requirements
     */

    /**
     * Mark the resource being installed as failed
     *
     * Override in concrete installers to update resource status (ServerPhp, ServerDatabase, etc.)
     * Default implementation is no-op for installers without specific resource status tracking
     */
    protected function markResourceAsFailed(string $errorMessage): void
    {
        // Default: no-op
        // Override in concrete installers to mark resource status as failed
    }

    /**
     * Send installation commands to the remote server
     */
    protected function install(array $commands): void
    {
        try {
            $this->sendCommandsToRemote($commands);
        } catch (\Exception $e) {
            $this->markResourceAsFailed($e->getMessage());
            throw $e;
        }
    }
}
