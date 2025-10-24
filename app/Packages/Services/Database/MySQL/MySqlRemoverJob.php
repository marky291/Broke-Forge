<?php

namespace App\Packages\Services\Database\MySQL;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

/**
 * MySQL Database Removal Job
 *
 * Handles queued MySQL database removal from remote servers with lifecycle management.
 */
class MySqlRemoverJob extends Taskable
{
    public function __construct(
        public Server $server,
        public ServerDatabase $serverDatabase
    ) {}

    protected function getModel(): Model
    {
        return $this->serverDatabase;
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
        $remover = new MySqlRemover($this->server);
        $remover->execute();
    }

    protected function getLogContext(Model $model): array
    {
        return [
            'database_id' => $model->id,
            'server_id' => $this->server->id,
            'version' => $model->version,
        ];
    }

    protected function getFailedLogContext(\Throwable $exception): array
    {
        return [
            'database_id' => $this->serverDatabase->id,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ];
    }
}
