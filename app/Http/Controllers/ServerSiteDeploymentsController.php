<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PreparesSiteData;
use App\Models\Server;
use App\Models\ServerDeployment;
use App\Models\ServerSite;
use App\Packages\Enums\GitStatus;
use App\Packages\Services\Sites\Deployment\SiteGitDeploymentJob;
use App\Packages\Services\SourceProvider\Github\GitHubWebhookManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServerSiteDeploymentsController extends Controller
{
    use PreparesSiteData;

    /**
     * Display the deployments page for a site.
     */
    public function show(Server $server, ServerSite $site): RedirectResponse|Response
    {
        // Check if site has Git repository installed
        if (! $site->hasGitRepository()) {
            return redirect()->route('servers.sites.git-repository', [$server, $site])
                ->with('error', 'Git repository must be installed before deploying.');
        }

        $gitConfig = $site->getGitConfiguration();

        // Get deployment script from configuration
        $deploymentScript = $site->getDeploymentScript();

        // Get deployment history
        $deployments = $site->deployments()
            ->latest()
            ->paginate(10)
            ->through(fn (ServerDeployment $deployment) => [
                'id' => $deployment->id,
                'status' => $deployment->status,
                'deployment_script' => $deployment->deployment_script,
                'output' => $deployment->output,
                'error_output' => $deployment->error_output,
                'exit_code' => $deployment->exit_code,
                'commit_sha' => $deployment->commit_sha,
                'branch' => $deployment->branch,
                'duration_ms' => $deployment->duration_ms,
                'duration_seconds' => $deployment->getDurationSeconds(),
                'started_at' => $deployment->started_at,
                'completed_at' => $deployment->completed_at,
                'created_at' => $deployment->created_at,
            ]);

        // Get latest deployment
        $latestDeployment = $site->latestDeployment;

        return Inertia::render('servers/site-deployments', [
            'server' => $this->prepareServerData($server),
            'site' => $this->prepareSiteData($site, [
                'document_root',
                'last_deployment_sha',
                'last_deployed_at',
                'auto_deploy_enabled',
            ]),
            'deploymentScript' => $deploymentScript,
            'gitConfig' => $gitConfig,
            'deployments' => $deployments,
            'latestDeployment' => $latestDeployment ? [
                'id' => $latestDeployment->id,
                'status' => $latestDeployment->status,
                'output' => $latestDeployment->output,
                'error_output' => $latestDeployment->error_output,
                'commit_sha' => $latestDeployment->commit_sha,
                'branch' => $latestDeployment->branch,
                'duration_seconds' => $latestDeployment->getDurationSeconds(),
                'started_at' => $latestDeployment->started_at,
                'completed_at' => $latestDeployment->completed_at,
            ] : null,
        ]);
    }

    /**
     * Update the deployment script for a site.
     */
    public function update(Request $request, Server $server, ServerSite $site): RedirectResponse
    {
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
        // Check if site has Git repository installed
        if (! $site->hasGitRepository()) {
            return redirect()->route('servers.sites.git-repository', [$server, $site])
                ->with('error', 'Git repository must be installed before deploying.');
        }

        // Get deployment script
        $deploymentScript = $site->getDeploymentScript();

        // Create deployment record
        $deployment = ServerDeployment::create([
            'server_id' => $server->id,
            'server_site_id' => $site->id,
            'status' => 'pending',
            'deployment_script' => $deploymentScript,
        ]);

        // Dispatch deployment job
        SiteGitDeploymentJob::dispatch($server, $site, $deployment);

        return redirect()
            ->route('servers.sites.deployments', [$server, $site])
            ->with('success', 'Deployment started. Refresh the page to see progress.');
    }

    /**
     * Get deployment status for polling.
     */
    public function status(Server $server, ServerSite $site, ServerDeployment $deployment): JsonResponse
    {
        return response()->json([
            'id' => $deployment->id,
            'status' => $deployment->status,
            'output' => $deployment->output,
            'error_output' => $deployment->error_output,
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
     * Toggle auto-deploy for a site.
     */
    public function toggleAutoDeploy(Request $request, Server $server, ServerSite $site): RedirectResponse
    {
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
