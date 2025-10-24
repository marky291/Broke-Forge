<?php

namespace App\Packages\Services\Database\PostgreSQL;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

/**
 * PostgreSQL Installation Job
 *
 * Handles queued PostgreSQL installation on remote servers with real-time status updates.
 */
class PostgreSqlInstallerJob extends Taskable
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
        $installer = new PostgreSqlInstaller($this->server);
        $installer->execute($model->version, $model->root_password);
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
