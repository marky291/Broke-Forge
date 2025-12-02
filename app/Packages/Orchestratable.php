<?php

namespace App\Packages;

use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Abstract Orchestrator Job Base Class
 *
 * Base class for orchestrator jobs that coordinate multiple sub-installer jobs.
 * Uses a separate lock key (orchestrator:{server_id}) to prevent concurrent
 * orchestrators while allowing child jobs to use their own locks (package:action:{server_id}).
 *
 * This separation prevents lock contention when orchestrators dispatch child jobs synchronously.
 *
 * Examples: NginxInstallerJob, SiteInstallerJob
 *
 * Child classes must implement their own handle() and failed() methods.
 */
abstract class Orchestratable implements ShouldQueue
{
    use Queueable;

    /**
     * The server instance for this operation.
     */
    public Server $server;

    /**
     * Get the middleware the job should pass through.
     *
     * Uses a separate lock key (orchestrator:{server_id}) to prevent concurrent
     * orchestrators without conflicting with child job locks (package:action:{server_id}).
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("orchestrator:{$this->server->id}"))->shared()
                ->releaseAfter(15)
                ->expireAfter(900),
        ];
    }
}
