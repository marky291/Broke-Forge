<?php

namespace App\Packages\Services\Database\User;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerDatabaseUser;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

/**
 * Database User Installation Job
 *
 * Handles queued database user creation on remote servers with real-time status updates.
 */
class DatabaseUserInstallerJob extends Taskable
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
        $installer = new DatabaseUserInstaller($this->server, $this->user->database);

        // Get schemas the user should have access to
        $schemas = $this->user->schemas()->pluck('name')->toArray();

        $installer->execute(
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
