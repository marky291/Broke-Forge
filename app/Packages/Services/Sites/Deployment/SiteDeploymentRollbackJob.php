<?php

namespace App\Packages\Services\Sites\Deployment;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerDeployment;
use App\Models\ServerSite;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

/**
 * Site Deployment Rollback Job
 *
 * Handles queued rollback execution for deployments with real-time status updates.
 */
class SiteDeploymentRollbackJob extends Taskable
{
    public function __construct(
        public Server $server,
        public ServerSite $site,
        public ServerDeployment $targetDeployment
    ) {}

    protected function getModel(): Model
    {
        return $this->targetDeployment;
    }

    protected function getInProgressStatus(): mixed
    {
        return TaskStatus::Updating;
    }

    protected function getSuccessStatus(): mixed
    {
        return TaskStatus::Success;
    }

    protected function getFailedStatus(): mixed
    {
        return TaskStatus::Failed;
    }

    protected function getAdditionalSuccessData(Model $model): array
    {
        return ['completed_at' => now()];
    }

    protected function handleFailure(Model $model, \Exception $e): void
    {
        $this->updateStatus($model, $this->getFailedStatus());

        \Illuminate\Support\Facades\Log::error("{$this->getOperationName()} failed", array_merge(
            $this->getLogContext($model),
            [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]
        ));
    }

    public function failed(\Throwable $exception): void
    {
        $model = $this->findModelForFailure();

        if ($model) {
            $this->updateStatus($model, $this->getFailedStatus());
        }

        \Illuminate\Support\Facades\Log::error("{$this->getOperationName()} job failed", $this->getFailedLogContext($exception));
    }

    protected function updateStatus(Model $model, mixed $status, array $additionalData = []): void
    {
        // Add started_at timestamp when moving to Running status
        if ($status === TaskStatus::Updating) {
            $additionalData['started_at'] = now();
        }

        // Add completed_at timestamp for terminal statuses
        if (in_array($status, [TaskStatus::Success, TaskStatus::Failed])) {
            $additionalData['completed_at'] = now();
        }

        parent::updateStatus($model, $status, $additionalData);
    }

    protected function executeOperation(Model $model): void
    {
        $installer = new SiteDeploymentRollbackInstaller($this->server);
        $installer->setSite($this->site);
        $installer->execute($this->site, $this->targetDeployment);
    }

    protected function getLogContext(Model $model): array
    {
        return [
            'deployment_id' => $model->id,
            'server_id' => $this->server->id,
            'site_id' => $this->site->id,
        ];
    }

    protected function getFailedLogContext(\Throwable $exception): array
    {
        return [
            'deployment_id' => $this->targetDeployment->id,
            'server_id' => $this->server->id,
            'site_id' => $this->site->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ];
    }

    protected function getOperationName(): string
    {
        return 'Rollback deployment';
    }
}
