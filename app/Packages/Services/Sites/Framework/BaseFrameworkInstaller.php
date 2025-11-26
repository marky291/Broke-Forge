<?php

namespace App\Packages\Services\Sites\Framework;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

/**
 * Base Framework Installer
 *
 * Abstract base class for all framework-specific site installers.
 * Provides shared installation logic and step tracking.
 */
abstract class BaseFrameworkInstaller extends Taskable
{
    /**
     * The number of exceptions to allow before failing.
     */
    public $maxExceptions = 1;

    /**
     * Track the current installation step for error reporting.
     */
    protected ?int $currentStep = null;

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
        return TaskStatus::Installing->value;
    }

    /**
     * Get the status value for success.
     */
    protected function getSuccessStatus(): mixed
    {
        return TaskStatus::Active->value;
    }

    /**
     * Get the status value for failure.
     */
    protected function getFailedStatus(): mixed
    {
        return TaskStatus::Failed->value;
    }

    /**
     * Execute the actual installation operation.
     */
    protected function executeOperation(Model $model): void
    {
        /** @var ServerSite $model */

        // Initialize installation state with all steps as pending
        $this->initializeInstallationSteps($model);

        // Execute framework-specific installation
        $this->installFramework($model);
    }

    /**
     * Initialize installation state with framework-specific steps.
     */
    protected function initializeInstallationSteps(ServerSite $site): void
    {
        $steps = $this->getFrameworkSteps($site);
        $installationState = collect();

        foreach ($steps as $index => $step) {
            $installationState->put($index + 1, TaskStatus::Pending->value);
        }

        $site->update(['installation_state' => $installationState]);
    }

    /**
     * Update a specific installation step status.
     */
    protected function updateInstallationStep(ServerSite $site, int $stepNumber, TaskStatus $status): void
    {
        // Track current step for error reporting
        $this->currentStep = $stepNumber;

        $site->refresh();
        $installationState = $site->installation_state ?? collect();
        $installationState->put($stepNumber, $status->value);
        $site->update(['installation_state' => $installationState]);
    }

    /**
     * Execute commands on the remote server.
     */
    protected function executeCommands(array $commands): void
    {
        foreach ($commands as $command) {
            if ($command instanceof \Closure) {
                $output = $command();

                // If closure returns a string, execute it as SSH command
                if (is_string($output)) {
                    $this->executeSshCommand($output);
                }
            } elseif (is_string($command)) {
                $this->executeSshCommand($command);
            }
        }
    }

    /**
     * Execute a single SSH command on the remote server.
     */
    protected function executeSshCommand(string $command): void
    {
        $process = $this->server->ssh('brokeforge')
            ->setTimeout(570)
            ->execute($command);

        if (! $process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput());
            $standardOutput = trim($process->getOutput());

            // Build comprehensive error message with remote output
            $errorParts = ["Command failed: {$command}"];

            // Provide context for common failure scenarios
            $context = $this->getErrorContext($command, $errorOutput);
            if ($context) {
                $errorParts[] = "Context: {$context}";
            }

            // Include stderr if available
            if ($errorOutput) {
                $errorParts[] = "Error Output:\n{$errorOutput}";
            }

            // Include stdout if available (Laravel errors often appear here)
            if ($standardOutput) {
                $errorParts[] = "Standard Output:\n{$standardOutput}";
            }

            throw new \RuntimeException(implode("\n\n", $errorParts));
        }
    }

    /**
     * Get contextual error information based on the command that failed.
     */
    protected function getErrorContext(string $command, string $errorOutput): ?string
    {
        // Laravel artisan commands
        if (str_contains($command, 'php artisan migrate')) {
            return 'Migration failed. Common causes: database connection issues, missing .env file, or migration errors.';
        }

        if (str_contains($command, 'composer install')) {
            return 'Composer install failed. Check for missing dependencies or version conflicts.';
        }

        // Nginx configuration errors
        if (str_contains($command, 'nginx -t')) {
            return 'Nginx configuration validation failed. Check the generated config file for syntax errors.';
        }

        if (str_contains($command, 'sites-available') || str_contains($command, 'sites-enabled')) {
            return 'Failed to write nginx configuration. This may indicate a shell escaping or file permission issue.';
        }

        // Git clone errors
        if (str_contains($command, 'git clone')) {
            return 'Failed to clone git repository. Verify repository URL and access credentials.';
        }

        // Permission errors
        if (str_contains($errorOutput, 'Permission denied')) {
            return 'Permission denied. Ensure the brokeforge user has necessary permissions.';
        }

        return null;
    }

    /**
     * Create deployment directory structure.
     */
    protected function createDeploymentDirectory(ServerSite $site, string &$deploymentPath): array
    {
        $domain = $site->domain;
        $timestamp = now()->format('dmY-His');

        $siteRoot = "/home/brokeforge/deployments/{$domain}";
        $deploymentPath = "{$siteRoot}/{$timestamp}";

        $appUser = $this->getAppUser();

        return [
            "mkdir -p {$siteRoot}/shared/storage",
            "mkdir -p {$siteRoot}/shared/vendor",
            "mkdir -p {$siteRoot}/shared/node_modules",
            "touch {$siteRoot}/shared/.env",
            "mkdir -p {$deploymentPath}",
            // Use www-data as group so PHP-FPM can write to storage directories
            "sudo chown -R {$appUser}:www-data {$siteRoot}",
            "sudo chmod -R 775 {$siteRoot}",
        ];
    }

    /**
     * Create symlink from site path to deployment path.
     */
    protected function createSymlink(ServerSite $site, string $deploymentPath): array
    {
        $siteSymlink = "/home/brokeforge/{$site->domain}";

        return [
            "ln -sfn {$deploymentPath} {$siteSymlink}",
        ];
    }

    /**
     * Configure Nginx for the site.
     */
    protected function configureNginx(ServerSite $site): array
    {
        $domain = $site->domain;
        $siteSymlink = "/home/brokeforge/{$domain}";
        $documentRootPath = $site->document_root;

        $nginxConfig = view('nginx.site', [
            'domain' => $domain,
            'documentRoot' => $documentRootPath,
            'phpSocket' => "/var/run/php/php{$site->php_version}-fpm.sock",
            'ssl' => $site->ssl_enabled,
            'sslCertPath' => $site->ssl_cert_path,
            'sslKeyPath' => $site->ssl_key_path,
        ])->render();

        // Escape double quotes in nginx config to prevent shell command breakage
        $nginxConfig = str_replace('"', '\\"', $nginxConfig);
        // Escape dollar signs to prevent bash variable expansion
        $nginxConfig = str_replace('$', '\\$', $nginxConfig);

        return [
            "sudo mkdir -p /var/log/nginx/{$domain}",
            "sudo bash -c \"cat > /etc/nginx/sites-available/{$domain} << 'NGINX_CONFIG_EOF'\n{$nginxConfig}\nNGINX_CONFIG_EOF\"",
            "sudo ln -sf /etc/nginx/sites-available/{$domain} /etc/nginx/sites-enabled/{$domain}",
            'sudo nginx -t',
            'sudo systemctl reload nginx',
        ];
    }

    /**
     * Clone Git repository if configured.
     */
    protected function cloneGitRepository(ServerSite $site, string $deploymentPath): array
    {
        $config = $site->configuration ?? [];

        if (! isset($config['git_repository'])) {
            return [];
        }

        $gitConfig = $config['git_repository'];
        $repository = $gitConfig['repository'] ?? null;
        $branch = $gitConfig['branch'] ?? 'main';

        if (! $repository) {
            return [];
        }

        // Normalize repository to SSH URL
        $repositorySshUrl = $this->normalizeGitRepository($repository);

        // Handle dedicated deploy keys
        $sshConfigCommands = [];
        $transformedRepositoryUrl = $repositorySshUrl;

        if ($site->has_dedicated_deploy_key) {
            $sshConfigCommands = $this->generateSshConfigCommands($site);
            $transformedRepositoryUrl = $this->transformGitUrlForSshConfig($repositorySshUrl, $site->id);
        }

        $sshKeyPath = '/home/brokeforge/.ssh/id_rsa';
        $gitSshCommand = sprintf(
            'GIT_SSH_COMMAND="ssh -i %s -o StrictHostKeyChecking=accept-new -o IdentitiesOnly=yes -o UserKnownHostsFile=/home/brokeforge/.ssh/known_hosts"',
            $sshKeyPath
        );

        $brokeforgeCredential = $this->server->credentials()
            ->where('user', 'brokeforge')
            ->first();
        $appUser = $brokeforgeCredential?->getUsername() ?: 'brokeforge';

        return array_merge(
            $sshConfigCommands,
            [
                sprintf('rm -rf %s', escapeshellarg($deploymentPath)),
                sprintf(
                    '%s git clone -b %s %s %s',
                    $gitSshCommand,
                    escapeshellarg($branch),
                    escapeshellarg($transformedRepositoryUrl),
                    escapeshellarg($deploymentPath)
                ),
                sprintf('sudo chown -R %s:%s %s', $appUser, $appUser, escapeshellarg($deploymentPath)),
                sprintf('sudo chmod -R 775 %s', escapeshellarg($deploymentPath)),
            ]
        );
    }

    /**
     * Create shared directory symlinks.
     *
     * Note: We must remove existing directories/files before creating symlinks
     * because git clone may create these directories from the repository,
     * and `ln -sfn` will create a symlink INSIDE an existing directory
     * rather than replacing it.
     */
    protected function createSharedSymlinks(string $deploymentPath): array
    {
        return [
            sprintf('rm -rf %s/storage && ln -sfn ../shared/storage %s/storage', escapeshellarg($deploymentPath), escapeshellarg($deploymentPath)),
            sprintf('rm -f %s/.env && ln -sfn ../shared/.env %s/.env', escapeshellarg($deploymentPath), escapeshellarg($deploymentPath)),
            sprintf('rm -rf %s/vendor && ln -sfn ../shared/vendor %s/vendor', escapeshellarg($deploymentPath), escapeshellarg($deploymentPath)),
            sprintf('rm -rf %s/node_modules && ln -sfn ../shared/node_modules %s/node_modules', escapeshellarg($deploymentPath), escapeshellarg($deploymentPath)),
        ];
    }

    /**
     * Create Laravel storage directory structure.
     *
     * Laravel requires specific storage subdirectories to exist before
     * composer install runs, as the post-autoload hook executes artisan
     * commands that need to access these paths.
     *
     * Ownership is set to {appUser}:www-data so PHP-FPM (www-data) can write.
     */
    protected function createLaravelStorageStructure(string $siteRoot, string $deploymentPath): array
    {
        $appUser = $this->getAppUser();

        return [
            sprintf('mkdir -p %s/shared/storage/framework/cache/data', escapeshellarg($siteRoot)),
            sprintf('mkdir -p %s/shared/storage/framework/sessions', escapeshellarg($siteRoot)),
            sprintf('mkdir -p %s/shared/storage/framework/testing', escapeshellarg($siteRoot)),
            sprintf('mkdir -p %s/shared/storage/framework/views', escapeshellarg($siteRoot)),
            sprintf('mkdir -p %s/shared/storage/logs', escapeshellarg($siteRoot)),
            sprintf('mkdir -p %s/shared/storage/app/public', escapeshellarg($siteRoot)),
            sprintf('mkdir -p %s/bootstrap/cache', escapeshellarg($deploymentPath)),
            sprintf('chmod -R 775 %s/shared/storage', escapeshellarg($siteRoot)),
            sprintf('chmod 775 %s/bootstrap/cache', escapeshellarg($deploymentPath)),
            // Ensure www-data group ownership for PHP-FPM write access
            sprintf('sudo chown -R %s:www-data %s/shared/storage', $appUser, escapeshellarg($siteRoot)),
            sprintf('sudo chown -R %s:www-data %s/bootstrap/cache', $appUser, escapeshellarg($deploymentPath)),
        ];
    }

    /**
     * Finalize installation by updating site status.
     */
    protected function finalizeInstallation(ServerSite $site, array $additionalData = []): void
    {
        $site->update(array_merge([
            'status' => TaskStatus::Active->value,
            'installed_at' => now(),
        ], $additionalData));
    }

    /**
     * Normalize Git repository URL to SSH format.
     */
    protected function normalizeGitRepository(string $repository): string
    {
        if (str_starts_with($repository, 'git@') || str_starts_with($repository, 'ssh://')) {
            return $repository;
        }

        if (str_starts_with($repository, 'https://github.com/')) {
            return preg_replace('#^https://github\.com/(.+?)(?:\.git)?$#', 'git@github.com:$1.git', $repository);
        }

        if (preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $repository)) {
            return sprintf('git@github.com:%s.git', $repository);
        }

        return $repository;
    }

    /**
     * Generate SSH config commands for site-specific deploy keys.
     */
    protected function generateSshConfigCommands(ServerSite $site): array
    {
        $siteId = $site->id;
        $keyPath = "/home/brokeforge/.ssh/site_{$siteId}_rsa";

        $sshConfigEntry = <<<EOF
Host github.com-site-{$siteId}
  HostName github.com
  IdentityFile {$keyPath}
  IdentitiesOnly yes
  StrictHostKeyChecking accept-new
  UserKnownHostsFile /home/brokeforge/.ssh/known_hosts
EOF;

        return [
            'mkdir -p /home/brokeforge/.ssh',
            'touch /home/brokeforge/.ssh/config',
            'chmod 600 /home/brokeforge/.ssh/config',
            sprintf(
                'if ! grep -q "Host github.com-site-%d" /home/brokeforge/.ssh/config; then cat >> /home/brokeforge/.ssh/config << \'SSH_CONFIG_EOF\'\n%s\nSSH_CONFIG_EOF\nfi',
                $siteId,
                $sshConfigEntry
            ),
        ];
    }

    /**
     * Transform Git URL to use SSH config host alias.
     */
    protected function transformGitUrlForSshConfig(string $originalUrl, int $siteId): string
    {
        if (str_starts_with($originalUrl, 'git@github.com:')) {
            return str_replace('git@github.com:', "git@github.com-site-{$siteId}:", $originalUrl);
        }

        if (str_starts_with($originalUrl, 'ssh://git@github.com/')) {
            return str_replace('ssh://git@github.com/', "ssh://git@github.com-site-{$siteId}/", $originalUrl);
        }

        return $originalUrl;
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
            'framework' => $model->siteFramework->slug,
        ];
    }

    /**
     * Get human-readable operation name for logging.
     */
    protected function getOperationName(): string
    {
        return 'Site installation';
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
            'failed_step' => $this->currentStep,
        ];
    }

    /**
     * Handle job failure - mark the current step as failed.
     */
    public function failed(\Throwable $exception): void
    {
        // Mark the step that was in progress as failed
        if ($this->currentStep !== null) {
            $site = ServerSite::find($this->siteId);
            if ($site) {
                $installationState = $site->installation_state ?? collect();
                $installationState->put($this->currentStep, TaskStatus::Failed->value);
                $site->update(['installation_state' => $installationState]);
            }
        }

        // Call parent to handle status update and logging
        parent::failed($exception);
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
     * Get the PHP binary path for the site's PHP version.
     */
    protected function getPhpBinaryPath(ServerSite $site): string
    {
        return "/usr/bin/php{$site->php_version}";
    }

    /**
     * Get the composer command using the site's PHP version.
     */
    protected function getComposerCommand(ServerSite $site): string
    {
        return "{$this->getPhpBinaryPath($site)} /usr/local/bin/composer";
    }

    /**
     * Get framework-specific installation steps.
     *
     * Each framework installer must define its own steps.
     * Returns array of step definitions with 'name' and 'description'.
     *
     * @return array<int, array{name: string, description: string}>
     */
    abstract protected function getFrameworkSteps(ServerSite $site): array;

    /**
     * Execute framework-specific installation logic.
     *
     * This method should call updateInstallationStep() at each milestone
     * to track progress in real-time.
     */
    abstract protected function installFramework(ServerSite $site): void;
}
