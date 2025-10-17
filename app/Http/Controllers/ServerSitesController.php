<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PreparesSiteData;
use App\Http\Requests\Servers\StoreSiteRequest;
use App\Http\Resources\ServerResource;
use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Enums\GitStatus;
use App\Packages\Services\Sites\ProvisionedSiteInstallerJob;
use App\Packages\Services\Sites\SiteDeployKeyGenerator;
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
        // Authorize user can view this server
        $this->authorize('view', $server);

        return Inertia::render('servers/sites', [
            'server' => new ServerResource($server),
        ]);
    }

    public function show(Server $server, ServerSite $site): RedirectResponse
    {
        // Authorize user can view this server
        $this->authorize('view', $server);

        // Always redirect to the application page
        return redirect()->route('servers.sites.application', [$server, $site]);
    }

    /**
     * Get the deploy key for the server.
     */
    public function deployKey(Server $server): JsonResponse
    {
        // Authorize user can view this server
        $this->authorize('view', $server);

        $credential = $server->credentials()
            ->where('user', 'brokeforge')
            ->first();

        return response()->json([
            'deploy_key' => $credential?->public_key ?? 'Deploy key not available',
        ]);
    }

    /**
     * Generate a dedicated deploy key for a specific site.
     */
    public function generateDeployKey(Server $server, ServerSite $site): JsonResponse
    {
        // Authorize user can update this server
        $this->authorize('update', $server);

        try {
            // Check if site already has a dedicated deploy key
            if ($site->has_dedicated_deploy_key) {
                return response()->json([
                    'error' => 'This site already has a dedicated deploy key.',
                ], 400);
            }

            // Generate the deploy key
            $generator = new SiteDeployKeyGenerator($server);
            $publicKey = $generator->execute($site);

            Log::info('Deploy key generated for site', [
                'server_id' => $server->id,
                'site_id' => $site->id,
                'domain' => $site->domain,
            ]);

            return response()->json([
                'public_key' => $publicKey,
                'title' => $site->dedicated_deploy_key_title,
                'message' => 'Deploy key generated successfully. Add this key to your repository\'s deploy keys.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to generate deploy key', [
                'server_id' => $server->id,
                'site_id' => $site->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to generate deploy key: '.$e->getMessage(),
            ], 500);
        }
    }

    public function store(StoreSiteRequest $request, Server $server): RedirectResponse
    {
        // Authorize user can update this server
        $this->authorize('update', $server);

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

            // Initialize GitHub API client
            $apiClient = new GitHubApiClient($githubProvider);

            // Validate repository exists and user has access
            $repoResponse = $apiClient->getRepository($owner, $repo);
            if (! $repoResponse->successful()) {
                $errorMessage = 'Cannot access repository';

                if ($repoResponse->status() === 404) {
                    $errorMessage = "Repository '{$validated['git_repository']}' not found or you don't have access. Please verify the repository exists and you have admin or write permissions.";
                } elseif ($repoResponse->status() === 403) {
                    $errorMessage = 'Access denied to repository. Please check your GitHub OAuth permissions.';
                }

                Log::warning('GitHub repository validation failed', [
                    'repository' => $validated['git_repository'],
                    'status' => $repoResponse->status(),
                    'response' => $repoResponse->json(),
                ]);

                return back()->with('error', $errorMessage);
            }

            // Build configuration with Git repository information
            $configuration = [
                'application_type' => 'application',
                'git_repository' => [
                    'provider' => 'github',
                    'repository' => $validated['git_repository'],
                    'branch' => $validated['git_branch'],
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

            return back()->with('success', 'Site provisioning started.');
        } catch (\Throwable $e) {
            Log::error('Failed to create site', [
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
        // Authorize user can update this server
        $this->authorize('update', $server);

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
        // Authorize user can delete this server
        $this->authorize('delete', $server);

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
     *
     * Only handles dedicated per-site deploy keys stored in database fields.
     */
    private function removeDeployKeyFromGitHub(ServerSite $site): void
    {
        try {
            // Only handle dedicated deploy keys (stored in database fields)
            if (! $site->has_dedicated_deploy_key || ! $site->dedicated_deploy_key_id) {
                return; // No dedicated deploy key to remove
            }

            $deployKeyId = $site->dedicated_deploy_key_id;
            $repository = $site->configuration['git_repository']['repository'] ?? null;

            if (! $repository) {
                return;
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
