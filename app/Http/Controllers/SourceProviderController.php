<?php

namespace App\Http\Controllers;

use App\Packages\Services\SourceProvider\Github\GitHubOAuthHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Manages source provider OAuth connections (GitHub, GitLab, etc.).
 *
 * Handles the OAuth flow for connecting Git hosting providers,
 * enabling features like automatic webhook management and repository access.
 */
class SourceProviderController extends Controller
{
    /**
     * Display the source providers settings page.
     */
    public function index(Request $request): \Inertia\Response
    {
        return Inertia::render('settings/source-providers', [
            'githubProvider' => $request->user()->githubProvider(),
        ]);
    }

    /**
     * Redirect to GitHub for OAuth authorization.
     */
    public function connectGitHub(Request $request): RedirectResponse
    {
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

            return redirect()
                ->route('source-providers.edit')
                ->with('success', 'GitHub connected successfully.');
        } catch (\Exception $e) {
            report($e);

            return redirect()
                ->route('source-providers.edit')
                ->with('error', 'Failed to connect GitHub.');
        }
    }

    /**
     * Disconnect GitHub source provider.
     */
    public function disconnectGitHub(Request $request): RedirectResponse
    {
        $handler = new GitHubOAuthHandler;

        try {
            $handler->disconnect($request->user());

            return redirect()
                ->route('source-providers.edit')
                ->with('success', 'GitHub disconnected successfully.');
        } catch (\Exception $e) {
            report($e);

            return redirect()
                ->route('source-providers.edit')
                ->with('error', 'Failed to disconnect GitHub.');
        }
    }
}
