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

            // Update site's last deployed info and active deployment
            $site->update([
                'last_deployment_sha' => $this->commitSha,
                'last_deployed_at' => now(),
                'active_deployment_id' => $deployment->id,
            ]);

            Log::info("Deployment completed successfully for site #{$site->id}", [
                'deployment_id' => $deployment->id,
                'commit_sha' => $this->commitSha,
                'duration_ms' => $duration,
            ]);

            // Prune old deployments (keep last 2)
            $this->pruneOldDeployments($site, 2);
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

            throw $e;
        }
    }

    /**
     * Generate SSH commands for deployment execution
     */
    protected function commands(string $documentRoot, string $deploymentScript, ServerSite $site, ServerDeployment $deployment): array
    {
        $siteRoot = $site->getSiteRoot();
        $siteSymlink = $site->getSiteSymlink();

        // Generate timestamp for deployment directory: ddMMYYYY-HHMMSS
        $timestamp = now()->format('dmY-His');
        $deploymentPath = "{$siteRoot}/{$timestamp}";

        // Get Git configuration
        $gitConfig = $site->getGitConfiguration();
        $repository = $gitConfig['repository'] ?? null;
        $branch = $gitConfig['branch'] ?? 'main';

        // Create log file path inside the deployment directory
        $logFilePath = "{$deploymentPath}/deployment.log";

        // Split deployment script into individual lines
        $scriptLines = array_filter(
            array_map('trim', explode("\n", $deploymentScript)),
            fn ($line) => ! empty($line) && ! str_starts_with($line, '#')
        );

        $commands = [];

        // Clone repository to new deployment directory
        $commands[] = function () use ($deploymentPath, $repository, $branch, $site, $deployment) {
            // Normalize repository to SSH URL
            if (str_starts_with($repository, 'git@') || str_starts_with($repository, 'ssh://')) {
                $repositorySshUrl = $repository;
            } elseif (str_starts_with($repository, 'https://github.com/')) {
                $repositorySshUrl = preg_replace('#^https://github\.com/(.+?)(?:\.git)?$#', 'git@github.com:$1.git', $repository);
            } elseif (preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $repository)) {
                $repositorySshUrl = sprintf('git@github.com:%s.git', $repository);
            } else {
                throw new RuntimeException('Invalid repository format');
            }

            // Check if site has dedicated deploy key and transform URL if needed
            if ($site->has_dedicated_deploy_key) {
                $repositorySshUrl = str_replace('git@github.com:', "git@github.com-site-{$site->id}:", $repositorySshUrl);
            }

            // Configure Git SSH command
            $sshKeyPath = '/home/brokeforge/.ssh/id_rsa';
            $gitSshCommand = sprintf(
                'GIT_SSH_COMMAND="ssh -i %s -o StrictHostKeyChecking=accept-new -o IdentitiesOnly=yes -o UserKnownHostsFile=/home/brokeforge/.ssh/known_hosts"',
                $sshKeyPath
            );

            // Clone repository (log file will be created after this)
            $cloneCommand = sprintf(
                'rm -rf %s && %s git clone -b %s %s %s',
                escapeshellarg($deploymentPath),
                $gitSshCommand,
                escapeshellarg($branch),
                escapeshellarg($repositorySshUrl),
                escapeshellarg($deploymentPath)
            );

            $process = $this->server->ssh('brokeforge')->setTimeout(300)->execute($cloneCommand);

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Failed to clone repository');
            }

            Log::info('Repository cloned to deployment directory', [
                'deployment_id' => $deployment->id,
                'deployment_path' => $deploymentPath,
            ]);
        };

        // Create log file inside the deployment directory (after clone creates the directory)
        $commands[] = function () use ($logFilePath) {
            $remoteCommand = sprintf(
                'touch %s && chown brokeforge:brokeforge %s && chmod 644 %s && echo "=== Deployment Started at $(date) ===" > %s',
                escapeshellarg($logFilePath),
                escapeshellarg($logFilePath),
                escapeshellarg($logFilePath),
                escapeshellarg($logFilePath)
            );

            $process = $this->server->ssh('brokeforge')->execute($remoteCommand);

            if (! $process->isSuccessful()) {
                Log::error('Failed to create deployment log file', [
                    'log_file_path' => $logFilePath,
                    'exit_code' => $process->getExitCode(),
                    'output' => $process->getOutput(),
                ]);

                throw new RuntimeException('Failed to create deployment log file on remote server');
            }
        };

        // Store log file path in deployment record for frontend polling
        $commands[] = function () use ($logFilePath, $deployment) {
            $deployment->update([
                'log_file_path' => $logFilePath,
            ]);
        };

        // Create symlinks to shared directories
        // Note: We must remove existing directories/files before creating symlinks
        // because git clone creates these directories from the repository,
        // and `ln -sfn` will create a symlink INSIDE an existing directory
        // rather than replacing it.
        $commands[] = function () use ($deploymentPath) {
            $symlinkCommands = sprintf(
                'rm -rf %s/storage && ln -sfn ../shared/storage %s/storage && '.
                'rm -f %s/.env && ln -sfn ../shared/.env %s/.env && '.
                'rm -rf %s/vendor && ln -sfn ../shared/vendor %s/vendor && '.
                'rm -rf %s/node_modules && ln -sfn ../shared/node_modules %s/node_modules',
                escapeshellarg($deploymentPath),
                escapeshellarg($deploymentPath),
                escapeshellarg($deploymentPath),
                escapeshellarg($deploymentPath),
                escapeshellarg($deploymentPath),
                escapeshellarg($deploymentPath),
                escapeshellarg($deploymentPath),
                escapeshellarg($deploymentPath)
            );

            $process = $this->server->ssh('brokeforge')->execute($symlinkCommands);

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Failed to create shared directory symlinks');
            }
        };

        // Create Laravel storage directory structure (required before composer install)
        // Ownership is set to {appUser}:www-data so PHP-FPM can write
        $appUser = $this->getAppUser();
        $commands[] = function () use ($siteRoot, $deploymentPath, $appUser) {
            $storageCommands = sprintf(
                'mkdir -p %s/shared/storage/framework/cache/data && '.
                'mkdir -p %s/shared/storage/framework/sessions && '.
                'mkdir -p %s/shared/storage/framework/testing && '.
                'mkdir -p %s/shared/storage/framework/views && '.
                'mkdir -p %s/shared/storage/logs && '.
                'mkdir -p %s/shared/storage/app/public && '.
                'mkdir -p %s/bootstrap/cache && '.
                'chmod -R 775 %s/shared/storage && '.
                'chmod 775 %s/bootstrap/cache && '.
                'sudo chown -R %s:www-data %s/shared/storage && '.
                'sudo chown -R %s:www-data %s/bootstrap/cache',
                escapeshellarg($siteRoot),
                escapeshellarg($siteRoot),
                escapeshellarg($siteRoot),
                escapeshellarg($siteRoot),
                escapeshellarg($siteRoot),
                escapeshellarg($siteRoot),
                escapeshellarg($deploymentPath),
                escapeshellarg($siteRoot),
                escapeshellarg($deploymentPath),
                $appUser,
                escapeshellarg($siteRoot),
                $appUser,
                escapeshellarg($deploymentPath)
            );

            $process = $this->server->ssh('brokeforge')->execute($storageCommands);

            if (! $process->isSuccessful()) {
                Log::warning('Failed to create Laravel storage structure during deployment', [
                    'deployment_path' => $deploymentPath,
                ]);
                // Don't throw - site might not be Laravel
            }
        };

        // Execute each line of the deployment script in the new deployment directory
        foreach ($scriptLines as $index => $line) {
            $commands[] = function () use ($deploymentPath, $line, $logFilePath, $deployment, $index, $site) {
                // Rewrite php and composer commands to use the site's PHP version
                $rewrittenLine = $this->rewritePhpCommands($line, $site->php_version);

                // Log the command being executed
                $logCommandCommand = sprintf(
                    'echo "\n=== Running: %s ===" >> %s',
                    addcslashes($rewrittenLine, '"\\$`'),
                    escapeshellarg($logFilePath)
                );
                $this->server->ssh('brokeforge')->execute($logCommandCommand);

                // Add safe.directory config and execute the command with output redirection
                $remoteCommand = sprintf(
                    'git config --global --add safe.directory %s && cd %s && %s >> %s 2>&1',
                    escapeshellarg($deploymentPath),
                    escapeshellarg($deploymentPath),
                    $rewrittenLine,
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
                        'line' => $rewrittenLine,
                        'exit_code' => $process->getExitCode(),
                    ]);

                    throw new RuntimeException("Deployment failed at line: {$rewrittenLine}");
                }

                Log::info("Deployment line #{$index} completed", [
                    'deployment_id' => $deployment->id,
                    'line' => $rewrittenLine,
                ]);
            };
        }

        // Capture current Git commit SHA
        $commands[] = function () use ($deploymentPath) {
            $remoteCommand = sprintf(
                'git config --global --add safe.directory %s && cd %s && git rev-parse HEAD 2>/dev/null || echo ""',
                escapeshellarg($deploymentPath),
                escapeshellarg($deploymentPath)
            );

            $process = $this->server->ssh('brokeforge')
                ->execute($remoteCommand);

            $this->commitSha = trim($process->getOutput()) ?: null;
        };

        // Atomically swap the site symlink to the new deployment
        $commands[] = function () use ($site, $siteSymlink, $timestamp, $logFilePath, $deployment) {
            $symlinkCommand = sprintf(
                'ln -sfn deployments/%s/%s %s && echo "\n=== Symlink updated to deployment %s ===" >> %s',
                escapeshellarg($site->domain),
                $timestamp,
                escapeshellarg($siteSymlink),
                $timestamp,
                escapeshellarg($logFilePath)
            );

            $process = $this->server->ssh('brokeforge')->execute($symlinkCommand);

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Failed to update site symlink');
            }

            Log::info('Site symlink updated to new deployment', [
                'deployment_id' => $deployment->id,
                'timestamp' => $timestamp,
            ]);
        };

        // Reload PHP-FPM to pick up any changes
        $commands[] = function () use ($site, $logFilePath) {
            $reloadCommand = sprintf(
                'sudo service php%s-fpm reload && echo "\n=== PHP-FPM reloaded ===" >> %s',
                $site->php_version,
                escapeshellarg($logFilePath)
            );

            $process = $this->server->ssh('brokeforge')->execute($reloadCommand);

            if (! $process->isSuccessful()) {
                Log::warning('Failed to reload PHP-FPM', [
                    'deployment_id' => $site->id,
                ]);
                // Don't throw - this is not critical
            }
        };

        // Update deployment record with deployment_path
        $commands[] = function () use ($deployment, $deploymentPath) {
            $deployment->update([
                'deployment_path' => $deploymentPath,
            ]);
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

    /**
     * Prune old deployments, keeping the most recent N deployments
     */
    protected function pruneOldDeployments(ServerSite $site, int $keep = 14): void
    {
        // Get all successful deployments with paths, ordered by created_at DESC
        $deployments = $site->deployments()
            ->where('status', 'success')
            ->whereNotNull('deployment_path')
            ->orderByDesc('created_at')
            ->get();

        if ($deployments->count() <= $keep) {
            Log::info("No deployments to prune for site #{$site->id}", [
                'total_deployments' => $deployments->count(),
                'keep' => $keep,
            ]);

            return;
        }

        // Skip the first N deployments (keep them), get the rest to delete
        $deploymentsToDelete = $deployments->skip($keep);

        Log::info("Pruning old deployments for site #{$site->id}", [
            'total_deployments' => $deployments->count(),
            'to_delete' => $deploymentsToDelete->count(),
            'keep' => $keep,
        ]);

        foreach ($deploymentsToDelete as $deployment) {
            try {
                // Delete deployment directory from remote server
                $remoteCommand = sprintf(
                    'rm -rf %s',
                    escapeshellarg($deployment->deployment_path)
                );

                $process = $this->server->ssh('brokeforge')->execute($remoteCommand);

                if ($process->isSuccessful()) {
                    // Update deployment record to mark path as deleted
                    $deployment->update(['deployment_path' => null]);

                    Log::info('Deleted old deployment directory', [
                        'deployment_id' => $deployment->id,
                        'deployment_path' => $deployment->deployment_path,
                    ]);
                } else {
                    Log::warning('Failed to delete old deployment directory', [
                        'deployment_id' => $deployment->id,
                        'deployment_path' => $deployment->deployment_path,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Error deleting old deployment directory', [
                    'deployment_id' => $deployment->id,
                    'deployment_path' => $deployment->deployment_path,
                    'error' => $e->getMessage(),
                ]);
                // Don't throw - continue pruning other deployments
            }
        }
    }

    /**
     * Get the application user for file ownership.
     *
     * Returns the brokeforge user from credentials, or defaults to 'brokeforge'.
     */
    protected function getAppUser(): string
    {
        $brokeforgeCredential = $this->server->credentials()
            ->where('user', 'brokeforge')
            ->first();

        return $brokeforgeCredential?->getUsername() ?: 'brokeforge';
    }

    /**
     * Rewrite php and composer commands to use the site's PHP version.
     */
    protected function rewritePhpCommands(string $command, string $phpVersion): string
    {
        $phpBinary = "/usr/bin/php{$phpVersion}";
        $composerCommand = "{$phpBinary} /usr/local/bin/composer";

        // Replace standalone 'php ' at start of command or after && or ;
        $command = preg_replace('/^php\s/', "{$phpBinary} ", $command);
        $command = preg_replace('/(\s*&&\s*|\s*;\s*)php\s/', "$1{$phpBinary} ", $command);

        // Replace standalone 'composer ' at start of command or after && or ;
        $command = preg_replace('/^composer\s/', "{$composerCommand} ", $command);
        $command = preg_replace('/(\s*&&\s*|\s*;\s*)composer\s/', "$1{$composerCommand} ", $command);

        return $command;
    }

    public function milestones(): Milestones
    {
        return new SiteGitDeploymentInstallerMilestones;
    }
}
