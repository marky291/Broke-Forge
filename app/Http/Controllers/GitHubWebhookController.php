<?php

namespace App\Http\Controllers;

use App\Models\ServerDeployment;
use App\Models\ServerSite;
use App\Packages\Services\Sites\Deployment\SiteGitDeploymentJob;
use App\Packages\Services\SourceProvider\Github\GitHubWebhookValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handles incoming GitHub webhook requests.
 *
 * Validates webhook signatures and triggers automatic deployments
 * when push events are received from GitHub.
 */
class GitHubWebhookController extends Controller
{
    /**
     * Handle incoming GitHub webhook.
     */
    public function __invoke(Request $request, ServerSite $site): JsonResponse
    {
        $validator = new GitHubWebhookValidator;

        // Validate webhook signature
        if (! $site->webhook_secret || ! $validator->validate($request, $site->webhook_secret)) {
            Log::warning('GitHub webhook signature validation failed', [
                'site_id' => $site->id,
                'delivery_id' => $validator->getDeliveryId($request),
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Only process push events
        if (! $validator->isPushEvent($request)) {
            return response()->json(['message' => 'Event type not supported'], 200);
        }

        // Check if auto-deploy is enabled
        if (! $site->auto_deploy_enabled) {
            return response()->json(['message' => 'Auto-deploy is disabled'], 200);
        }

        // Check if site has Git repository installed
        if (! $site->hasGitRepository()) {
            return response()->json(['error' => 'Git repository not configured'], 400);
        }

        // Get commit information
        $commitInfo = $validator->getCommitInfo($request);

        if (! $commitInfo) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        // Get site's configured branch
        $gitConfig = $site->getGitConfiguration();
        $configuredBranch = $gitConfig['branch'] ?? 'main';

        // Only deploy if push is to the configured branch
        if ($commitInfo['branch'] !== $configuredBranch) {
            return response()->json([
                'message' => 'Push to non-configured branch ignored',
                'pushed_branch' => $commitInfo['branch'],
                'configured_branch' => $configuredBranch,
            ], 200);
        }

        // Load server relationship
        $site->load('server');

        // ✅ CREATE RECORD FIRST with 'pending' status
        $deployment = ServerDeployment::create([
            'server_id' => $site->server->id,
            'server_site_id' => $site->id,
            'status' => 'pending',
            'deployment_script' => $site->getDeploymentScript(),
            'triggered_by' => 'webhook',
            'commit_sha' => $commitInfo['sha'],
            'branch' => $commitInfo['branch'],
        ]);

        // ✅ THEN dispatch job with deployment record
        SiteGitDeploymentJob::dispatch($site->server, $deployment);

        Log::info('Auto-deployment triggered via webhook', [
            'site_id' => $site->id,
            'deployment_id' => $deployment->id,
            'commit_sha' => $commitInfo['sha'],
            'branch' => $commitInfo['branch'],
            'delivery_id' => $validator->getDeliveryId($request),
        ]);

        return response()->json([
            'message' => 'Deployment triggered',
            'deployment_id' => $deployment->id,
            'commit' => $commitInfo['sha'],
            'branch' => $commitInfo['branch'],
        ], 200);
    }
}
