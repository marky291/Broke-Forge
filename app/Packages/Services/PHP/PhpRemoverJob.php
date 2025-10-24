<?php

namespace App\Packages\Services\PHP;

use App\Enums\PhpStatus;
use App\Models\Server;
use App\Models\ServerPhp;
use App\Packages\Enums\PhpVersion;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

/**
 * PHP Removal Job
 *
 * Handles queued PHP removal on remote servers with lifecycle management.
 */
class PhpRemoverJob extends Taskable
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
        return PhpStatus::Removing;
    }

    protected function getSuccessStatus(): mixed
    {
        return PhpStatus::Active; // Not used since we delete
    }

    protected function getFailedStatus(): mixed
    {
        return PhpStatus::Failed;
    }

    protected function shouldDeleteOnSuccess(): bool
    {
        return true;
    }

    protected function executeOperation(Model $model): void
    {
        $phpVersion = PhpVersion::from($model->version);

        $remover = new PhpRemover($this->server);
        $remover->execute($phpVersion, $model->id);
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
