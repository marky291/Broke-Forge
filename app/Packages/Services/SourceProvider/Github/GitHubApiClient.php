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
    ) {}

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

    /**
     * Add a deploy key to a repository.
     *
     * @param  string  $owner  Repository owner
     * @param  string  $repo  Repository name
     * @param  string  $title  Deploy key title
     * @param  string  $key  SSH public key content
     * @param  bool  $readOnly  Whether key is read-only (default: true)
     * @return Response GitHub API response with 'id' field
     */
    public function addDeployKey(string $owner, string $repo, string $title, string $key, bool $readOnly = true): Response
    {
        return $this->client()->post("/repos/{$owner}/{$repo}/keys", [
            'title' => $title,
            'key' => $key,
            'read_only' => $readOnly,
        ]);
    }

    /**
     * Remove a deploy key from a repository.
     *
     * @param  string  $owner  Repository owner
     * @param  string  $repo  Repository name
     * @param  int  $keyId  The GitHub deploy key ID
     */
    public function removeDeployKey(string $owner, string $repo, int $keyId): Response
    {
        return $this->client()->delete("/repos/{$owner}/{$repo}/keys/{$keyId}");
    }

    /**
     * List all deploy keys for a repository.
     *
     * @param  string  $owner  Repository owner
     * @param  string  $repo  Repository name
     * @return Response Array of deploy keys with id, key, title, read_only
     */
    public function getDeployKeys(string $owner, string $repo): Response
    {
        return $this->client()->get("/repos/{$owner}/{$repo}/keys");
    }

    /**
     * List all SSH keys for the authenticated user.
     *
     * Retrieves all public SSH keys associated with the authenticated user's account.
     * Each key includes an id, key content, title, and metadata.
     *
     * @return Response Array of SSH keys with id, key, title, created_at, read_only
     */
    public function getUserSshKeys(): Response
    {
        return $this->client()->get('/user/keys');
    }

    /**
     * Add a new SSH key to the authenticated user's account.
     *
     * Creates a new public SSH key for the authenticated user. The key can be used
     * for Git operations across all repositories the user has access to.
     *
     * @param  string  $title  Descriptive name for the SSH key
     * @param  string  $key  SSH public key content (e.g., ssh-rsa AAAA...)
     * @return Response GitHub API response with 'id', 'key', 'title', 'created_at'
     */
    public function addUserSshKey(string $title, string $key): Response
    {
        return $this->client()->post('/user/keys', [
            'title' => $title,
            'key' => $key,
        ]);
    }

    /**
     * Remove an SSH key from the authenticated user's account.
     *
     * Deletes a specific SSH key from the authenticated user's account using the key ID.
     * This revokes the key's access to all repositories.
     *
     * @param  int  $keyId  The GitHub SSH key ID to remove
     * @return Response GitHub API response (204 No Content on success)
     */
    public function removeUserSshKey(int $keyId): Response
    {
        return $this->client()->delete("/user/keys/{$keyId}");
    }
}
