<?php

namespace App\Http\Controllers;

use App\Http\Resources\ServerSiteResource;
use App\Models\Server;
use App\Models\ServerDeployment;
use App\Models\ServerSite;
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

        // ✅ THEN dispatch job with deployment record ID
        SiteGitDeploymentJob::dispatch($server, $deployment->id);

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
