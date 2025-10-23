<?php

namespace App\Packages\Services\Scheduler\Task;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerScheduledTask;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Server Scheduled Task Removal Job
 *
 * Handles queued task removal from remote servers with real-time status updates
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

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 0;

    /**
     * The number of exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    public function __construct(
        public Server $server,
        public int $taskId  // ← Receives task ID, NOT full model
    ) {}

    public function handle(): void
    {
        set_time_limit(0);

        // Load the task from database
        $task = ServerScheduledTask::findOrFail($this->taskId);

        Log::info('Starting scheduled task removal', [
            'task_id' => $task->id,
            'server_id' => $this->server->id,
        ]);

        try {
            // ✅ UPDATE: active → removing
            // Model event broadcasts automatically via Reverb
            $task->update(['status' => 'removing']);

            // Create remover instance
            $remover = new ServerScheduleTaskRemover($this->server, $task);

            // Execute removal on remote server
            $remover->execute();

            Log::info('Scheduled task removal completed successfully', [
                'task_id' => $task->id,
                'server_id' => $this->server->id,
            ]);

            // ✅ DELETE task from database (model's deleted event broadcasts automatically)
            $task->delete();

        } catch (Exception $e) {
            // ✅ Mark as failed
            $task->update([
                'status' => TaskStatus::Failed,
                'error_log' => $e->getMessage(),
            ]);

            Log::error('Scheduled task removal failed', [
                'task_id' => $task->id,
                'server_id' => $this->server->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("package:action:{$this->server->id}"))->shared()
                ->releaseAfter(15)
                ->expireAfter(900),
        ];
    }

    public function failed(\Throwable $exception): void
    {
        $task = ServerScheduledTask::find($this->taskId);

        if ($task) {
            $task->update([
                'status' => TaskStatus::Failed,
                'error_log' => $exception->getMessage(),
            ]);
        }

        Log::error('ServerScheduleTaskRemoverJob job failed', [
            'task_id' => $this->taskId,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
