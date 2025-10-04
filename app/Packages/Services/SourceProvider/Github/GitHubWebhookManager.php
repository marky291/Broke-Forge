<?php

namespace App\Packages\Services\SourceProvider\Github;

use App\Models\ServerSite;
use App\Models\SourceProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Manages GitHub webhooks for automatic deployments.
 *
 * Creates, updates, and deletes webhooks on GitHub repositories
 * to enable auto-deploy functionality for server sites.
 */
class GitHubWebhookManager
{
    public function __construct(
        private readonly SourceProvider $sourceProvider,
        private readonly GitHubApiClient $apiClient
    ) {}

    /**
     * Create a new instance for a server site.
     */
    public static function forSite(ServerSite $site): ?self
    {
        $user = $site->server->user;
        $githubProvider = $user->githubProvider();

        if (! $githubProvider) {
            return null;
        }

        $apiClient = new GitHubApiClient($githubProvider);

        return new self($githubProvider, $apiClient);
    }

    /**
     * Create a webhook for the site's repository.
     *
     * Generates a webhook secret, creates the webhook on GitHub,
     * and stores the webhook ID and secret on the site.
     *
     * @return array{success: bool, webhook_id: ?string, error: ?string}
     */
    public function createWebhook(ServerSite $site): array
    {
        $gitConfig = $site->getGitConfiguration();
        $repository = $gitConfig['repository'];

        if (! $repository) {
            return [
                'success' => false,
                'webhook_id' => null,
                'error' => 'No Git repository configured for this site',
            ];
        }

        // Parse repository (format: "owner/repo")
        [$owner, $repo] = $this->parseRepository($repository);

        if (! $owner || ! $repo) {
            return [
                'success' => false,
                'webhook_id' => null,
                'error' => 'Invalid repository format. Expected: owner/repo',
            ];
        }

        // Generate webhook secret
        $secret = Str::random(40);

        // Webhook callback URL
        $webhookUrl = route('webhooks.github', ['site' => $site->id]);

        // Create webhook via GitHub API
        $response = $this->apiClient->createWebhook($owner, $repo, [
            'url' => $webhookUrl,
            'content_type' => 'json',
            'secret' => $secret,
        ], ['push']);

        if (! $response->successful()) {
            $error = $response->json('message') ?? 'Failed to create webhook';

            Log::error('Failed to create GitHub webhook', [
                'site_id' => $site->id,
                'repository' => $repository,
                'error' => $error,
                'status' => $response->status(),
            ]);

            return [
                'success' => false,
                'webhook_id' => null,
                'error' => $error,
            ];
        }

        $webhookId = (string) $response->json('id');

        // Store webhook ID and secret on the site
        $site->update([
            'webhook_id' => $webhookId,
            'webhook_secret' => $secret,
            'auto_deploy_enabled' => true,
        ]);

        Log::info('GitHub webhook created successfully', [
            'site_id' => $site->id,
            'repository' => $repository,
            'webhook_id' => $webhookId,
        ]);

        return [
            'success' => true,
            'webhook_id' => $webhookId,
            'error' => null,
        ];
    }

    /**
     * Delete the webhook from GitHub.
     *
     * @return array{success: bool, error: ?string}
     */
    public function deleteWebhook(ServerSite $site): array
    {
        if (! $site->webhook_id) {
            return [
                'success' => false,
                'error' => 'No webhook configured for this site',
            ];
        }

        $gitConfig = $site->getGitConfiguration();
        $repository = $gitConfig['repository'];

        if (! $repository) {
            return [
                'success' => false,
                'error' => 'No Git repository configured for this site',
            ];
        }

        [$owner, $repo] = $this->parseRepository($repository);

        if (! $owner || ! $repo) {
            return [
                'success' => false,
                'error' => 'Invalid repository format',
            ];
        }

        $response = $this->apiClient->deleteWebhook($owner, $repo, $site->webhook_id);

        // GitHub returns 204 for successful deletion
        if ($response->successful() || $response->status() === 404) {
            // Clear webhook data from site (even if webhook was already deleted on GitHub)
            $site->update([
                'webhook_id' => null,
                'webhook_secret' => null,
                'auto_deploy_enabled' => false,
            ]);

            Log::info('GitHub webhook deleted successfully', [
                'site_id' => $site->id,
                'repository' => $repository,
                'webhook_id' => $site->webhook_id,
            ]);

            return [
                'success' => true,
                'error' => null,
            ];
        }

        $error = $response->json('message') ?? 'Failed to delete webhook';

        Log::error('Failed to delete GitHub webhook', [
            'site_id' => $site->id,
            'repository' => $repository,
            'webhook_id' => $site->webhook_id,
            'error' => $error,
            'status' => $response->status(),
        ]);

        return [
            'success' => false,
            'error' => $error,
        ];
    }

    /**
     * Parse repository string into owner and repo name.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function parseRepository(string $repository): array
    {
        $parts = explode('/', $repository);

        if (count($parts) !== 2) {
            return [null, null];
        }

        return [$parts[0], $parts[1]];
    }
}
