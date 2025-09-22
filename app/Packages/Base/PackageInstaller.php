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
     * Send installation commands to the remote server
     */
    protected function install(array $commands): void
    {
        $this->sendCommandsToRemote($commands);
    }
}
