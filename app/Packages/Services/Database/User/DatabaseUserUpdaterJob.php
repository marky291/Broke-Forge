<?php

namespace App\Packages\Services\Database\User;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerDatabaseUser;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

/**
 * Database User Update Job
 *
 * Handles queued database user password/privilege updates on remote servers.
 */
class DatabaseUserUpdaterJob extends Taskable
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
        return TaskStatus::Updating;
    }

    protected function getSuccessStatus(): mixed
    {
        return null;  // Clear update_status on success
    }

    protected function getFailedStatus(): mixed
    {
        return TaskStatus::Failed;
    }

    protected function getStatusField(): string
    {
        return 'update_status';  // Track update operations separately
    }

    protected function getErrorField(): string
    {
        return 'update_error_log';  // Store update errors separately
    }

    protected function executeOperation(Model $model): void
    {
        $updater = new DatabaseUserUpdater($this->server, $this->user->database);

        // Get schemas the user should have access to
        $schemas = $this->user->schemas()->pluck('name')->toArray();

        $updater->execute(
            $model->username,
            $model->password,
            $model->host,
            $model->privileges,
            $schemas
        );
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
}
