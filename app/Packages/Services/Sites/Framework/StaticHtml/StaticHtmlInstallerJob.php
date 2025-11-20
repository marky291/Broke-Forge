<?php

namespace App\Packages\Services\Sites\Framework\StaticHtml;

use App\Enums\TaskStatus;
use App\Models\ServerSite;
use App\Packages\Services\Sites\Framework\BaseFrameworkInstaller;

/**
 * Static HTML Installation Job
 *
 * Handles queued Static HTML site installation on remote servers with real-time status updates.
 */
class StaticHtmlInstallerJob extends BaseFrameworkInstaller
{
    /**
     * Get framework-specific installation steps for Static HTML.
     */
    protected function getFrameworkSteps(ServerSite $site): array
    {
        $steps = [
            ['name' => 'Initializing deployment', 'description' => 'Creating deployment directories and structure'],
            ['name' => 'Configuring Nginx', 'description' => 'Setting up web server configuration'],
        ];

        // Add Git cloning step if repository is configured
        if (isset($site->configuration['git_repository'])) {
            $steps[] = ['name' => 'Cloning Git repository', 'description' => 'Fetching site files from repository'];
        }

        $steps[] = ['name' => 'Finalizing installation', 'description' => 'Setting permissions and activating site'];

        return $steps;
    }

    /**
     * Execute Static HTML-specific installation logic.
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
                sprintf('mkdir -p %s', escapeshellarg($deploymentPath)),
                sprintf('echo \'<!DOCTYPE html><html><head><title>Welcome</title></head><body><h1>Welcome to %s</h1><p>Your site is ready. Upload your files to get started.</p></body></html>\' > %s/index.html', $site->domain, escapeshellarg($deploymentPath)),
            ]);
        }

        // Step 4: Finalize installation
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
        return 'Static HTML installation';
    }
}
