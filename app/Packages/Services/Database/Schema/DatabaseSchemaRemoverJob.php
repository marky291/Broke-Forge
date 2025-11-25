<?php

namespace App\Packages\Services\Database\Schema;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerDatabaseSchema;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

/**
 * Database Schema Removal Job
 *
 * Handles queued database schema deletion on remote servers with real-time status updates.
 */
class DatabaseSchemaRemoverJob extends Taskable
{
    public function __construct(
        public Server $server,
        public ServerDatabaseSchema $schema
    ) {}

    protected function getModel(): Model
    {
        return $this->schema;
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
        $remover = new DatabaseSchemaRemover($this->server, $this->schema->database);
        $remover->execute($model->name);
    }

    protected function getLogContext(Model $model): array
    {
        return [
            'schema_id' => $model->id,
            'server_id' => $this->server->id,
            'database_id' => $this->schema->server_database_id,
            'schema_name' => $model->name,
        ];
    }

    protected function getFailedLogContext(\Throwable $exception): array
    {
        return [
            'schema_id' => $this->schema->id,
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
        return "database schema removal for server #{$this->server->id}";
    }
}
