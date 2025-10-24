<?php

namespace App\Packages\Services\Scheduler\Task;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerScheduledTask;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

/**
 * Server Scheduled Task Removal Job
 *
 * Handles queued task removal from remote servers with real-time status updates.
 */
class ServerScheduleTaskRemoverJob extends Taskable
{
    public function __construct(
        public Server $server,
        public ServerScheduledTask $serverScheduledTask
    ) {}

    protected function getModel(): Model
    {
        return $this->serverScheduledTask;
    }

    protected function getInProgressStatus(): mixed
    {
        return TaskStatus::Removing;
    }

    protected function getSuccessStatus(): mixed
    {
        return TaskStatus::Active; // Not used since we delete
    }

    protected function getFailedStatus(): mixed
    {
        return TaskStatus::Failed;
    }

    protected function shouldDeleteOnSuccess(): bool
    {
        return true;
    }

    protected function executeOperation(Model $model): void
    {
        $remover = new ServerScheduleTaskRemover($this->server, $model);
        $remover->execute();
    }

    protected function getLogContext(Model $model): array
    {
        return [
            'task_id' => $model->id,
            'server_id' => $this->server->id,
            'task_name' => $model->name,
        ];
    }

    protected function getFailedLogContext(\Throwable $exception): array
    {
        return [
            'task_id' => $this->serverScheduledTask->id,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ];
    }
}
