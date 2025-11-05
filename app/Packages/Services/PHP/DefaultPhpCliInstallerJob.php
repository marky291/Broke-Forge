<?php

namespace App\Packages\Services\PHP;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerPhp;
use App\Packages\Enums\PhpVersion;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

/**
 * Default PHP CLI Installer Job
 *
 * Sets the system-wide PHP CLI default on a remote server using update-alternatives.
 * Follows the Reverb Package Lifecycle Pattern with status transitions.
 */
class DefaultPhpCliInstallerJob extends Taskable
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
        return TaskStatus::Updating;
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

        $installer = new DefaultPhpCliInstaller($this->server);
        $installer->execute($phpVersion);
    }

    protected function getAdditionalSuccessData(Model $model): array
    {
        return [
            'is_cli_default' => true,
        ];
    }

    protected function getLogContext(Model $model): array
    {
        return [
            'php_id' => $model->id,
            'server_id' => $this->server->id,
            'version' => $model->version,
        ];
    }

    protected function getFailedLogContext(\Throwable $exception): array
    {
        return [
            'php_id' => $this->serverPhp->id,
            'server_id' => $this->server->id,
            'version' => $this->serverPhp->version,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ];
    }
}
