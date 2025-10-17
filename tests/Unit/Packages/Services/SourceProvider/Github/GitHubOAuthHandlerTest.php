<?php

namespace Tests\Unit\Packages\Services\SourceProvider\Github;

use App\Models\SourceProvider;
use App\Models\User;
use App\Packages\Services\SourceProvider\Github\GitHubOAuthHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GitHubOAuthHandlerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test redirect returns redirect response with correct scopes.
     */
    public function test_redirect_returns_redirect_response_with_correct_scopes(): void
    {
        // Arrange
        $handler = new GitHubOAuthHandler;

        $mockDriver = Mockery::mock(Provider::class);
        $mockDriver->shouldReceive('scopes')
            ->once()
            ->with(['repo', 'admin:repo_hook', 'write:public_key'])
            ->andReturnSelf();
        $mockDriver->shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://github.com/oauth'));

        Socialite::shouldReceive('driver')
            ->once()
            ->with('github')
            ->andReturn($mockDriver);

        // Act
        $response = $handler->redirect();

        // Assert
        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
    }

    /**
     * Test handleCallback creates new source provider with valid data.
     */
    public function test_handle_callback_creates_new_source_provider_with_valid_data(): void
    {
        // Arrange
        $handler = new GitHubOAuthHandler;
        $user = User::factory()->create();

        $mockGithubUser = Mockery::mock(SocialiteUser::class);
        $mockGithubUser->shouldReceive('getId')->andReturn('12345');
        $mockGithubUser->shouldReceive('getNickname')->andReturn('octocat');
        $mockGithubUser->shouldReceive('getEmail')->andReturn('octocat@github.com');
        $mockGithubUser->token = 'github-access-token-123';

        $mockDriver = Mockery::mock(Provider::class);
        $mockDriver->shouldReceive('user')->andReturn($mockGithubUser);

        Socialite::shouldReceive('driver')
            ->with('github')
            ->andReturn($mockDriver);

        // Act
        $sourceProvider = $handler->handleCallback($user);

        // Assert
        $this->assertInstanceOf(SourceProvider::class, $sourceProvider);
        $this->assertEquals($user->id, $sourceProvider->user_id);
        $this->assertEquals('github', $sourceProvider->provider);
        $this->assertEquals('12345', $sourceProvider->provider_user_id);
        $this->assertEquals('octocat', $sourceProvider->username);
        $this->assertEquals('octocat@github.com', $sourceProvider->email);
        $this->assertEquals('github-access-token-123', $sourceProvider->access_token);
    }

    /**
     * Test handleCallback updates existing source provider.
     */
    public function test_handle_callback_updates_existing_source_provider(): void
    {
        // Arrange
        $handler = new GitHubOAuthHandler;
        $user = User::factory()->create();

        $existingProvider = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_user_id' => '12345',
            'username' => 'oldusername',
            'access_token' => 'old-token',
        ]);

        $mockGithubUser = Mockery::mock(SocialiteUser::class);
        $mockGithubUser->shouldReceive('getId')->andReturn('12345');
        $mockGithubUser->shouldReceive('getNickname')->andReturn('newusername');
        $mockGithubUser->shouldReceive('getEmail')->andReturn('new@github.com');
        $mockGithubUser->token = 'new-access-token';

        $mockDriver = Mockery::mock(Provider::class);
        $mockDriver->shouldReceive('user')->andReturn($mockGithubUser);

        Socialite::shouldReceive('driver')
            ->with('github')
            ->andReturn($mockDriver);

        // Act
        $sourceProvider = $handler->handleCallback($user);

        // Assert
        $this->assertEquals($existingProvider->id, $sourceProvider->id);
        $this->assertEquals('newusername', $sourceProvider->username);
        $this->assertEquals('new@github.com', $sourceProvider->email);
        $this->assertEquals('new-access-token', $sourceProvider->access_token);

        // Verify only one provider exists
        $this->assertEquals(1, SourceProvider::where('user_id', $user->id)->count());
    }

    /**
     * Test handleCallback throws exception when GitHub user ID is missing.
     */
    public function test_handle_callback_throws_exception_when_github_user_id_missing(): void
    {
        // Arrange
        $handler = new GitHubOAuthHandler;
        $user = User::factory()->create();

        $mockGithubUser = Mockery::mock(SocialiteUser::class);
        $mockGithubUser->shouldReceive('getId')->andReturn(null);
        $mockGithubUser->shouldReceive('getNickname')->andReturn('octocat');
        $mockGithubUser->token = 'token';

        $mockDriver = Mockery::mock(Provider::class);
        $mockDriver->shouldReceive('user')->andReturn($mockGithubUser);

        Socialite::shouldReceive('driver')
            ->with('github')
            ->andReturn($mockDriver);

        // Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid GitHub user data received');

        // Act
        $handler->handleCallback($user);
    }

    /**
     * Test handleCallback throws exception when GitHub nickname is missing.
     */
    public function test_handle_callback_throws_exception_when_github_nickname_missing(): void
    {
        // Arrange
        $handler = new GitHubOAuthHandler;
        $user = User::factory()->create();

        $mockGithubUser = Mockery::mock(SocialiteUser::class);
        $mockGithubUser->shouldReceive('getId')->andReturn('12345');
        $mockGithubUser->shouldReceive('getNickname')->andReturn(null);
        $mockGithubUser->token = 'token';

        $mockDriver = Mockery::mock(Provider::class);
        $mockDriver->shouldReceive('user')->andReturn($mockGithubUser);

        Socialite::shouldReceive('driver')
            ->with('github')
            ->andReturn($mockDriver);

        // Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid GitHub user data received');

        // Act
        $handler->handleCallback($user);
    }

    /**
     * Test handleCallback throws exception when GitHub token is missing.
     */
    public function test_handle_callback_throws_exception_when_github_token_missing(): void
    {
        // Arrange
        $handler = new GitHubOAuthHandler;
        $user = User::factory()->create();

        $mockGithubUser = Mockery::mock(SocialiteUser::class);
        $mockGithubUser->shouldReceive('getId')->andReturn('12345');
        $mockGithubUser->shouldReceive('getNickname')->andReturn('octocat');
        $mockGithubUser->token = null;

        $mockDriver = Mockery::mock(Provider::class);
        $mockDriver->shouldReceive('user')->andReturn($mockGithubUser);

        Socialite::shouldReceive('driver')
            ->with('github')
            ->andReturn($mockDriver);

        // Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid GitHub user data received');

        // Act
        $handler->handleCallback($user);
    }

    /**
     * Test handleCallback stores email correctly.
     */
    public function test_handle_callback_stores_email_correctly(): void
    {
        // Arrange
        $handler = new GitHubOAuthHandler;
        $user = User::factory()->create();

        $mockGithubUser = Mockery::mock(SocialiteUser::class);
        $mockGithubUser->shouldReceive('getId')->andReturn('12345');
        $mockGithubUser->shouldReceive('getNickname')->andReturn('octocat');
        $mockGithubUser->shouldReceive('getEmail')->andReturn('test@example.com');
        $mockGithubUser->token = 'token';

        $mockDriver = Mockery::mock(Provider::class);
        $mockDriver->shouldReceive('user')->andReturn($mockGithubUser);

        Socialite::shouldReceive('driver')
            ->with('github')
            ->andReturn($mockDriver);

        // Act
        $sourceProvider = $handler->handleCallback($user);

        // Assert
        $this->assertEquals('test@example.com', $sourceProvider->email);
    }

    /**
     * Test handleCallback handles null email gracefully.
     */
    public function test_handle_callback_handles_null_email_gracefully(): void
    {
        // Arrange
        $handler = new GitHubOAuthHandler;
        $user = User::factory()->create();

        $mockGithubUser = Mockery::mock(SocialiteUser::class);
        $mockGithubUser->shouldReceive('getId')->andReturn('12345');
        $mockGithubUser->shouldReceive('getNickname')->andReturn('octocat');
        $mockGithubUser->shouldReceive('getEmail')->andReturn(null);
        $mockGithubUser->token = 'token';

        $mockDriver = Mockery::mock(Provider::class);
        $mockDriver->shouldReceive('user')->andReturn($mockGithubUser);

        Socialite::shouldReceive('driver')
            ->with('github')
            ->andReturn($mockDriver);

        // Act
        $sourceProvider = $handler->handleCallback($user);

        // Assert
        $this->assertNull($sourceProvider->email);
        $this->assertInstanceOf(SourceProvider::class, $sourceProvider);
    }

    /**
     * Test disconnect returns true when provider exists.
     */
    public function test_disconnect_returns_true_when_provider_exists(): void
    {
        // Arrange
        $handler = new GitHubOAuthHandler;
        $user = User::factory()->create();

        SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
        ]);

        // Act
        $result = $handler->disconnect($user);

        // Assert
        $this->assertTrue($result);
        $this->assertEquals(0, SourceProvider::where('user_id', $user->id)->where('provider', 'github')->count());
    }

    /**
     * Test disconnect returns false when no provider exists.
     */
    public function test_disconnect_returns_false_when_no_provider_exists(): void
    {
        // Arrange
        $handler = new GitHubOAuthHandler;
        $user = User::factory()->create();

        // Act
        $result = $handler->disconnect($user);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test disconnect only deletes github providers.
     */
    public function test_disconnect_only_deletes_github_providers(): void
    {
        // Arrange
        $handler = new GitHubOAuthHandler;
        $user = User::factory()->create();

        $githubProvider = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
        ]);

        $gitlabProvider = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'gitlab',
        ]);

        // Act
        $result = $handler->disconnect($user);

        // Assert
        $this->assertTrue($result);
        $this->assertEquals(0, SourceProvider::where('id', $githubProvider->id)->count());
        $this->assertEquals(1, SourceProvider::where('id', $gitlabProvider->id)->count());
    }

    /**
     * Test disconnect only deletes providers for the specific user.
     */
    public function test_disconnect_only_deletes_providers_for_specific_user(): void
    {
        // Arrange
        $handler = new GitHubOAuthHandler;
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $user1Provider = SourceProvider::factory()->create([
            'user_id' => $user1->id,
            'provider' => 'github',
        ]);

        $user2Provider = SourceProvider::factory()->create([
            'user_id' => $user2->id,
            'provider' => 'github',
        ]);

        // Act
        $result = $handler->disconnect($user1);

        // Assert
        $this->assertTrue($result);
        $this->assertEquals(0, SourceProvider::where('id', $user1Provider->id)->count());
        $this->assertEquals(1, SourceProvider::where('id', $user2Provider->id)->count());
    }
}
