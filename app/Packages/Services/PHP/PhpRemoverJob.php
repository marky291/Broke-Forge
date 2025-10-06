<?php

namespace App\Packages\Services\PHP;

use App\Models\Server;
use App\Packages\Enums\PhpVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * PHP Removal Job
 *
 * Handles queued PHP removal on remote servers
 */
class PhpRemoverJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600;

    public function __construct(
        public Server $server,
        public PhpVersion $phpVersion,
        public int $phpId
    ) {}

    public function handle(): void
    {
        // Set no time limit for long-running removal process
        set_time_limit(0);

        Log::info("Starting PHP {$this->phpVersion->value} removal for server #{$this->server->id}");

        // Create remover instance
        $remover = new PhpRemover($this->server);

        // Execute removal - base class handles failure marking automatically
        $remover->execute($this->phpVersion, $this->phpId);

        Log::info("PHP {$this->phpVersion->value} removal completed for server #{$this->server->id}");
    }
}
