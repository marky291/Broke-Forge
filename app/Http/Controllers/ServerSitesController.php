<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PreparesSiteData;
use App\Http\Requests\Servers\StoreSiteRequest;
use App\Http\Resources\ServerResource;
use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Enums\CredentialType;
use App\Packages\Enums\GitStatus;
use App\Packages\Services\Sites\ProvisionedSiteInstallerJob;
use App\Packages\Services\Sites\SiteRemoverJob;
use App\Packages\Services\SourceProvider\Github\GitHubApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ServerSitesController extends Controller
{
    use PreparesSiteData;

    public function index(Server $server): Response
    {
        return Inertia::render('servers/sites', [
            'server' => new ServerResource($server),
        ]);
    }

    public function show(Server $server, ServerSite $site): RedirectResponse
    {
        // Always redirect to the application page
        return redirect()->route('servers.sites.application', [$server, $site]);
    }

    /**
     * Get the deploy key for the server.
     */
    public function deployKey(Server $server): JsonResponse
    {
        $credential = $server->credential(CredentialType::BrokeForge);

        return response()->json([
            'deploy_key' => $credential?->public_key ?? 'Deploy key not available',
        ]);
    }

    public function store(StoreSiteRequest $request, Server $server): RedirectResponse
    {
        $validated = $request->validated();

        try {
            // Ensure GitHub OAuth is connected (mandatory)
            $githubProvider = $request->user()->githubProvider();
            if (! $githubProvider) {
                return back()->with('error', 'Please connect GitHub to create sites with repositories.');
            }

            // Parse repository owner/repo
            if (! str_contains($validated['git_repository'], '/')) {
                return back()->with('error', 'Repository must be in owner/repo format.');
            }

            [$owner, $repo] = explode('/', $validated['git_repository'], 2);

            // Get server's SSH public key for deploy key
            $deployKey = $server->credential(CredentialType::BrokeForge)->public_key;
            if (! $deployKey) {
                return back()->with('error', 'Server SSH key not found. Please contact support.');
            }

            // Generate unique deploy key title
            $deployKeyTitle = "BrokeForge - {$validated['domain']} - Server #{$server->id}";

            // Add deploy key to GitHub repository
            $apiClient = new GitHubApiClient($githubProvider);
            $deployKeyResponse = $apiClient->addDeployKey($owner, $repo, $deployKeyTitle, $deployKey, true);

            if (! $deployKeyResponse->successful()) {
                $errorMessage = 'Failed to add deploy key to GitHub repository';
                $responseData = $deployKeyResponse->json();
                if (isset($responseData['message'])) {
                    $errorMessage .= ': '.$responseData['message'];
                }

                Log::error('GitHub deploy key addition failed', [
                    'repository' => $validated['git_repository'],
                    'status' => $deployKeyResponse->status(),
                    'response' => $responseData,
                ]);

                return back()->with('error', $errorMessage);
            }

            $deployKeyData = $deployKeyResponse->json();

            // Build configuration with Git repository and deploy key metadata
            $configuration = [
                'application_type' => 'application',
                'git_repository' => [
                    'provider' => 'github',
                    'repository' => $validated['git_repository'],
                    'branch' => $validated['git_branch'],
                    'deploy_key_id' => $deployKeyData['id'],
                    'deploy_key_title' => $deployKeyTitle,
                ],
            ];

            // Create site with Git status as "installing"
            $site = ServerSite::create([
                'server_id' => $server->id,
                'domain' => $validated['domain'],
                'php_version' => $validated['php_version'],
                'ssl_enabled' => $validated['ssl'],
                'status' => 'provisioning',
                'document_root' => "/home/brokeforge/{$validated['domain']}/public",
                'nginx_config_path' => "/etc/nginx/sites-available/{$validated['domain']}",
                'configuration' => $configuration,
                'git_status' => GitStatus::Installing,
            ]);

            // Dispatch site installation job with site ID
            ProvisionedSiteInstallerJob::dispatch($server, $site->id);

            return back()->with('success', 'Site provisioning started. Deploy key added to repository automatically.');
        } catch (\Throwable $e) {
            Log::error('Failed to create site with auto deploy key', [
                'error' => $e->getMessage(),
                'repository' => $validated['git_repository'] ?? 'unknown',
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to start site provisioning: '.$e->getMessage());
        }
    }

    /**
     * Uninstall a site from the server
     */
    public function uninstall(Server $server, ServerSite $site): RedirectResponse
    {
        // Attempt to remove deploy key from GitHub before uninstalling
        $this->removeDeployKeyFromGitHub($site);

        // Set status to uninstalling
        $site->update(['status' => 'uninstalling']);

        // Dispatch site removal job
        SiteRemoverJob::dispatch($server, $site);

        return redirect()
            ->route('servers.sites', $server)
            ->with('success', 'Site uninstallation started');
    }

    /**
     * Delete a site from the server (typically for failed installations)
     */
    public function destroy(Server $server, ServerSite $site): RedirectResponse
    {
        try {
            // Attempt to remove deploy key from GitHub before deleting
            $this->removeDeployKeyFromGitHub($site);

            // Set status to removing to indicate cleanup is in progress
            $site->update(['status' => 'removing']);

            // Dispatch site removal job to clean up any partial installation
            SiteRemoverJob::dispatch($server, $site);

            return redirect()
                ->route('servers.sites', $server)
                ->with('success', 'Site deletion started. Any partial installation will be cleaned up.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to delete site: '.$e->getMessage());
        }
    }

    /**
     * Attempt to remove deploy key from GitHub repository.
     *
     * This method gracefully handles failures (e.g., OAuth disconnected, insufficient permissions)
     * and logs warnings instead of blocking site deletion.
     */
    private function removeDeployKeyFromGitHub(ServerSite $site): void
    {
        try {
            // Check if site has deploy key metadata
            $deployKeyId = $site->configuration['git_repository']['deploy_key_id'] ?? null;
            $repository = $site->configuration['git_repository']['repository'] ?? null;

            if (! $deployKeyId || ! $repository) {
                return; // No deploy key to remove
            }

            // Check if GitHub is still connected
            $githubProvider = auth()->user()?->githubProvider();
            if (! $githubProvider) {
                Log::warning('Cannot remove deploy key: GitHub not connected', [
                    'site_id' => $site->id,
                    'repository' => $repository,
                    'deploy_key_id' => $deployKeyId,
                ]);

                return;
            }

            // Parse repository owner/repo
            if (! str_contains($repository, '/')) {
                Log::warning('Invalid repository format for deploy key removal', [
                    'site_id' => $site->id,
                    'repository' => $repository,
                ]);

                return;
            }

            [$owner, $repo] = explode('/', $repository, 2);

            // Attempt to remove deploy key
            $apiClient = new GitHubApiClient($githubProvider);
            $response = $apiClient->removeDeployKey($owner, $repo, $deployKeyId);

            if ($response->successful()) {
                Log::info('Successfully removed deploy key from GitHub', [
                    'site_id' => $site->id,
                    'repository' => $repository,
                    'deploy_key_id' => $deployKeyId,
                ]);
            } else {
                Log::warning('Failed to remove deploy key from GitHub', [
                    'site_id' => $site->id,
                    'repository' => $repository,
                    'deploy_key_id' => $deployKeyId,
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
            }
        } catch (\Throwable $e) {
            // Log the error but don't block site deletion
            Log::warning('Error removing deploy key from GitHub', [
                'site_id' => $site->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
