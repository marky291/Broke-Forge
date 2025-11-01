<?php

namespace App\Packages\Services\Node;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

/**
 * Composer Update Job
 *
 * Handles queued Composer updates on remote servers with real-time status updates.
 */
class ComposerUpdaterJob extends Taskable
{
    public function __construct(
        public Server $server
    ) {}

    protected function getModel(): Model
    {
        return $this->server;
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
        return 'composer_status';
    }

    protected function executeOperation(Model $model): void
    {
        $updater = new ComposerUpdater($this->server);
        $updater->execute();

        // Update composer version after successful update
        $this->updateComposerVersion();
    }

    /**
     * Update server with latest Composer version information
     */
    private function updateComposerVersion(): void
    {
        try {
            // Get Composer version from server (without head to handle multi-line warnings)
            $result = $this->server->ssh('root')->execute('composer --version 2>&1');

            if ($result->isSuccessful()) {
                $output = $result->getOutput();
                // Extract version number from multi-line output
                // Looks for "Composer version X.Y.Z" anywhere in output
                if (preg_match('/Composer\s+version\s+([\d.]+)/', $output, $matches)) {
                    // Only store the version number (e.g., "2.8.12")
                    $this->server->update([
                        'composer_version' => $matches[1],
                        'composer_updated_at' => now(),
                    ]);
                } else {
                    // Version detection failed - log but don't update version
                    \Log::warning('Could not parse Composer version from output after update', [
                        'server_id' => $this->server->id,
                        'output' => $output,
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Log but don't fail the update if version detection fails
            \Log::warning('Failed to detect Composer version after update', [
                'server_id' => $this->server->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function getLogContext(Model $model): array
    {
        return [
            'server_id' => $this->server->id,
            'current_version' => $this->server->composer_version,
        ];
    }

    protected function getFailedLogContext(\Throwable $exception): array
    {
        return [
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ];
    }
}
