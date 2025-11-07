<?php

namespace App\Http\Controllers;

use App\Http\Resources\ServerSiteResource;
use App\Models\Server;
use App\Models\ServerDeployment;
use App\Models\ServerSite;
use App\Packages\Services\Sites\Deployment\SiteDeploymentRollbackJob;
use App\Packages\Services\Sites\Deployment\SiteGitDeploymentJob;
use App\Packages\Services\SourceProvider\Github\GitHubWebhookManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServerSiteDeploymentsController extends Controller
{
    /**
     * Display the deployments page for a site.
     */
    public function show(Server $server, ServerSite $site): RedirectResponse|Response
    {
        $this->authorize('view', $server);

        // Check if site has Git repository installed
        if (! $site->hasGitRepository()) {
            return redirect()->route('servers.sites.application.git.setup', [$server, $site])
                ->with('error', 'Git repository must be installed before deploying.');
        }

        return Inertia::render('servers/site-deployments', [
            'site' => new ServerSiteResource($site),
        ]);
    }

    /**
     * Update the deployment script for a site.
     */
    public function update(Request $request, Server $server, ServerSite $site): RedirectResponse
    {
        $this->authorize('update', $server);

        $validated = $request->validate([
            'deployment_script' => ['required', 'string', 'max:5000'],
        ]);

        $site->updateDeploymentScript($validated['deployment_script']);

        return redirect()
            ->route('servers.sites.deployments', [$server, $site])
            ->with('success', 'Deployment script updated successfully.');
    }

    /**
     * Execute a deployment for a site.
     */
    public function deploy(Request $request, Server $server, ServerSite $site): RedirectResponse
    {
        $this->authorize('update', $server);

        // Check if site has Git repository installed
        if (! $site->hasGitRepository()) {
            return redirect()->route('servers.sites.application.git.setup', [$server, $site])
                ->with('error', 'Git repository must be installed before deploying.');
        }

        // Get deployment script
        $deploymentScript = $site->getDeploymentScript();

        // ✅ CREATE RECORD FIRST with 'pending' status
        $deployment = ServerDeployment::create([
            'server_id' => $server->id,
            'server_site_id' => $site->id,
            'status' => 'pending',
            'deployment_script' => $deploymentScript,
        ]);

        // ✅ THEN dispatch job with deployment record
        SiteGitDeploymentJob::dispatch($server, $deployment);

        return redirect()
            ->route('servers.sites.deployments', [$server, $site])
            ->with('success', 'Deployment started. Refresh the page to see progress.');
    }

    /**
     * Rollback to a previous deployment.
     */
    public function rollback(Request $request, Server $server, ServerSite $site, ServerDeployment $deployment): RedirectResponse
    {
        $this->authorize('update', $server);

        // Validate deployment can be rolled back to
        if (! $deployment->canRollback()) {
            return redirect()
                ->route('servers.sites.deployments', [$server, $site])
                ->with('error', 'Cannot rollback to this deployment - it may have failed or the deployment directory no longer exists.');
        }

        // Prevent rolling back to current active deployment
        if ($site->active_deployment_id === $deployment->id) {
            return redirect()
                ->route('servers.sites.deployments', [$server, $site])
                ->with('error', 'This deployment is already active.');
        }

        // Dispatch rollback job (follows Reverb Package Lifecycle Pattern)
        SiteDeploymentRollbackJob::dispatch($server, $site, $deployment);

        return redirect()
            ->route('servers.sites.deployments', [$server, $site])
            ->with('success', 'Rollback initiated. Refresh the page to see progress.');
    }

    /**
     * Get deployment status for polling.
     */
    public function status(Server $server, ServerSite $site, ServerDeployment $deployment): JsonResponse
    {
        $this->authorize('view', $server);

        return response()->json([
            'id' => $deployment->id,
            'status' => $deployment->status,
            'exit_code' => $deployment->exit_code,
            'commit_sha' => $deployment->commit_sha,
            'branch' => $deployment->branch,
            'duration_ms' => $deployment->duration_ms,
            'duration_seconds' => $deployment->getDurationSeconds(),
            'started_at' => $deployment->started_at,
            'completed_at' => $deployment->completed_at,
            'is_running' => $deployment->isRunning(),
            'is_success' => $deployment->isSuccess(),
            'is_failed' => $deployment->isFailed(),
        ]);
    }

    /**
     * Stream log output from remote server for active deployments.
     */
    public function streamLog(Server $server, ServerSite $site, ServerDeployment $deployment): JsonResponse
    {
        $this->authorize('view', $server);

        // If no log file path, return empty response
        if (! $deployment->log_file_path) {
            \Illuminate\Support\Facades\Log::warning('Deployment log file path not set', [
                'deployment_id' => $deployment->id,
                'server_id' => $server->id,
            ]);

            return response()->json([
                'output' => null,
                'file_size' => 0,
                'status' => $deployment->status->value,
                'is_running' => $deployment->isPending() || $deployment->isRunning(),
                'error' => 'Log file path not configured for this deployment',
            ]);
        }

        // Check if file exists and is readable on remote server
        $checkCommand = sprintf(
            'if [ -f %s ]; then if [ -r %s ]; then echo "readable"; else echo "not_readable"; fi; else echo "not_found"; fi',
            escapeshellarg($deployment->log_file_path),
            escapeshellarg($deployment->log_file_path)
        );

        $checkProcess = $server->ssh('brokeforge')->execute($checkCommand);
        $fileStatus = trim($checkProcess->getOutput());

        \Illuminate\Support\Facades\Log::info('Checking deployment log file', [
            'deployment_id' => $deployment->id,
            'log_file_path' => $deployment->log_file_path,
            'file_status' => $fileStatus,
            'exit_code' => $checkProcess->getExitCode(),
        ]);

        if ($fileStatus === 'not_found') {
            return response()->json([
                'output' => null,
                'file_size' => 0,
                'status' => $deployment->status->value,
                'is_running' => $deployment->isPending() || $deployment->isRunning(),
                'is_success' => $deployment->isSuccess(),
                'is_failed' => $deployment->isFailed(),
                'error' => 'Log file not found on remote server. It may have been deleted or the deployment did not complete.',
            ]);
        }

        if ($fileStatus === 'not_readable') {
            return response()->json([
                'output' => null,
                'file_size' => 0,
                'status' => $deployment->status->value,
                'is_running' => $deployment->isPending() || $deployment->isRunning(),
                'is_success' => $deployment->isSuccess(),
                'is_failed' => $deployment->isFailed(),
                'error' => 'Log file exists but is not readable. Check file permissions on the remote server.',
            ]);
        }

        // Read log file from remote server
        $process = $server->ssh('brokeforge')
            ->execute(sprintf('cat %s 2>&1', escapeshellarg($deployment->log_file_path)));

        if (! $process->isSuccessful()) {
            \Illuminate\Support\Facades\Log::error('Failed to read deployment log file', [
                'deployment_id' => $deployment->id,
                'log_file_path' => $deployment->log_file_path,
                'exit_code' => $process->getExitCode(),
                'output' => $process->getOutput(),
            ]);

            return response()->json([
                'output' => null,
                'file_size' => 0,
                'status' => $deployment->status->value,
                'is_running' => $deployment->isPending() || $deployment->isRunning(),
                'is_success' => $deployment->isSuccess(),
                'is_failed' => $deployment->isFailed(),
                'error' => 'Failed to read log file from remote server. Please try again.',
            ]);
        }

        $output = $process->getOutput();

        return response()->json([
            'output' => $output,
            'file_size' => strlen($output),
            'status' => $deployment->status->value,
            'is_running' => $deployment->isPending() || $deployment->isRunning(),
            'is_success' => $deployment->isSuccess(),
            'is_failed' => $deployment->isFailed(),
        ]);
    }

    /**
     * Toggle auto-deploy for a site.
     */
    public function toggleAutoDeploy(Request $request, Server $server, ServerSite $site): RedirectResponse
    {
        $this->authorize('update', $server);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $webhookManager = GitHubWebhookManager::forSite($site);

        if (! $webhookManager) {
            return redirect()
                ->route('servers.sites.deployments', [$server, $site])
                ->with('error', 'GitHub must be connected to enable auto-deploy. Please connect GitHub in server settings.');
        }

        if ($validated['enabled']) {
            // Enable auto-deploy by creating webhook
            $result = $webhookManager->createWebhook($site);

            if (! $result['success']) {
                return redirect()
                    ->route('servers.sites.deployments', [$server, $site])
                    ->with('error', 'Failed to enable auto-deploy: '.$result['error']);
            }

            return redirect()
                ->route('servers.sites.deployments', [$server, $site])
                ->with('success', 'Auto-deploy enabled successfully.');
        } else {
            // Disable auto-deploy by deleting webhook
            $result = $webhookManager->deleteWebhook($site);

            if (! $result['success']) {
                return redirect()
                    ->route('servers.sites.deployments', [$server, $site])
                    ->with('error', 'Failed to disable auto-deploy: '.$result['error']);
            }

            return redirect()
                ->route('servers.sites.deployments', [$server, $site])
                ->with('success', 'Auto-deploy disabled successfully.');
        }
    }
}
