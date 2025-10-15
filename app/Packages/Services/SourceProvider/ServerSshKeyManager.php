<?php

namespace App\Packages\Services\SourceProvider;

use App\Models\Server;
use App\Models\SourceProvider;
use App\Packages\Enums\CredentialType;
use App\Packages\Services\SourceProvider\Github\GitHubApiClient;
use Illuminate\Support\Facades\Log;

/**
 * Manages server SSH keys on GitHub user accounts.
 *
 * Handles adding and removing server SSH keys to/from the authenticated user's
 * GitHub account (not repository-specific deploy keys).
 */
class ServerSshKeyManager
{
    public function __construct(
        private readonly Server $server,
        private readonly SourceProvider $sourceProvider
    ) {}

    /**
     * Add server's SSH key to user's GitHub account.
     */
    public function addServerKeyToGitHub(): bool
    {
        try {
            // Get server's BrokeForge SSH public key
            $credential = $this->server->credential(CredentialType::BrokeForge);
            if (! $credential || ! $credential->public_key) {
                Log::error('Server SSH key not found', ['server_id' => $this->server->id]);

                return false;
            }

            $publicKey = $credential->public_key;
            $title = "BrokeForge Server - {$this->server->vanity_name}";

            // Add key to GitHub user account
            $apiClient = new GitHubApiClient($this->sourceProvider);
            $response = $apiClient->addUserSshKey($title, $publicKey);

            if (! $response->successful()) {
                Log::error('Failed to add server SSH key to GitHub', [
                    'server_id' => $this->server->id,
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);

                return false;
            }

            $keyData = $response->json();

            // Update server fields
            $this->server->update([
                'source_provider_ssh_key_added' => true,
                'source_provider_ssh_key_id' => (string) $keyData['id'],
                'source_provider_ssh_key_title' => $title,
            ]);

            Log::info('Successfully added server SSH key to GitHub', [
                'server_id' => $this->server->id,
                'vanity_name' => $this->server->vanity_name,
                'key_id' => $keyData['id'],
                'key_title' => $title,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Error adding server SSH key to GitHub', [
                'server_id' => $this->server->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Remove server's SSH key from user's GitHub account.
     */
    public function removeServerKeyFromGitHub(): bool
    {
        try {
            if (! $this->server->source_provider_ssh_key_added || ! $this->server->source_provider_ssh_key_id) {
                Log::warning('No server SSH key to remove from GitHub', ['server_id' => $this->server->id]);

                return true; // Nothing to remove
            }

            $keyId = (int) $this->server->source_provider_ssh_key_id;

            // Remove key from GitHub
            $apiClient = new GitHubApiClient($this->sourceProvider);
            $response = $apiClient->removeUserSshKey($keyId);

            // 204 No Content or 404 Not Found are both success cases
            if (! $response->successful() && $response->status() !== 404) {
                Log::error('Failed to remove server SSH key from GitHub', [
                    'server_id' => $this->server->id,
                    'key_id' => $keyId,
                    'status' => $response->status(),
                ]);

                return false;
            }

            // Update server fields
            $this->server->update([
                'source_provider_ssh_key_added' => false,
                'source_provider_ssh_key_id' => null,
                'source_provider_ssh_key_title' => null,
            ]);

            Log::info('Successfully removed server SSH key from GitHub', [
                'server_id' => $this->server->id,
                'key_id' => $keyId,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Error removing server SSH key from GitHub', [
                'server_id' => $this->server->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check if server's SSH key is on user's GitHub account.
     */
    public function hasServerKeyOnGitHub(): bool
    {
        try {
            $credential = $this->server->credential(CredentialType::BrokeForge);
            if (! $credential || ! $credential->public_key) {
                return false;
            }

            $apiClient = new GitHubApiClient($this->sourceProvider);
            $response = $apiClient->getUserSshKeys();

            if (! $response->successful()) {
                Log::error('Failed to fetch user SSH keys from GitHub', [
                    'server_id' => $this->server->id,
                    'status' => $response->status(),
                ]);

                return false;
            }

            $keys = $response->json();
            $serverPublicKey = trim($credential->public_key);

            foreach ($keys as $key) {
                if (trim($key['key']) === $serverPublicKey) {
                    return true;
                }
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Error checking if server SSH key is on GitHub', [
                'server_id' => $this->server->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
