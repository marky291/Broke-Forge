<?php

namespace App\Packages\Services\Node;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerNode;
use App\Packages\Enums\NodeVersion;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

/**
 * Node.js Removal Job
 *
 * Handles queued Node.js removal on remote servers with lifecycle management.
 */
class NodeRemoverJob extends Taskable
{
    public function __construct(
        public Server $server,
        public ServerNode $serverNode
    ) {}

    protected function getModel(): Model
    {
        return $this->serverNode;
    }

    protected function getInProgressStatus(): mixed
    {
        return TaskStatus::Removing;
    }

    protected function getSuccessStatus(): mixed
    {
        return TaskStatus::Active; // Not used since we delete
    }

    protected function getFailedStatus(): mixed
    {
        return TaskStatus::Failed;
    }

    protected function shouldDeleteOnSuccess(): bool
    {
        return true;
    }

    protected function executeOperation(Model $model): void
    {
        $nodeVersion = NodeVersion::from($model->version);

        $remover = new NodeRemover($this->server);
        $remover->execute($nodeVersion, $model->id);
    }

    protected function getLogContext(Model $model): array
    {
        $nodeVersion = NodeVersion::from($model->version);

        return [
            'node_id' => $model->id,
            'server_id' => $this->server->id,
            'version' => $nodeVersion->value,
        ];
    }

    protected function getFailedLogContext(\Throwable $exception): array
    {
        return [
            'node_id' => $this->serverNode->id,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ];
    }
}
