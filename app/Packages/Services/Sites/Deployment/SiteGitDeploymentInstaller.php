<?php

namespace App\Packages\Services\Sites\Deployment;

use App\Enums\TaskStatus;
use App\Models\ServerDeployment;
use App\Models\ServerSite;
use App\Packages\Core\Base\PackageInstaller;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Site Git Deployment Installer
 *
 * Executes deployment scripts for Git-enabled sites and tracks deployment history
 */
class SiteGitDeploymentInstaller extends PackageInstaller implements \App\Packages\Core\Base\SitePackage
{
    protected ?string $commitSha = null;

    /**
     * Execute deployment for a site
     */
    public function execute(ServerSite $site, ServerDeployment $deployment): void
    {
        // Mark deployment as running
        $deployment->update([
            'status' => TaskStatus::Updating,
            'started_at' => now(),
        ]);

        $documentRoot = $site->document_root;
        if (! $documentRoot) {
            throw new RuntimeException('Site document root not configured.');
        }

        $start = (int) (microtime(true) * 1000);

        try {
            $this->install($this->commands($documentRoot, $deployment->deployment_script, $site, $deployment));

            $duration = (int) (microtime(true) * 1000) - $start;

            // Update deployment with success
            $deployment->update([
                'status' => 'success',
                'exit_code' => 0,
                'commit_sha' => $this->commitSha,
                'branch' => $site->getGitConfiguration()['branch'] ?? null,
                'duration_ms' => $duration,
                'completed_at' => now(),
            ]);

            // Update site's last deployed info
            $site->update([
                'last_deployment_sha' => $this->commitSha,
                'last_deployed_at' => now(),
            ]);

            Log::info("Deployment completed successfully for site #{$site->id}", [
                'deployment_id' => $deployment->id,
                'commit_sha' => $this->commitSha,
                'duration_ms' => $duration,
            ]);
        } catch (\Exception $e) {
            $duration = (int) (microtime(true) * 1000) - $start;

            // Update deployment with failure
            $deployment->update([
                'status' => 'failed',
                'exit_code' => 1,
                'duration_ms' => $duration,
                'completed_at' => now(),
            ]);

            Log::error("Deployment failed for site #{$site->id}", [
                'deployment_id' => $deployment->id,
                'error' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);
        }
    }

    /**
     * Generate SSH commands for deployment execution
     */
    protected function commands(string $documentRoot, string $deploymentScript, ServerSite $site, ServerDeployment $deployment): array
    {
        $documentRoot = rtrim($documentRoot, '/');
        // Git repository is cloned to the site directory (parent of document root)
        $siteDirectory = dirname($documentRoot);

        // Create log file path
        $logDirectory = config('deployment.log_directory');
        $logFileName = sprintf('%s_%s.log', $deployment->id, time());
        $logFilePath = rtrim($logDirectory, '/').'/'.$logFileName;

        // Split deployment script into individual lines
        $scriptLines = array_filter(
            array_map('trim', explode("\n", $deploymentScript)),
            fn ($line) => ! empty($line) && ! str_starts_with($line, '#')
        );

        $commands = [
            // Create log directory if it doesn't exist and initialize log file with proper ownership
            function () use ($logDirectory, $logFilePath) {
                $remoteCommand = sprintf(
                    'mkdir -p %s && touch %s && chown brokeforge:brokeforge %s && chmod 644 %s && echo "=== Deployment Started at $(date) ===" > %s',
                    escapeshellarg($logDirectory),
                    escapeshellarg($logFilePath),
                    escapeshellarg($logFilePath),
                    escapeshellarg($logFilePath),
                    escapeshellarg($logFilePath)
                );

                $process = $this->server->ssh('brokeforge')->execute($remoteCommand);

                if (! $process->isSuccessful()) {
                    Log::error('Failed to create deployment log file', [
                        'server_id' => $this->server->id,
                        'log_file_path' => $logFilePath,
                        'exit_code' => $process->getExitCode(),
                        'output' => $process->getOutput(),
                    ]);

                    throw new RuntimeException('Failed to create deployment log file on remote server');
                }
            },
        ];

        // Store log file path in deployment record for frontend polling
        $commands[] = function () use ($logFilePath, $deployment) {
            $deployment->update([
                'log_file_path' => $logFilePath,
            ]);
        };

        // Execute each line of the deployment script
        foreach ($scriptLines as $index => $line) {
            $commands[] = function () use ($siteDirectory, $line, $logFilePath, $deployment, $index) {
                // Log the command being executed
                $logCommandCommand = sprintf(
                    'echo "\n=== Running: %s ===" >> %s',
                    addcslashes($line, '"\\$`'),
                    escapeshellarg($logFilePath)
                );
                $this->server->ssh('brokeforge')->execute($logCommandCommand);

                // Add safe.directory config and execute the command with output redirection
                $remoteCommand = sprintf(
                    'git config --global --add safe.directory %s && cd %s && %s >> %s 2>&1',
                    escapeshellarg($siteDirectory),
                    escapeshellarg($siteDirectory),
                    $line,
                    escapeshellarg($logFilePath)
                );

                $process = $this->server->ssh('brokeforge')
                    ->setTimeout(300) // 5 minute timeout per command
                    ->execute($remoteCommand);

                if (! $process->isSuccessful()) {
                    // Log failure to remote log file
                    $failureLogCommand = sprintf(
                        'echo "\n!!! Command failed with exit code %d !!!" >> %s',
                        $process->getExitCode(),
                        escapeshellarg($logFilePath)
                    );
                    $this->server->ssh('brokeforge')->execute($failureLogCommand);

                    Log::warning('Deployment script line failed.', [
                        'server_id' => $this->server->id,
                        'line' => $line,
                        'exit_code' => $process->getExitCode(),
                    ]);

                    throw new RuntimeException("Deployment failed at line: {$line}");
                }

                Log::info("Deployment line #{$index} completed", [
                    'deployment_id' => $deployment->id,
                    'line' => $line,
                ]);
            };
        }

        // Capture current Git commit SHA
        $commands[] = function () use ($siteDirectory) {
            $remoteCommand = sprintf(
                'git config --global --add safe.directory %s && cd %s && git rev-parse HEAD 2>/dev/null || echo ""',
                escapeshellarg($siteDirectory),
                escapeshellarg($siteDirectory)
            );

            $process = $this->server->ssh('brokeforge')
                ->execute($remoteCommand);

            $this->commitSha = trim($process->getOutput()) ?: null;
        };

        // Write completion message to log file
        $commands[] = function () use ($logFilePath) {
            $completionCommand = sprintf(
                'echo "\n=== Deployment Completed Successfully at $(date) ===" >> %s',
                escapeshellarg($logFilePath)
            );
            $this->server->ssh('brokeforge')->execute($completionCommand);
        };

        return $commands;
    }

    public function milestones(): Milestones
    {
        return new SiteGitDeploymentInstallerMilestones;
    }
}
