<?php

namespace App\Packages\Services\Supervisor\Task;

use App\Models\Server;
use App\Models\ServerSupervisorTask;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Supervisor Task Removal Job
 *
 * Handles queued task removal from remote servers with real-time status updates
 */
class SupervisorTaskRemoverJob implements ShouldQueue
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
        public int $taskId  // ← Receives task ID, NOT full model
    ) {}

    public function handle(): void
    {
        set_time_limit(0);

        // Load the task from database
        $task = ServerSupervisorTask::findOrFail($this->taskId);

        // Store original status for rollback on failure
        $originalStatus = $task->status;

        Log::info('Starting supervisor task removal', [
            'task_id' => $task->id,
            'server_id' => $this->server->id,
            'task_name' => $task->name,
        ]);

        try {
            // Create remover instance
            $remover = new SupervisorTaskRemover($this->server, $task);

            // Execute removal on remote server
            $remover->execute();

            Log::info('Supervisor task removal completed successfully', [
                'task_id' => $task->id,
                'server_id' => $this->server->id,
            ]);

            // ✅ DELETE task from database (model's deleted event broadcasts automatically)
            $task->delete();

        } catch (Exception $e) {
            // ✅ ROLLBACK: Restore original status on failure (allows retry)
            $task->update(['status' => $originalStatus]);
            // Model event broadcasts automatically via Reverb

            Log::error('Supervisor task removal failed', [
                'task_id' => $task->id,
                'server_id' => $this->server->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
