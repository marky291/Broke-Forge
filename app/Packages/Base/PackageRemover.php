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
     * Mark the resource being removed as failed
     *
     * Override in concrete removers to update resource status (ServerPhp, ServerDatabase, etc.)
     * Default implementation is no-op for removers without specific resource status tracking
     */
    protected function markResourceAsFailed(string $errorMessage): void
    {
        // Default: no-op
        // Override in concrete removers to mark resource status as failed
    }

    /**
     * Send removal commands to the remote server
     */
    protected function remove(array $commands): void
    {
        try {
            $this->sendCommandsToRemote($commands);
        } catch (\Exception $e) {
            $this->markResourceAsFailed($e->getMessage());
            throw $e;
        }
    }
}
