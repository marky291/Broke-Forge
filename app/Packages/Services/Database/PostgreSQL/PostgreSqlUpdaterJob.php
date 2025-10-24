<?php

namespace App\Packages\Services\Database\PostgreSQL;

use App\Enums\DatabaseStatus;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

/**
 * PostgreSQL Database Update Job
 *
 * Handles queued PostgreSQL database updates on remote servers with real-time status updates.
 */
class PostgreSqlUpdaterJob extends Taskable
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
        return DatabaseStatus::Updating;
    }

    protected function getSuccessStatus(): mixed
    {
        return DatabaseStatus::Active;
    }

    protected function getFailedStatus(): mixed
    {
        return DatabaseStatus::Failed;
    }

    protected function executeOperation(Model $model): void
    {
        $updater = new PostgreSqlUpdater($this->server);
        $updater->execute($model->version);
    }

    protected function getLogContext(Model $model): array
    {
        return [
            'database_id' => $model->id,
            'server_id' => $this->server->id,
            'target_version' => $model->version,
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
