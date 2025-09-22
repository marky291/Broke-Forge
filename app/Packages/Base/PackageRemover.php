<?php

namespace App\Packages\Base;

use App\Models\Server;
use App\Packages\Contracts\Remover;

/**
 * Base class for package removal services
 */
abstract class PackageRemover extends PackageManager implements Remover
{
    public function __construct(Server $server)
    {
        $this->server = $server;
    }


    /**
     * Send removal commands to the remote server
     */
    protected function remove(array $commands): void
    {
        $this->sendCommandsToRemote($commands);
    }
}
