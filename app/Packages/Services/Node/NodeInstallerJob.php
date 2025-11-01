<?php

namespace App\Packages\Services\Node;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerNode;
use App\Packages\Enums\NodeVersion;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

/**
 * Node.js Installation Job
 *
 * Handles queued Node.js installation on remote servers with real-time status updates.
 * Also handles Composer installation if this is the first Node installation.
 */
class NodeInstallerJob extends Taskable
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

    protected function executeOperation(Model $model): void
    {
        $nodeVersion = NodeVersion::from($model->version);

        // Check if this is the first Node installation (should install Composer)
        $isFirstNodeInstall = ! $this->server->composer_version;

        $installer = new NodeInstaller($this->server);
        $installer->execute($nodeVersion, $isFirstNodeInstall);

        // If we installed Composer, update the server with Composer info
        if ($isFirstNodeInstall) {
            $this->updateComposerInfo();
        }
    }

    /**
     * Update server with Composer version information
     */
    private function updateComposerInfo(): void
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
                        'composer_status' => TaskStatus::Active,
                        'composer_updated_at' => now(),
                    ]);
                } else {
                    // Version detection failed - only update status, not version
                    \Log::warning('Could not parse Composer version from output', [
                        'server_id' => $this->server->id,
                        'output' => $output,
                    ]);

                    $this->server->update([
                        'composer_status' => TaskStatus::Active,
                        'composer_updated_at' => now(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Log but don't fail the Node installation if Composer version detection fails
            \Log::warning('Failed to detect Composer version', [
                'server_id' => $this->server->id,
                'error' => $e->getMessage(),
            ]);

            // Only update status on error, not version
            $this->server->update([
                'composer_status' => TaskStatus::Active,
                'composer_updated_at' => now(),
            ]);
        }
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
