<?php

namespace App\Packages\Services\Sites\Deployment;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerDeployment;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

/**
 * Site Git Deployment Job
 *
 * Handles queued deployment execution for Git-enabled sites with real-time status updates.
 */
class SiteGitDeploymentJob extends Taskable
{
    public function __construct(
        public Server $server,
        public ServerDeployment $serverDeployment
    ) {}

    protected function getModel(): Model
    {
        return $this->serverDeployment;
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

    protected function getErrorField(): string
    {
        return 'error_output';
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
        $site = $model->site;

        $installer = new SiteGitDeploymentInstaller($this->server);
        $installer->setSite($site);
        $installer->execute($site, $model);
    }

    protected function getLogContext(Model $model): array
    {
        return [
            'deployment_id' => $model->id,
            'server_id' => $this->server->id,
            'site_id' => $model->site->id,
        ];
    }

    protected function getFailedLogContext(\Throwable $exception): array
    {
        return [
            'deployment_id' => $this->serverDeployment->id,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ];
    }
}
