<?php

namespace App\Packages\Services\SourceProvider\Github;

use App\Models\SourceProvider;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Handles GitHub OAuth authentication flow.
 *
 * Manages the OAuth connection process for GitHub source providers,
 * including redirecting to GitHub for authorization and handling
 * the callback with access token storage.
 */
class GitHubOAuthHandler
{
    /**
     * Redirect to GitHub for OAuth authorization.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirect()
    {
        return Socialite::driver('github')
            ->scopes(['repo', 'admin:repo_hook', 'write:public_key'])
            ->redirect();
    }

    /**
     * Handle the OAuth callback from GitHub.
     *
     * Creates or updates the source provider record with the
     * authenticated user's access token and profile information.
     *
     * @throws \Exception If GitHub user data is invalid
     */
    public function handleCallback(User $user): SourceProvider
    {
        /** @var SocialiteUser $githubUser */
        $githubUser = Socialite::driver('github')->user();

        // Validate required fields
        if (! $githubUser->getId() || ! $githubUser->getNickname() || ! $githubUser->token) {
            throw new \Exception('Invalid GitHub user data received');
        }

        return SourceProvider::updateOrCreate(
            [
                'user_id' => $user->id,
                'provider' => 'github',
            ],
            [
                'provider_user_id' => $githubUser->getId(),
                'username' => $githubUser->getNickname(),
                'email' => $githubUser->getEmail(),
                'access_token' => $githubUser->token,
            ]
        );
    }

    /**
     * Disconnect the GitHub source provider.
     *
     * Deletes the source provider record and removes OAuth access.
     */
    public function disconnect(User $user): bool
    {
        return $user->sourceProviders()
            ->where('provider', 'github')
            ->delete() > 0;
    }
}
