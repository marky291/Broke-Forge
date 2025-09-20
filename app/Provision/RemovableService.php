<?php

namespace App\Provision;

use App\Models\Server;

abstract class RemovableService extends Serviceable
{
    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    protected function remove(array $commands): void
    {
        $this->sendCommandsToRemote($commands);
    }
}
