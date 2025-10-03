<?php

namespace App\Packages\Services\SourceProvider\Github;

use App\Models\SourceProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * GitHub API Client for interacting with GitHub's REST API.
 *
 * Provides methods for repository management, webhook operations,
 * and user information retrieval using OAuth access tokens.
 */
class GitHubApiClient
{
    private const API_BASE_URL = 'https://api.github.com';

    public function __construct(
        private readonly SourceProvider $sourceProvider
    ) {
    }

    /**
     * Get authenticated HTTP client with GitHub API headers.
     */
    private function client(): PendingRequest
    {
        return Http::withToken($this->sourceProvider->access_token)
            ->acceptJson()
            ->baseUrl(self::API_BASE_URL);
    }

    /**
     * Get authenticated user information.
     */
    public function getUser(): Response
    {
        return $this->client()->get('/user');
    }

    /**
     * Get user's repositories.
     */
    public function getRepositories(int $perPage = 100): Response
    {
        return $this->client()->get('/user/repos', [
            'per_page' => $perPage,
            'sort' => 'updated',
            'affiliation' => 'owner,collaborator',
        ]);
    }

    /**
     * Get a specific repository.
     */
    public function getRepository(string $owner, string $repo): Response
    {
        return $this->client()->get("/repos/{$owner}/{$repo}");
    }

    /**
     * Get repository branches.
     */
    public function getBranches(string $owner, string $repo): Response
    {
        return $this->client()->get("/repos/{$owner}/{$repo}/branches");
    }

    /**
     * Create a webhook for a repository.
     *
     * @param  array<string, mixed>  $config  Webhook configuration (url, content_type, secret)
     * @param  array<string>  $events  Events to trigger webhook (default: ['push'])
     */
    public function createWebhook(string $owner, string $repo, array $config, array $events = ['push']): Response
    {
        return $this->client()->post("/repos/{$owner}/{$repo}/hooks", [
            'name' => 'web',
            'active' => true,
            'events' => $events,
            'config' => $config,
        ]);
    }

    /**
     * Delete a webhook from a repository.
     */
    public function deleteWebhook(string $owner, string $repo, string $webhookId): Response
    {
        return $this->client()->delete("/repos/{$owner}/{$repo}/hooks/{$webhookId}");
    }

    /**
     * Get webhook details.
     */
    public function getWebhook(string $owner, string $repo, string $webhookId): Response
    {
        return $this->client()->get("/repos/{$owner}/{$repo}/hooks/{$webhookId}");
    }

    /**
     * Update a webhook.
     *
     * @param  array<string, mixed>  $config  Webhook configuration to update
     * @param  array<string>|null  $events  Events to trigger webhook
     */
    public function updateWebhook(string $owner, string $repo, string $webhookId, array $config, ?array $events = null): Response
    {
        $data = [
            'config' => $config,
            'active' => true,
        ];

        if ($events !== null) {
            $data['events'] = $events;
        }

        return $this->client()->patch("/repos/{$owner}/{$repo}/hooks/{$webhookId}", $data);
    }

    /**
     * Get the latest commit for a branch.
     */
    public function getLatestCommit(string $owner, string $repo, string $branch = 'main'): Response
    {
        return $this->client()->get("/repos/{$owner}/{$repo}/commits/{$branch}");
    }
}
