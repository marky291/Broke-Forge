<?php

namespace App\Packages\Services\Database\MySQL;

use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * MySQL Database Removal Job
 *
 * Handles queued MySQL database removal from remote servers
 */
class MySqlRemoverJob implements ShouldQueue
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
        Log::info("Starting MySQL database removal for server #{$this->server->id}");

        // Create remover instance
        $remover = new MySqlRemover($this->server);

        // Execute removal - base class handles failure marking automatically
        $remover->execute();

        Log::info("MySQL database removal completed for server #{$this->server->id}");
    }
}
