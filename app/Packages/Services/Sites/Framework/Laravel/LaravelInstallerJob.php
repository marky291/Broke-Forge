<?php

namespace App\Packages\Services\Sites\Framework\Laravel;

use App\Enums\TaskStatus;
use App\Models\ServerSite;
use App\Packages\Services\Sites\Framework\BaseFrameworkInstaller;

/**
 * Laravel Installation Job
 *
 * Handles queued Laravel installation on remote servers with real-time status updates.
 */
class LaravelInstallerJob extends BaseFrameworkInstaller
{
    /**
     * Get framework-specific installation steps for Laravel.
     */
    protected function getFrameworkSteps(ServerSite $site): array
    {
        $steps = [
            ['name' => 'Initializing deployment', 'description' => 'Creating deployment directories and structure'],
            ['name' => 'Configuring Nginx', 'description' => 'Setting up web server configuration'],
        ];

        // Add Git cloning step if repository is configured
        if (isset($site->configuration['git_repository'])) {
            $steps[] = ['name' => 'Cloning Git repository', 'description' => 'Fetching application code from repository'];
        }

        $steps = array_merge($steps, [
            ['name' => 'Creating environment file', 'description' => 'Generating Laravel .env configuration'],
            ['name' => 'Installing Composer dependencies', 'description' => 'Installing PHP packages'],
        ]);

        // Add NPM if node is configured
        if ($site->node_id) {
            $steps[] = ['name' => 'Installing NPM dependencies', 'description' => 'Installing JavaScript packages'];
        }

        $steps[] = ['name' => 'Running database migrations', 'description' => 'Setting up database tables'];

        // Add build step if node is configured
        if ($site->node_id) {
            $steps[] = ['name' => 'Building assets', 'description' => 'Compiling frontend assets'];
        }

        $steps[] = ['name' => 'Finalizing installation', 'description' => 'Setting permissions and activating site'];

        return $steps;
    }

    /**
     * Execute Laravel-specific installation logic.
     */
    protected function installFramework(ServerSite $site): void
    {
        $deploymentPath = '';
        $currentStep = 1;

        // Step 1: Initialize deployment
        $this->updateInstallationStep($site, $currentStep, TaskStatus::Installing);
        $commands = $this->createDeploymentDirectory($site, $deploymentPath);
        $this->executeCommands($commands);
        $this->updateInstallationStep($site, $currentStep++, TaskStatus::Success);

        // Step 2: Configure Nginx
        $this->updateInstallationStep($site, $currentStep, TaskStatus::Installing);
        $commands = $this->configureNginx($site);
        $this->executeCommands($commands);
        $this->updateInstallationStep($site, $currentStep++, TaskStatus::Success);

        // Step 3: Clone Git repository (if configured)
        if (isset($site->configuration['git_repository'])) {
            $this->updateInstallationStep($site, $currentStep, TaskStatus::Installing);
            $commands = $this->cloneGitRepository($site, $deploymentPath);
            $this->executeCommands($commands);

            // Create initial deployment record
            $this->createInitialDeployment($site, $deploymentPath);

            $this->updateInstallationStep($site, $currentStep++, TaskStatus::Success);
        } else {
            // Create placeholder if no Git
            $this->executeCommands([
                sprintf('mkdir -p %s/public', escapeshellarg($deploymentPath)),
                sprintf('echo \'<?php phpinfo();\' > %s/public/index.php', escapeshellarg($deploymentPath)),
            ]);
        }

        // Create shared symlinks
        $this->executeCommands($this->createSharedSymlinks($deploymentPath));

        // Create Laravel storage directory structure (required before composer install)
        $siteRoot = "/home/brokeforge/deployments/{$site->domain}";
        $this->executeCommands($this->createLaravelStorageStructure($siteRoot, $deploymentPath));

        // Step 4: Create and configure environment file
        $this->updateInstallationStep($site, $currentStep, TaskStatus::Installing);
        $commands = $this->configureEnvironmentFile($site, $deploymentPath);
        $this->executeCommands($commands);
        $this->updateInstallationStep($site, $currentStep++, TaskStatus::Success);

        // Step 5: Install Composer dependencies
        $this->updateInstallationStep($site, $currentStep, TaskStatus::Installing);
        if (isset($site->configuration['git_repository'])) {
            $this->executeCommands([
                sprintf('cd %s && %s install --no-dev --no-interaction --prefer-dist --optimize-autoloader', escapeshellarg($deploymentPath), $this->getComposerCommand($site)),
            ]);

            // Generate APP_KEY for Laravel encryption (must run AFTER composer install)
            $this->executeCommands([
                sprintf('cd %s && %s artisan key:generate --force',
                    escapeshellarg($deploymentPath),
                    $this->getPhpBinaryPath($site)
                ),
            ]);

            // Create public/storage symlink for serving uploaded files
            $this->executeCommands([
                sprintf('cd %s && %s artisan storage:link --force',
                    escapeshellarg($deploymentPath),
                    $this->getPhpBinaryPath($site)
                ),
            ]);
        }
        $this->updateInstallationStep($site, $currentStep++, TaskStatus::Success);

        // Step 6: Install NPM dependencies (if node is configured)
        if ($site->node_id) {
            $this->updateInstallationStep($site, $currentStep, TaskStatus::Installing);
            if (isset($site->configuration['git_repository'])) {
                $this->executeCommands([
                    sprintf('cd %s && npm install', escapeshellarg($deploymentPath)),
                ]);
            }
            $this->updateInstallationStep($site, $currentStep++, TaskStatus::Success);
        }

        // Step 7: Run database migrations
        $this->updateInstallationStep($site, $currentStep, TaskStatus::Installing);
        if (isset($site->configuration['git_repository']) && $site->database_id) {
            $this->executeCommands([
                sprintf('cd %s && %s artisan migrate --force', escapeshellarg($deploymentPath), $this->getPhpBinaryPath($site)),
            ]);
        }
        $this->updateInstallationStep($site, $currentStep++, TaskStatus::Success);

        // Step 8: Build assets (if node is configured)
        if ($site->node_id) {
            $this->updateInstallationStep($site, $currentStep, TaskStatus::Installing);
            if (isset($site->configuration['git_repository'])) {
                $this->executeCommands([
                    sprintf('cd %s && npm run build', escapeshellarg($deploymentPath)),
                ]);
            }
            $this->updateInstallationStep($site, $currentStep++, TaskStatus::Success);
        }

        // Final Step: Finalize installation
        $this->updateInstallationStep($site, $currentStep, TaskStatus::Installing);
        $commands = $this->createSymlink($site, $deploymentPath);
        $this->executeCommands($commands);

        // Update git status if repository was cloned
        $additionalData = [];
        if (isset($site->configuration['git_repository'])) {
            $additionalData['git_status'] = TaskStatus::Success;
            $additionalData['git_installed_at'] = now();
        }

        $this->finalizeInstallation($site, $additionalData);
        $this->updateInstallationStep($site, $currentStep, TaskStatus::Success);
    }

    /**
     * Create and configure Laravel environment file with database credentials.
     *
     * Writes directly to the shared/.env file to preserve symlink structure.
     * The deployment directory's .env is symlinked to ../shared/.env
     */
    protected function configureEnvironmentFile(ServerSite $site, string $deploymentPath): array
    {
        $commands = [];
        $siteRoot = "/home/brokeforge/deployments/{$site->domain}";
        $sharedEnvPath = "{$siteRoot}/shared/.env";

        // Copy .env.example directly to shared/.env (not through the symlink)
        // This preserves the symlink at {deploymentPath}/.env -> ../shared/.env
        $commands[] = sprintf(
            'if [ -f %s/.env.example ]; then cp %s/.env.example %s; fi',
            escapeshellarg($deploymentPath),
            escapeshellarg($deploymentPath),
            escapeshellarg($sharedEnvPath)
        );

        // Load the database relationship if database is configured
        if ($site->database_id) {
            $site->load('database');
            $database = $site->database;

            if ($database) {
                // Map database engine to Laravel DB_CONNECTION value
                $connectionType = match ($database->engine->value) {
                    'mysql', 'mariadb' => 'mysql',
                    'postgresql' => 'pgsql',
                    'mongodb' => 'mongodb',
                    'redis' => 'redis',
                    default => 'mysql',
                };

                // Generate sed commands to update database configuration in shared .env
                $dbConfig = [
                    'DB_CONNECTION' => $connectionType,
                    'DB_HOST' => '127.0.0.1',
                    'DB_PORT' => (string) $database->port,
                    'DB_DATABASE' => $database->name,
                    'DB_USERNAME' => 'root',
                ];

                // Add simple values (no special escaping needed)
                foreach ($dbConfig as $key => $value) {
                    $escapedValue = str_replace(['/', '&', '\\'], ['\\/', '\\&', '\\\\'], $value);

                    $commands[] = sprintf(
                        'if grep -q "^%s=" %s 2>/dev/null; then sed -i "s|^%s=.*|%s=%s|" %s; else echo "%s=%s" >> %s; fi',
                        $key,
                        escapeshellarg($sharedEnvPath),
                        $key,
                        $key,
                        $escapedValue,
                        escapeshellarg($sharedEnvPath),
                        $key,
                        $escapedValue,
                        escapeshellarg($sharedEnvPath)
                    );
                }

                // Handle password separately (may contain special characters that need quoting)
                $password = $database->root_password;
                $needsQuoting = preg_match('/[\s#"\']/', $password);
                if ($needsQuoting) {
                    $password = str_replace('"', '\\"', $password);
                    $password = "\"{$password}\"";
                }
                $escapedPassword = str_replace(['/', '&', '\\'], ['\\/', '\\&', '\\\\'], $password);

                $commands[] = sprintf(
                    'if grep -q "^DB_PASSWORD=" %s 2>/dev/null; then sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=%s|" %s; else echo "DB_PASSWORD=%s" >> %s; fi',
                    escapeshellarg($sharedEnvPath),
                    $escapedPassword,
                    escapeshellarg($sharedEnvPath),
                    $escapedPassword,
                    escapeshellarg($sharedEnvPath)
                );
            }
        }

        return $commands;
    }

    /**
     * Create initial deployment record in database.
     */
    protected function createInitialDeployment(ServerSite $site, string $deploymentPath): void
    {
        $gitConfig = $site->configuration['git_repository'];
        $branch = $gitConfig['branch'] ?? 'main';

        // Get commit SHA
        $commitSha = null;
        try {
            $process = $this->server->ssh('brokeforge')->execute("cd {$deploymentPath} && git rev-parse HEAD");
            if ($process->isSuccessful()) {
                $commitSha = trim($process->getOutput());
            }
        } catch (\Exception $e) {
            // Ignore errors getting commit SHA
        }

        // Create initial deployment record
        $initialDeployment = \App\Models\ServerDeployment::create([
            'server_id' => $site->server_id,
            'server_site_id' => $site->id,
            'status' => TaskStatus::Success,
            'deployment_script' => 'Initial deployment',
            'deployment_path' => $deploymentPath,
            'commit_sha' => $commitSha,
            'branch' => $branch,
            'started_at' => now(),
            'completed_at' => now(),
            'triggered_by' => 'system',
        ]);

        // Set as active deployment
        $site->update(['active_deployment_id' => $initialDeployment->id]);
    }

    /**
     * Get human-readable operation name for logging.
     */
    protected function getOperationName(): string
    {
        return 'Laravel installation';
    }
}
