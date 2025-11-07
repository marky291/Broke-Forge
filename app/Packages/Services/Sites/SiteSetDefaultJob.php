<?php

namespace App\Packages\Services\Sites;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Sets a site as the default site (responds to server IP address).
 * Swaps the /home/brokeforge/default symlink to point to the specified site.
 */
class SiteSetDefaultJob extends Taskable
{
    public function __construct(
        public Server $server,
        public ServerSite $site,
        public int $previousDefaultSiteId
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
        return 'default_site_status';
    }

    protected function executeOperation(Model $model): void
    {
        $installer = new SiteSetDefaultInstaller($this->server);
        $installer->execute($model);
    }

    protected function handleFailure(Model $model, \Exception $e): void
    {
        // Call parent to update status
        parent::handleFailure($model, $e);

        // Rollback: restore previous default site
        $this->rollbackDefaultSite($model);
    }

    /**
     * Handle a job failure (timeout, fatal error, worker crash).
     */
    public function failed(\Throwable $exception): void
    {
        // Call parent to update status
        parent::failed($exception);

        // Rollback: restore previous default site
        if ($model = $this->findModelForFailure()) {
            $this->rollbackDefaultSite($model);
        }
    }

    /**
     * Rollback to previous default site.
     */
    protected function rollbackDefaultSite(Model $model): void
    {
        // Unset current site as default
        $model->update(['is_default' => false]);

        // Restore previous default site
        if ($previousDefaultSite = ServerSite::find($this->previousDefaultSiteId)) {
            $previousDefaultSite->update(['is_default' => true]);
        }
    }

    protected function getLogContext(Model $model): array
    {
        return [
            'server_id' => $this->server->id,
            'site_id' => $model->id,
            'domain' => $model->domain,
            'previous_default_id' => $this->previousDefaultSiteId,
        ];
    }

    protected function getOperationName(): string
    {
        return "set default site for server #{$this->server->id}";
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
            'error_log' => null, // Clear any previous errors
        ];
    }
}
