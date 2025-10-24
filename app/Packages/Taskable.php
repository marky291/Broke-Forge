<?php

namespace App\Packages;

use App\Models\Server;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Abstract Package Lifecycle Job
 *
 * Base class for all package installation, removal, and update jobs.
 * Provides common lifecycle management, error handling, logging, and middleware.
 *
 * Child classes must implement abstract methods to define their specific behavior.
 */
abstract class Taskable implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     */
    public $timeout = 600;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 0;

    /**
     * The number of exceptions to allow before failing.
     */
    public $maxExceptions = 3;

    /**
     * The server instance for this operation.
     */
    public Server $server;

    /**
     * Handle the job execution with centralized lifecycle management.
     */
    public function handle(): void
    {
        set_time_limit(0);

        $model = $this->loadModel();

        Log::info("Starting {$this->getOperationName()}", $this->getLogContext($model));

        try {
            // Update to in-progress status
            $this->updateStatus($model, $this->getInProgressStatus());

            // Execute the operation
            $this->executeOperation($model);

            // Handle success
            $this->handleSuccess($model);

            Log::info("{$this->getOperationName()} completed", $this->getLogContext($model));

        } catch (Exception $e) {
            $this->handleFailure($model, $e);

            throw $e;  // Re-throw for Laravel's retry mechanism
        }
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("package:action:{$this->server->id}"))->shared()
                ->releaseAfter(15)
                ->expireAfter(900),
        ];
    }

    /**
     * Handle a job failure (timeout, fatal error, worker crash).
     */
    public function failed(\Throwable $exception): void
    {
        $model = $this->findModelForFailure();

        if ($model) {
            $this->updateStatus($model, $this->getFailedStatus(), [
                $this->getErrorField() => $exception->getMessage(),
            ]);
        }

        Log::error("{$this->getOperationName()} job failed", $this->getFailedLogContext($exception));
    }

    /**
     * Update model status with optional additional data.
     */
    protected function updateStatus(Model $model, mixed $status, array $additionalData = []): void
    {
        $model->update(array_merge(
            [$this->getStatusField() => $status],
            $additionalData
        ));
    }

    /**
     * Handle successful operation completion.
     */
    protected function handleSuccess(Model $model): void
    {
        if ($this->shouldDeleteOnSuccess()) {
            $model->delete();
        } else {
            $this->updateStatus(
                $model,
                $this->getSuccessStatus(),
                $this->getAdditionalSuccessData($model)
            );
        }
    }

    /**
     * Handle operation failure.
     */
    protected function handleFailure(Model $model, Exception $e): void
    {
        $this->updateStatus($model, $this->getFailedStatus(), [
            $this->getErrorField() => $e->getMessage(),
        ]);

        Log::error("{$this->getOperationName()} failed", array_merge(
            $this->getLogContext($model),
            [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]
        ));
    }

    /**
     * Load the model from the database.
     * Refreshes the model instance to ensure we have the latest data.
     */
    protected function loadModel(): Model
    {
        return $this->getModel()->fresh() ?? throw new \Exception('Model not found in database');
    }

    /**
     * Get the model instance for this job.
     * Child classes should return their model property.
     */
    abstract protected function getModel(): Model;

    /**
     * Get the status value for "in progress" (e.g., Installing, Removing, Updating).
     */
    abstract protected function getInProgressStatus(): mixed;

    /**
     * Get the status value for success (e.g., Active, Deleted, Success).
     */
    abstract protected function getSuccessStatus(): mixed;

    /**
     * Get the status value for failure.
     */
    abstract protected function getFailedStatus(): mixed;

    /**
     * Execute the actual operation (installation, removal, update, etc.).
     */
    abstract protected function executeOperation(Model $model): void;

    /**
     * Get logging context for this operation.
     */
    abstract protected function getLogContext(Model $model): array;

    /**
     * Get human-readable operation name for logging.
     * Defaults to the class name (e.g., "PhpRemoverJob").
     */
    protected function getOperationName(): string
    {
        return class_basename(static::class);
    }

    /**
     * Find the model for the failed() method.
     * Returns a fresh instance of the model or null if not found.
     */
    protected function findModelForFailure(): ?Model
    {
        return $this->getModel()->fresh();
    }

    /**
     * Get logging context for the failed() method.
     */
    abstract protected function getFailedLogContext(\Throwable $exception): array;

    /**
     * Whether to delete the model on successful completion (for remover jobs).
     */
    protected function shouldDeleteOnSuccess(): bool
    {
        return false;
    }

    /**
     * Get additional data to save on success (e.g., timestamps).
     */
    protected function getAdditionalSuccessData(Model $model): array
    {
        return [];
    }

    /**
     * Get the status field name (usually 'status').
     */
    protected function getStatusField(): string
    {
        return 'status';
    }

    /**
     * Get the error field name (usually 'error_log').
     */
    protected function getErrorField(): string
    {
        return 'error_log';
    }
}
