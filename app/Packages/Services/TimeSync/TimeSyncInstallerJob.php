<?php

namespace App\Packages\Services\TimeSync;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

/**
 * Time Synchronization Installation Job
 *
 * Handles queued time synchronization setup on remote servers with real-time status updates.
 * Prevents APT repository validation failures due to clock skew.
 */
class TimeSyncInstallerJob extends Taskable
{
    public function __construct(
        public Server $server
    ) {}

    protected function getModel(): Model
    {
        return $this->server;
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

    protected function getStatusField(): string
    {
        return 'timesync_status';
    }

    protected function executeOperation(Model $model): void
    {
        $installer = new TimeSyncInstaller($this->server);
        $installer->execute();
    }

    protected function getAdditionalSuccessData(Model $model): array
    {
        return [
            'timesync_installed_at' => now(),
        ];
    }

    protected function getLogContext(Model $model): array
    {
        return [
            'server_id' => $this->server->id,
            'current_status' => $this->server->timesync_status,
        ];
    }

    protected function getFailedLogContext(\Throwable $exception): array
    {
        return [
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ];
    }
}
