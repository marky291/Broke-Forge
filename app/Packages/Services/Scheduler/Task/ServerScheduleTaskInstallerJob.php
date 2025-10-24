<?php

namespace App\Packages\Services\Scheduler\Task;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerScheduledTask;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

/**
 * Server Scheduled Task Installation Job
 *
 * Handles queued task installation on remote servers with real-time status updates.
 * Each job instance handles ONE scheduled task only.
 * For multiple tasks, dispatch multiple job instances.
 */
class ServerScheduleTaskInstallerJob extends Taskable
{
    /**
     * @param  Server  $server  The server to configure
     * @param  ServerScheduledTask  $serverScheduledTask  The scheduled task to install
     */
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
        return TaskStatus::Installing;
    }

    protected function getSuccessStatus(): mixed
    {
        return TaskStatus::Active;
    }

    protected function getFailedStatus(): mixed
    {
        return TaskStatus::Failed;
    }

    protected function executeOperation(Model $model): void
    {
        $installer = new ServerScheduleTaskInstaller($this->server, $model);
        $installer->execute();
    }

    protected function getLogContext(Model $model): array
    {
        return [
            'task_id' => $model->id,
            'server_id' => $this->server->id,
            'task_name' => $model->name,
            'command' => $model->command,
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
