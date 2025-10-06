<?php

namespace App\Packages\Services\Database\MariaDB;

use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * MariaDB Removal Job
 *
 * Handles queued MariaDB removal from remote servers
 */
class MariaDbRemoverJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600;

    public function __construct(
        public Server $server
    ) {}

    public function handle(): void
    {
        Log::info("Starting MariaDB removal for server #{$this->server->id}");

        $remover = new MariaDbRemover($this->server);
        // Execute removal - base class handles failure marking automatically
        $remover->execute();

        Log::info("MariaDB removal completed for server #{$this->server->id}");
    }
}
