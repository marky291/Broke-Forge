<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\ServerCredential;
use App\Models\ServerSite;
use App\Models\SourceProvider;
use App\Models\User;
use App\Packages\Enums\CredentialType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerSitesDeployKeyTest extends TestCase
{
    use RefreshDatabase;

    protected function createUserWithGitHub(): array
    {
        $user = User::factory()->create();

        $githubProvider = SourceProvider::create([
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_user_id' => '123456',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'access_token' => 'fake-token',
        ]);

        return [$user, $githubProvider];
    }

    protected function createServerWithCredentials(User $user): Server
    {
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create BrokeForge credential for deploy key
        ServerCredential::factory()->brokeforge()->create([
            'server_id' => $server->id,
        ]);

        // Create Root credential for site removal
        ServerCredential::factory()->root()->create([
            'server_id' => $server->id,
        ]);

        return $server;
    }

    public function test_it_requires_github_connection_to_create_site(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = $this->createServerWithCredentials($user);

        $response = $this->actingAs($user)->post("/servers/{$server->id}/sites", [
            'domain' => 'example.com',
            'php_version' => '8.3',
            'ssl' => false,
            'git_repository' => 'testuser/repo1',
            'git_branch' => 'main',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Please connect GitHub to create sites with repositories.');
    }

    public function test_it_automatically_adds_deploy_key_when_creating_site(): void
    {
        Queue::fake();

        [$user, $githubProvider] = $this->createUserWithGitHub();
        $server = $this->createServerWithCredentials($user);

        // Mock GitHub API for deploy key creation
        Http::fake([
            'https://api.github.com/repos/testuser/repo1/keys' => Http::response([
                'id' => 12345678,
                'key' => 'ssh-rsa AAAA...',
                'title' => "BrokeForge - example.com - Server #{$server->id}",
                'read_only' => true,
            ], 201),
        ]);

        $response = $this->actingAs($user)->post("/servers/{$server->id}/sites", [
            'domain' => 'example.com',
            'php_version' => '8.3',
            'ssl' => false,
            'git_repository' => 'testuser/repo1',
            'git_branch' => 'main',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify deploy key metadata is stored
        $site = ServerSite::where('domain', 'example.com')->first();
        $this->assertNotNull($site);
        $this->assertEquals(12345678, $site->configuration['git_repository']['deploy_key_id']);
        $this->assertEquals("BrokeForge - example.com - Server #{$server->id}", $site->configuration['git_repository']['deploy_key_title']);
    }

    public function test_it_stores_deploy_key_id_in_site_configuration(): void
    {
        Queue::fake();

        [$user, $githubProvider] = $this->createUserWithGitHub();
        $server = $this->createServerWithCredentials($user);

        // Mock GitHub API
        Http::fake([
            'https://api.github.com/repos/testuser/repo1/keys' => Http::response([
                'id' => 87654321,
                'key' => 'ssh-rsa AAAA...',
                'title' => "BrokeForge - test.com - Server #{$server->id}",
                'read_only' => true,
            ], 201),
        ]);

        $this->actingAs($user)->post("/servers/{$server->id}/sites", [
            'domain' => 'test.com',
            'php_version' => '8.3',
            'ssl' => false,
            'git_repository' => 'testuser/repo1',
            'git_branch' => 'develop',
        ]);

        $site = ServerSite::where('domain', 'test.com')->first();

        $this->assertArrayHasKey('git_repository', $site->configuration);
        $this->assertArrayHasKey('deploy_key_id', $site->configuration['git_repository']);
        $this->assertArrayHasKey('deploy_key_title', $site->configuration['git_repository']);
        $this->assertEquals(87654321, $site->configuration['git_repository']['deploy_key_id']);
        $this->assertEquals('testuser/repo1', $site->configuration['git_repository']['repository']);
        $this->assertEquals('develop', $site->configuration['git_repository']['branch']);
    }

    public function test_site_creation_fails_if_deploy_key_addition_fails(): void
    {
        Queue::fake();

        [$user, $githubProvider] = $this->createUserWithGitHub();
        $server = $this->createServerWithCredentials($user);

        // Mock GitHub API failure
        Http::fake([
            'https://api.github.com/repos/testuser/repo1/keys' => Http::response([
                'message' => 'Key already exists',
            ], 422),
        ]);

        $response = $this->actingAs($user)->post("/servers/{$server->id}/sites", [
            'domain' => 'example.com',
            'php_version' => '8.3',
            'ssl' => false,
            'git_repository' => 'testuser/repo1',
            'git_branch' => 'main',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Verify site was not created
        $this->assertDatabaseMissing('server_sites', [
            'domain' => 'example.com',
        ]);
    }

    public function test_it_removes_deploy_key_on_site_deletion(): void
    {
        Queue::fake();

        [$user, $githubProvider] = $this->createUserWithGitHub();
        $server = $this->createServerWithCredentials($user);

        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
            'configuration' => [
                'git_repository' => [
                    'provider' => 'github',
                    'repository' => 'testuser/repo1',
                    'branch' => 'main',
                    'deploy_key_id' => 12345678,
                    'deploy_key_title' => "BrokeForge - example.com - Server #{$server->id}",
                ],
            ],
        ]);

        // Mock GitHub API for deploy key deletion
        Http::fake([
            'https://api.github.com/repos/testuser/repo1/keys/12345678' => Http::response(null, 204),
        ]);

        $response = $this->actingAs($user)->delete("/servers/{$server->id}/sites/{$site->id}");

        $response->assertRedirect();

        // Verify GitHub API was called to remove deploy key
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.github.com/repos/testuser/repo1/keys/12345678'
                && $request->method() === 'DELETE';
        });
    }

    public function test_site_deletion_succeeds_even_if_deploy_key_removal_fails(): void
    {
        Queue::fake();

        [$user, $githubProvider] = $this->createUserWithGitHub();
        $server = $this->createServerWithCredentials($user);

        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
            'configuration' => [
                'git_repository' => [
                    'provider' => 'github',
                    'repository' => 'testuser/repo1',
                    'branch' => 'main',
                    'deploy_key_id' => 12345678,
                ],
            ],
        ]);

        // Mock GitHub API failure for deploy key deletion
        Http::fake([
            'https://api.github.com/repos/testuser/repo1/keys/12345678' => Http::response([
                'message' => 'Not Found',
            ], 404),
        ]);

        $response = $this->actingAs($user)->delete("/servers/{$server->id}/sites/{$site->id}");

        // Site deletion should still succeed
        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_site_deletion_handles_github_disconnected_gracefully(): void
    {
        Queue::fake();

        $user = User::factory()->create(); // No GitHub connection
        $server = $this->createServerWithCredentials($user);

        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
            'configuration' => [
                'git_repository' => [
                    'provider' => 'github',
                    'repository' => 'testuser/repo1',
                    'branch' => 'main',
                    'deploy_key_id' => 12345678,
                ],
            ],
        ]);

        Http::fake();

        $response = $this->actingAs($user)->delete("/servers/{$server->id}/sites/{$site->id}");

        // Site deletion should succeed even without GitHub connection
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify no GitHub API calls were made
        Http::assertNothingSent();
    }

    public function test_it_validates_repository_format(): void
    {
        Queue::fake();

        [$user, $githubProvider] = $this->createUserWithGitHub();
        $server = $this->createServerWithCredentials($user);

        Http::fake();

        // Invalid format: missing slash
        $response = $this->actingAs($user)->post("/servers/{$server->id}/sites", [
            'domain' => 'example.com',
            'php_version' => '8.3',
            'ssl' => false,
            'git_repository' => 'invalid-repo-format',
            'git_branch' => 'main',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('git_repository');

        // Verify no site was created
        $this->assertDatabaseMissing('server_sites', [
            'domain' => 'example.com',
        ]);
    }

    public function test_github_oauth_redirects_to_sites_page_with_modal_open(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Simulate OAuth flow
        $this->actingAs($user);
        session(['source_provider_server_id' => $server->id]);

        // Mock Socialite GitHub user
        $this->mock(\Laravel\Socialite\Contracts\Factory::class, function ($mock) {
            $githubUser = $this->mock(\Laravel\Socialite\Two\User::class);
            $githubUser->shouldReceive('getId')->andReturn('123456');
            $githubUser->shouldReceive('getNickname')->andReturn('testuser');
            $githubUser->shouldReceive('getEmail')->andReturn('test@example.com');
            $githubUser->token = 'fake-token';

            $mock->shouldReceive('driver')
                ->with('github')
                ->andReturnSelf();

            $mock->shouldReceive('user')
                ->andReturn($githubUser);
        });

        $response = $this->get('/source-providers/github/callback');

        $response->assertRedirect(route('servers.sites', $server));
        $response->assertSessionHas('success', 'GitHub connected successfully. You can now create sites.');
        $response->assertSessionHas('open_add_site_modal', true);
    }

    public function test_it_handles_github_api_rate_limiting_gracefully(): void
    {
        Queue::fake();

        [$user, $githubProvider] = $this->createUserWithGitHub();
        $server = $this->createServerWithCredentials($user);

        // Mock GitHub API rate limit response
        Http::fake([
            'https://api.github.com/repos/testuser/repo1/keys' => Http::response([
                'message' => 'API rate limit exceeded',
                'documentation_url' => 'https://docs.github.com/rest/overview/resources-in-the-rest-api#rate-limiting',
            ], 403),
        ]);

        $response = $this->actingAs($user)->post("/servers/{$server->id}/sites", [
            'domain' => 'example.com',
            'php_version' => '8.3',
            'ssl' => false,
            'git_repository' => 'testuser/repo1',
            'git_branch' => 'main',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Failed to add deploy key', session('error'));
    }
}
