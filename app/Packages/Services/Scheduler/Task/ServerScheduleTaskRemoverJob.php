<?php

namespace App\Packages\Services\Scheduler\Task;

use App\Models\Server;
use App\Models\ServerScheduledTask;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Server Scheduled Task Removal Job
 *
 * Handles queued task removal from remote servers
 */
class ServerScheduleTaskRemoverJob implements ShouldQueue
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
        public ServerScheduledTask $task
    ) {}

    public function handle(): void
    {
        set_time_limit(0);

        Log::info("Starting scheduled task removal for task #{$this->task->id} on server #{$this->server->id}");

        try {
            // Create remover instance
            $remover = new ServerScheduleTaskRemover($this->server, $this->task);

            // Execute removal
            $remover->execute();

            Log::info("Scheduled task removal completed for task #{$this->task->id} on server #{$this->server->id}");

        } catch (Exception $e) {
            Log::error("Scheduled task removal failed for task #{$this->task->id} on server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
