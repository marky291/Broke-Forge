<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Packages\Services\SourceProvider\Github\GitHubOAuthHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Manages source provider OAuth connections (GitHub, GitLab, etc.).
 *
 * Handles the OAuth flow for connecting Git hosting providers,
 * enabling features like automatic webhook management and repository access.
 */
class SourceProviderController extends Controller
{
    /**
     * Redirect to GitHub for OAuth authorization.
     */
    public function connectGitHub(Request $request, Server $server): RedirectResponse
    {
        // Store the server ID in the session to redirect back after OAuth
        $request->session()->put('source_provider_server_id', $server->id);

        $handler = new GitHubOAuthHandler;

        return $handler->redirect();
    }

    /**
     * Handle the OAuth callback from GitHub.
     */
    public function callbackGitHub(Request $request): RedirectResponse
    {
        $handler = new GitHubOAuthHandler;

        try {
            $handler->handleCallback($request->user());

            $serverId = $request->session()->pull('source_provider_server_id');

            if ($serverId) {
                return redirect()
                    ->route('servers.sites', $serverId)
                    ->with('success', 'GitHub connected successfully. You can now create sites.')
                    ->with('open_add_site_modal', true);
            }

            return redirect()
                ->route('dashboard')
                ->with('success', 'GitHub connected successfully.');
        } catch (\Exception $e) {
            report($e);

            $serverId = $request->session()->pull('source_provider_server_id');

            if ($serverId) {
                return redirect()
                    ->route('servers.sites', $serverId)
                    ->with('error', 'Failed to connect GitHub: '.$e->getMessage());
            }

            return redirect()
                ->route('dashboard')
                ->with('error', 'Failed to connect GitHub.');
        }
    }

    /**
     * Disconnect GitHub source provider.
     */
    public function disconnectGitHub(Request $request, Server $server): RedirectResponse
    {
        $handler = new GitHubOAuthHandler;

        try {
            $handler->disconnect($request->user());

            return redirect()
                ->route('servers.settings', $server)
                ->with('success', 'GitHub disconnected successfully.');
        } catch (\Exception $e) {
            report($e);

            return redirect()
                ->route('servers.settings', $server)
                ->with('error', 'Failed to disconnect GitHub.');
        }
    }
}
