<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Packages\Services\SourceProvider\Github\GitHubApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Handles fetching GitHub repositories and branches for site creation.
 */
class GitHubRepositoriesController extends Controller
{
    /**
     * Fetch all accessible GitHub repositories (without branches).
     *
     * Returns an array of repository names.
     * Format: { repositories: ['owner/repo1', 'owner/repo2', ...], connected: true }
     */
    public function index(Request $request, Server $server): JsonResponse
    {
        // Authorize: Ensure the authenticated user owns this server
        if ($server->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized access to this server.');
        }

        $githubProvider = $request->user()->githubProvider();

        // Return empty state if GitHub not connected
        if (! $githubProvider) {
            return response()->json([
                'repositories' => [],
                'connected' => false,
            ]);
        }

        // Cache key unique to this user
        $cacheKey = "github_repositories_{$request->user()->id}";

        // Try to get cached repositories (cache for 10 minutes)
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json([
                'repositories' => $cached,
                'connected' => true,
                'cached' => true,
            ]);
        }

        try {
            $apiClient = new GitHubApiClient($githubProvider);

            // Fetch user repositories (limited to 100 to reduce API calls)
            $reposResponse = $apiClient->getRepositories(100);

            if (! $reposResponse->successful()) {
                Log::warning('Failed to fetch GitHub repositories', [
                    'user_id' => $request->user()->id,
                    'status' => $reposResponse->status(),
                ]);

                return response()->json([
                    'repositories' => [],
                    'connected' => true,
                    'error' => 'Failed to fetch repositories from GitHub',
                ], 500);
            }

            $repos = $reposResponse->json();

            // Check GitHub rate limits from response headers
            $rateLimit = $reposResponse->header('X-RateLimit-Remaining');
            if ($rateLimit !== null && (int) $rateLimit < 10) {
                Log::warning('GitHub API rate limit low', [
                    'user_id' => $request->user()->id,
                    'remaining' => $rateLimit,
                ]);
            }

            // Extract repository names (owner/repo format)
            $repositories = [];
            foreach ($repos as $repo) {
                $owner = $repo['owner']['login'] ?? null;
                $repoName = $repo['name'] ?? null;

                if ($owner && $repoName) {
                    $repositories[] = "{$owner}/{$repoName}";
                }
            }

            // Cache the results for 10 minutes to reduce API calls
            Cache::put($cacheKey, $repositories, now()->addMinutes(10));

            return response()->json([
                'repositories' => $repositories,
                'connected' => true,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching GitHub repositories', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'repositories' => [],
                'connected' => true,
                'error' => 'An error occurred while fetching repositories',
            ], 500);
        }
    }

    /**
     * Fetch branches for a specific GitHub repository.
     *
     * Returns an array of branch names for the specified repository.
     * Format: { branches: ['main', 'develop', 'feature-x', ...] }
     */
    public function branches(Request $request, Server $server, string $owner, string $repo): JsonResponse
    {
        // Authorize: Ensure the authenticated user owns this server
        if ($server->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized access to this server.');
        }

        $githubProvider = $request->user()->githubProvider();

        // Return empty state if GitHub not connected
        if (! $githubProvider) {
            return response()->json([
                'branches' => [],
                'error' => 'GitHub not connected',
            ], 400);
        }

        // Cache key unique to this user and repository
        $cacheKey = "github_branches_{$request->user()->id}_{$owner}_{$repo}";

        // Try to get cached branches (cache for 10 minutes)
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json([
                'branches' => $cached,
                'cached' => true,
            ]);
        }

        try {
            $apiClient = new GitHubApiClient($githubProvider);

            // Fetch branches for this specific repository
            $branchesResponse = $apiClient->getBranches($owner, $repo);

            if (! $branchesResponse->successful()) {
                Log::warning('Failed to fetch GitHub branches', [
                    'user_id' => $request->user()->id,
                    'repository' => "{$owner}/{$repo}",
                    'status' => $branchesResponse->status(),
                ]);

                return response()->json([
                    'branches' => [],
                    'error' => 'Failed to fetch branches from GitHub',
                ], 500);
            }

            $branchesData = $branchesResponse->json();

            // Extract branch names
            $branches = array_map(
                fn ($branch) => $branch['name'] ?? 'unknown',
                $branchesData
            );

            // Cache the results for 10 minutes
            Cache::put($cacheKey, $branches, now()->addMinutes(10));

            return response()->json([
                'branches' => $branches,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching GitHub branches', [
                'user_id' => $request->user()->id,
                'repository' => "{$owner}/{$repo}",
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'branches' => [],
                'error' => 'An error occurred while fetching branches',
            ], 500);
        }
    }

    /**
     * Check repository permissions for the authenticated user.
     *
     * Returns permission details including whether the user can add deploy keys.
     * Format: { can_add_deploy_keys: boolean, has_admin: boolean, has_push: boolean, has_pull: boolean }
     */
    public function permissions(Request $request, Server $server, string $owner, string $repo): JsonResponse
    {
        // Authorize: Ensure the authenticated user owns this server
        if ($server->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized access to this server.');
        }

        $githubProvider = $request->user()->githubProvider();

        // Return empty state if GitHub not connected
        if (! $githubProvider) {
            return response()->json([
                'error' => 'GitHub not connected',
                'can_add_deploy_keys' => false,
            ], 400);
        }

        try {
            $apiClient = new GitHubApiClient($githubProvider);

            // Fetch repository details including permissions
            $repoResponse = $apiClient->getRepository($owner, $repo);

            if (! $repoResponse->successful()) {
                $status = $repoResponse->status();

                if ($status === 404) {
                    return response()->json([
                        'error' => 'Repository not found or you don\'t have access',
                        'can_add_deploy_keys' => false,
                    ], 404);
                }

                if ($status === 403) {
                    return response()->json([
                        'error' => 'Access denied to repository',
                        'can_add_deploy_keys' => false,
                    ], 403);
                }

                return response()->json([
                    'error' => 'Failed to fetch repository permissions',
                    'can_add_deploy_keys' => false,
                ], 500);
            }

            $repoData = $repoResponse->json();
            $permissions = $repoData['permissions'] ?? [];

            $hasAdmin = $permissions['admin'] ?? false;
            $hasPush = $permissions['push'] ?? false;
            $hasPull = $permissions['pull'] ?? false;

            // Can add deploy keys if user has admin or push permissions
            $canAddDeployKeys = $hasAdmin || $hasPush;

            return response()->json([
                'can_add_deploy_keys' => $canAddDeployKeys,
                'has_admin' => $hasAdmin,
                'has_push' => $hasPush,
                'has_pull' => $hasPull,
                'repository' => $repoData['full_name'] ?? "{$owner}/{$repo}",
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching repository permissions', [
                'user_id' => $request->user()->id,
                'repository' => "{$owner}/{$repo}",
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'An error occurred while fetching permissions',
                'can_add_deploy_keys' => false,
            ], 500);
        }
    }
}
