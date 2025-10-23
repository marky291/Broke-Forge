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
 * Server Scheduled Task Installation Job
 *
 * Handles queued task installation on remote servers with real-time status updates
 * Each job instance handles ONE scheduled task only.
 * For multiple tasks, dispatch multiple job instances.
 */
class ServerScheduleTaskInstallerJob implements ShouldQueue
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

    /**
     * @param  Server  $server  The server to configure
     * @param  int  $taskId  The ServerScheduledTask ID to install
     */
    public function __construct(
        public Server $server,
        public int $taskId
    ) {}

    public function handle(): void
    {
        // Set no time limit for long-running installation process
        set_time_limit(0);

        // Load the scheduled task from database
        $task = ServerScheduledTask::findOrFail($this->taskId);

        Log::info('Starting scheduled task installation', [
            'task_id' => $task->id,
            'server_id' => $this->server->id,
            'task_name' => $task->name,
            'command' => $task->command,
        ]);

        try {
            // ✅ UPDATE: pending → installing
            $task->update(['status' => TaskStatus::Installing]);
            // Model event broadcasts automatically via Reverb

            // Create installer instance with existing task model
            $installer = new ServerScheduleTaskInstaller($this->server, $task);

            // Execute installation
            $installer->execute();

            // ✅ UPDATE: installing → active
            $task->update(['status' => TaskStatus::Active]);
            // Model event broadcasts automatically via Reverb

            Log::info("Scheduled task '{$task->name}' installed successfully", [
                'task_id' => $task->id,
                'server_id' => $this->server->id,
            ]);

        } catch (Exception $e) {
            // ✅ UPDATE: any → failed
            $task->update([
                'status' => TaskStatus::Failed,
                'error_log' => $e->getMessage(),
            ]);
            // Model event broadcasts automatically via Reverb

            Log::error('Scheduled task installation failed', [
                'task_id' => $task->id,
                'server_id' => $this->server->id,
                'task_name' => $task->name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;  // Re-throw for Laravel's retry mechanism
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

        Log::error('ServerScheduleTaskInstallerJob job failed', [
            'task_id' => $this->taskId,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
