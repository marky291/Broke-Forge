<?php

namespace App\Packages\Services\Sites\Framework\WordPress;

use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

/**
 * WordPress Installation Job
 *
 * Handles queued WordPress installation on remote servers with real-time status updates.
 */
class WordPressInstallerJob extends Taskable
{
    /**
     * Create a new job instance.
     */
    public function __construct(
        public Server $server,
        public int $siteId
    ) {}

    /**
     * Get the model instance for this job.
     */
    protected function getModel(): Model
    {
        return ServerSite::findOrFail($this->siteId);
    }

    /**
     * Get the status value for "in progress".
     */
    protected function getInProgressStatus(): mixed
    {
        // For site installation, we stay in 'installing' during WordPress install
        return 'installing';
    }

    /**
     * Get the status value for success.
     */
    protected function getSuccessStatus(): mixed
    {
        return 'active';
    }

    /**
     * Get the status value for failure.
     */
    protected function getFailedStatus(): mixed
    {
        return 'failed';
    }

    /**
     * Execute the actual WordPress installation operation.
     */
    protected function executeOperation(Model $model): void
    {
        /** @var ServerSite $model */
        $installer = new WordPressInstaller($this->server);
        $installer->execute($model);
    }

    /**
     * Get logging context for this operation.
     */
    protected function getLogContext(Model $model): array
    {
        /** @var ServerSite $model */
        return [
            'site_id' => $model->id,
            'domain' => $model->domain,
            'server_id' => $this->server->id,
        ];
    }

    /**
     * Get human-readable operation name for logging.
     */
    protected function getOperationName(): string
    {
        return 'WordPress installation';
    }

    /**
     * Find the model for the failed() method.
     */
    protected function findModelForFailure(): ?Model
    {
        return ServerSite::find($this->siteId);
    }

    /**
     * Get logging context for the failed() method.
     */
    protected function getFailedLogContext(\Throwable $exception): array
    {
        return [
            'site_id' => $this->siteId,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ];
    }

    /**
     * Get additional data to save on success.
     */
    protected function getAdditionalSuccessData(Model $model): array
    {
        return [
            'installed_at' => now(),
        ];
    }
}
