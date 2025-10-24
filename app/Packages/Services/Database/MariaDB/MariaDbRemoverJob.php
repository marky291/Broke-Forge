<?php

namespace App\Packages\Services\Database\MariaDB;

use App\Enums\DatabaseStatus;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

/**
 * MariaDB Database Removal Job
 *
 * Handles queued MariaDB database removal from remote servers with lifecycle management.
 */
class MariaDbRemoverJob extends Taskable
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
        return DatabaseStatus::Uninstalling;
    }

    protected function getSuccessStatus(): mixed
    {
        return DatabaseStatus::Active; // Not used since we delete
    }

    protected function getFailedStatus(): mixed
    {
        return DatabaseStatus::Failed;
    }

    protected function shouldDeleteOnSuccess(): bool
    {
        return true;
    }

    protected function executeOperation(Model $model): void
    {
        $remover = new MariaDbRemover($this->server);
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
