<?php

namespace App\Provision;

use App\Models\Server;

abstract class InstallableService extends Serviceable
{
    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    protected function install(array $commands): void
    {
        $this->sendCommandsToRemote($commands);
    }
}
