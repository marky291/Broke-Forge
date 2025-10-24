<?php

namespace App\Packages\Services\PHP;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerPhp;
use App\Packages\Enums\PhpVersion;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

/**
 * PHP Installation Job
 *
 * Handles queued PHP installation on remote servers with real-time status updates.
 */
class PhpInstallerJob extends Taskable
{
    public function __construct(
        public Server $server,
        public ServerPhp $serverPhp
    ) {}

    protected function getModel(): Model
    {
        return $this->serverPhp;
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
        $phpVersion = PhpVersion::from($model->version);

        $installer = new PhpInstaller($this->server);
        $installer->execute($phpVersion);
    }

    protected function getLogContext(Model $model): array
    {
        $phpVersion = PhpVersion::from($model->version);

        return [
            'php_id' => $model->id,
            'server_id' => $this->server->id,
            'version' => $phpVersion->value,
        ];
    }

    protected function getFailedLogContext(\Throwable $exception): array
    {
        return [
            'php_id' => $this->serverPhp->id,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ];
    }
}
