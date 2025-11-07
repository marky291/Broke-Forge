<?php

namespace App\Packages\Services\Sites;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Unsets the default site (removes IP-based access to any site).
 * Removes the /home/brokeforge/default symlink entirely.
 */
class SiteUnsetDefaultJob extends Taskable
{
    public function __construct(
        public Server $server,
        public ServerSite $site
    ) {}

    /**
     * Middleware to prevent concurrent default site operations on the same server.
     */
    public function middleware(): array
    {
        // Skip middleware for synchronous dispatch
        if ($this->connection === 'sync') {
            return [];
        }

        return [
            (new WithoutOverlapping("site:default:{$this->server->id}"))->shared()
                ->releaseAfter(15)
                ->expireAfter(900),
        ];
    }

    protected function getModel(): Model
    {
        return $this->site;
    }

    protected function getInProgressStatus(): mixed
    {
        return TaskStatus::Removing;
    }

    protected function getSuccessStatus(): mixed
    {
        return TaskStatus::Success;
    }

    protected function getFailedStatus(): mixed
    {
        return TaskStatus::Failed;
    }

    protected function getStatusField(): string
    {
        return 'default_site_status';
    }

    protected function executeOperation(Model $model): void
    {
        $installer = new SiteUnsetDefaultInstaller($this->server);
        $installer->execute($model);
    }

    protected function handleFailure(Model $model, \Exception $e): void
    {
        // Call parent to update status
        parent::handleFailure($model, $e);

        // Rollback: restore default flag
        $model->update(['is_default' => true]);
    }

    /**
     * Handle a job failure (timeout, fatal error, worker crash).
     */
    public function failed(\Throwable $exception): void
    {
        // Call parent to update status
        parent::failed($exception);

        // Rollback: restore default flag
        if ($model = $this->findModelForFailure()) {
            $model->update(['is_default' => true]);
        }
    }

    protected function getLogContext(Model $model): array
    {
        return [
            'server_id' => $this->server->id,
            'site_id' => $model->id,
            'domain' => $model->domain,
        ];
    }

    protected function getOperationName(): string
    {
        return "unset default site for server #{$this->server->id}";
    }

    protected function findModelForFailure(): ?Model
    {
        return ServerSite::find($this->site->id);
    }

    protected function getFailedLogContext(\Throwable $exception): array
    {
        return [
            'server_id' => $this->server->id,
            'site_id' => $this->site->id,
            'domain' => $this->site->domain,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ];
    }

    protected function getAdditionalSuccessData(Model $model): array
    {
        return [
            'is_default' => false, // Unset default flag on success
            'error_log' => null, // Clear any previous errors
            'default_site_status' => null, // Clear status on success
        ];
    }
}
