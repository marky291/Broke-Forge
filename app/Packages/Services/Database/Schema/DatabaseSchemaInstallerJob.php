<?php

namespace App\Packages\Services\Database\Schema;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerDatabaseSchema;
use App\Models\ServerDatabaseUser;
use App\Packages\Services\Database\User\DatabaseUserInstaller;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

/**
 * Database Schema Installation Job
 *
 * Handles queued database schema creation on remote servers with real-time status updates.
 */
class DatabaseSchemaInstallerJob extends Taskable
{
    public function __construct(
        public Server $server,
        public ServerDatabaseSchema $schema,
        public ?ServerDatabaseUser $user = null
    ) {}

    protected function getModel(): Model
    {
        return $this->schema;
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
        // Create the database schema
        $installer = new DatabaseSchemaInstaller($this->server, $this->schema->database);
        $installer->execute($model->name, $model->character_set, $model->collation);

        // Create database user if provided
        if ($this->user) {
            $userInstaller = new DatabaseUserInstaller($this->server, $this->schema->database);
            $userInstaller->execute(
                $this->user->username,
                $this->user->password,
                $this->user->host,
                $this->user->privileges,
                [$model->name]
            );

            // Update user status to active
            $this->user->update(['status' => TaskStatus::Active]);
        }
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
}
