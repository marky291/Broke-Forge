<?php

namespace App\Packages\Services\Database\User;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerDatabaseUser;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

/**
 * Database User Removal Job
 *
 * Handles queued database user deletion on remote servers with real-time status updates.
 */
class DatabaseUserRemoverJob extends Taskable
{
    public function __construct(
        public Server $server,
        public ServerDatabaseUser $user
    ) {}

    protected function getModel(): Model
    {
        return $this->user;
    }

    protected function getInProgressStatus(): mixed
    {
        return TaskStatus::Removing;
    }

    protected function getSuccessStatus(): mixed
    {
        // Model will be deleted after successful removal
        return TaskStatus::Active;
    }

    protected function getFailedStatus(): mixed
    {
        return TaskStatus::Failed;
    }

    protected function executeOperation(Model $model): void
    {
        $remover = new DatabaseUserRemover($this->server, $this->user->database);
        $remover->execute($model->username, $model->host);
    }

    protected function getLogContext(Model $model): array
    {
        return [
            'user_id' => $model->id,
            'server_id' => $this->server->id,
            'database_id' => $this->user->server_database_id,
            'username' => $model->username,
            'host' => $model->host,
        ];
    }

    protected function getFailedLogContext(\Throwable $exception): array
    {
        return [
            'user_id' => $this->user->id,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ];
    }

    protected function shouldDeleteOnSuccess(): bool
    {
        return true;
    }

    protected function getOperationName(): string
    {
        return "database user removal for server #{$this->server->id}";
    }
}
