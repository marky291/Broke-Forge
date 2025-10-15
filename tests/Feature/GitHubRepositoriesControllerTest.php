<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\SourceProvider;
use App\Models\User;
use App\Packages\Services\SourceProvider\Github\GitHubApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubRepositoriesControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_authentication(): void
    {
        $server = Server::factory()->create();

        $response = $this->getJson("/servers/{$server->id}/github/repositories");

        $response->assertUnauthorized();
    }

    public function test_it_prevents_unauthorized_access_to_other_users_servers(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)->getJson("/servers/{$server->id}/github/repositories");

        $response->assertForbidden();
    }

    public function test_it_returns_empty_when_github_not_connected(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson("/servers/{$server->id}/github/repositories");

        $response->assertOk()
            ->assertJson([
                'repositories' => [],
                'connected' => false,
            ]);
    }

    public function test_it_fetches_repositories_when_github_connected(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create GitHub source provider
        SourceProvider::create([
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_user_id' => '123456',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'access_token' => 'fake-token',
        ]);

        // Mock GitHub API responses
        Http::fake([
            'https://api.github.com/user/repos*' => Http::response([
                [
                    'name' => 'repo1',
                    'owner' => ['login' => 'testuser'],
                ],
                [
                    'name' => 'repo2',
                    'owner' => ['login' => 'testuser'],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->getJson("/servers/{$server->id}/github/repositories");

        $response->assertOk()
            ->assertJson([
                'repositories' => [
                    'testuser/repo1',
                    'testuser/repo2',
                ],
                'connected' => true,
            ]);
    }

    public function test_it_handles_github_api_failure_gracefully(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create GitHub source provider
        SourceProvider::create([
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_user_id' => '123456',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'access_token' => 'fake-token',
        ]);

        // Mock GitHub API failure
        Http::fake([
            'https://api.github.com/user/repos*' => Http::response([], 500),
        ]);

        $response = $this->actingAs($user)->getJson("/servers/{$server->id}/github/repositories");

        $response->assertStatus(500)
            ->assertJson([
                'repositories' => [],
                'connected' => true,
                'error' => 'Failed to fetch repositories from GitHub',
            ]);
    }


    public function test_it_handles_malformed_repository_data(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create GitHub source provider
        SourceProvider::create([
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_user_id' => '123456',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'access_token' => 'fake-token',
        ]);

        // Mock GitHub API responses with malformed data
        Http::fake([
            'https://api.github.com/user/repos*' => Http::response([
                [
                    'name' => 'repo1',
                    'owner' => ['login' => 'testuser'],
                ],
                [
                    // Missing name
                    'owner' => ['login' => 'testuser'],
                ],
                [
                    'name' => 'repo3',
                    // Missing owner
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->getJson("/servers/{$server->id}/github/repositories");

        $response->assertOk()
            ->assertJson([
                'repositories' => ['testuser/repo1'],
                'connected' => true,
            ])
            ->assertJsonCount(1, 'repositories');
    }

    // Tests for branches endpoint

    public function test_it_fetches_branches_for_specific_repository(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create GitHub source provider
        SourceProvider::create([
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_user_id' => '123456',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'access_token' => 'fake-token',
        ]);

        // Mock GitHub API response for branches
        Http::fake([
            'https://api.github.com/repos/testuser/repo1/branches' => Http::response([
                ['name' => 'main'],
                ['name' => 'develop'],
                ['name' => 'feature-x'],
            ], 200),
        ]);

        $response = $this->actingAs($user)->getJson("/servers/{$server->id}/github/repositories/testuser/repo1/branches");

        $response->assertOk()
            ->assertJson([
                'branches' => ['main', 'develop', 'feature-x'],
            ]);
    }

    public function test_branches_endpoint_requires_authentication(): void
    {
        $server = Server::factory()->create();

        $response = $this->getJson("/servers/{$server->id}/github/repositories/testuser/repo1/branches");

        $response->assertUnauthorized();
    }

    public function test_branches_endpoint_prevents_unauthorized_access(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)->getJson("/servers/{$server->id}/github/repositories/testuser/repo1/branches");

        $response->assertForbidden();
    }

    public function test_branches_endpoint_returns_error_when_github_not_connected(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson("/servers/{$server->id}/github/repositories/testuser/repo1/branches");

        $response->assertStatus(400)
            ->assertJson([
                'branches' => [],
                'error' => 'GitHub not connected',
            ]);
    }

    public function test_branches_endpoint_handles_api_failure_gracefully(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create GitHub source provider
        SourceProvider::create([
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_user_id' => '123456',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'access_token' => 'fake-token',
        ]);

        // Mock GitHub API failure
        Http::fake([
            'https://api.github.com/repos/testuser/repo1/branches' => Http::response([], 500),
        ]);

        $response = $this->actingAs($user)->getJson("/servers/{$server->id}/github/repositories/testuser/repo1/branches");

        $response->assertStatus(500)
            ->assertJson([
                'branches' => [],
                'error' => 'Failed to fetch branches from GitHub',
            ]);
    }
}
