<?php

namespace App\Packages\Services\Supervisor\Task;

use App\Enums\SupervisorTaskStatus;
use App\Models\Server;
use App\Models\ServerSupervisorTask;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Supervisor Task Installation Job
 *
 * Handles queued supervisor task installation on remote servers with real-time status updates
 */
class SupervisorTaskInstallerJob implements ShouldQueue
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
    public $tries = 3;

    public function __construct(
        public Server $server,
        public int $taskId  // ← Receives task ID, NOT full model
    ) {}

    public function handle(): void
    {
        // Load the task from database
        $task = ServerSupervisorTask::findOrFail($this->taskId);

        Log::info('Starting supervisor task installation', [
            'task_id' => $task->id,
            'server_id' => $this->server->id,
            'task_name' => $task->name,
        ]);

        try {
            // ✅ UPDATE: pending → installing
            $task->update(['status' => SupervisorTaskStatus::Installing]);
            // Model event broadcasts automatically via Reverb

            // Create installer and execute
            $installer = new SupervisorTaskInstaller($this->server, $task);
            $installer->execute();

            // ✅ UPDATE: installing → active
            $task->update(['status' => SupervisorTaskStatus::Active, 'installed_at' => now()]);
            // Model event broadcasts automatically via Reverb

            Log::info('Supervisor task installation completed', [
                'task_id' => $task->id,
                'server_id' => $this->server->id,
            ]);

        } catch (Exception $e) {
            // ✅ UPDATE: any → failed
            $task->update([
                'status' => SupervisorTaskStatus::Failed,
                'error_log' => $e->getMessage(),
            ]);
            // Model event broadcasts automatically via Reverb

            Log::error('Supervisor task installation failed', [
                'task_id' => $task->id,
                'server_id' => $this->server->id,
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
        $task = ServerSupervisorTask::find($this->taskId);

        if ($task) {
            $task->update([
                'status' => SupervisorTaskStatus::Failed,
                'error_log' => $exception->getMessage(),
            ]);
        }

        Log::error('SupervisorTaskInstallerJob job failed', [
            'task_id' => $this->taskId,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
