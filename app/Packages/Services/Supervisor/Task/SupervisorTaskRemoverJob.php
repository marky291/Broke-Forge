<?php

namespace App\Packages\Services\Supervisor\Task;

use App\Enums\SupervisorTaskStatus;
use App\Models\Server;
use App\Models\ServerSupervisorTask;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

/**
 * Supervisor Task Removal Job
 *
 * Handles queued supervisor task removal from remote servers with real-time status updates.
 */
class SupervisorTaskRemoverJob extends Taskable
{
    public function __construct(
        public Server $server,
        public ServerSupervisorTask $serverSupervisorTask
    ) {}

    protected function getModel(): Model
    {
        return $this->serverSupervisorTask;
    }

    protected function getInProgressStatus(): mixed
    {
        return SupervisorTaskStatus::Removing;
    }

    protected function getSuccessStatus(): mixed
    {
        return SupervisorTaskStatus::Active; // Not used since we delete
    }

    protected function getFailedStatus(): mixed
    {
        return SupervisorTaskStatus::Failed;
    }

    protected function shouldDeleteOnSuccess(): bool
    {
        return true;
    }

    protected function executeOperation(Model $model): void
    {
        $remover = new SupervisorTaskRemover($this->server, $model);
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
            'task_id' => $this->serverSupervisorTask->id,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ];
    }
}
