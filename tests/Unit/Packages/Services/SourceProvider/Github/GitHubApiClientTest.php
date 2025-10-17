<?php

namespace Tests\Unit\Packages\Services\SourceProvider\Github;

use App\Models\SourceProvider;
use App\Packages\Services\SourceProvider\Github\GitHubApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubApiClientTest extends TestCase
{
    /**
     * Test getUser returns user information.
     */
    public function test_get_user_returns_user_information(): void
    {
        // Arrange
        $sourceProvider = new SourceProvider(['access_token' => 'test-token']);
        $client = new GitHubApiClient($sourceProvider);

        Http::fake([
            'api.github.com/user' => Http::response([
                'login' => 'octocat',
                'id' => 1,
                'name' => 'Octo Cat',
                'email' => 'octocat@github.com',
            ], 200),
        ]);

        // Act
        $response = $client->getUser();

        // Assert
        $this->assertTrue($response->successful());
        $this->assertEquals('octocat', $response->json('login'));
        $this->assertEquals('Octo Cat', $response->json('name'));
    }

    /**
     * Test getRepositories returns user repositories.
     */
    public function test_get_repositories_returns_user_repositories(): void
    {
        // Arrange
        $sourceProvider = new SourceProvider(['access_token' => 'test-token']);
        $client = new GitHubApiClient($sourceProvider);

        Http::fake([
            'api.github.com/user/repos*' => Http::response([
                [
                    'id' => 1,
                    'name' => 'repo1',
                    'full_name' => 'octocat/repo1',
                ],
                [
                    'id' => 2,
                    'name' => 'repo2',
                    'full_name' => 'octocat/repo2',
                ],
            ], 200),
        ]);

        // Act
        $response = $client->getRepositories();

        // Assert
        $this->assertTrue($response->successful());
        $this->assertCount(2, $response->json());
        $this->assertEquals('repo1', $response->json()[0]['name']);
        $this->assertEquals('repo2', $response->json()[1]['name']);
    }

    /**
     * Test getRepositories passes correct query parameters.
     */
    public function test_get_repositories_passes_correct_query_parameters(): void
    {
        // Arrange
        $sourceProvider = new SourceProvider(['access_token' => 'test-token']);
        $client = new GitHubApiClient($sourceProvider);

        Http::fake();

        // Act
        $client->getRepositories(50);

        // Assert
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.github.com/user/repos?per_page=50&sort=updated&affiliation=owner%2Ccollaborator';
        });
    }

    /**
     * Test getRepository returns specific repository.
     */
    public function test_get_repository_returns_specific_repository(): void
    {
        // Arrange
        $sourceProvider = new SourceProvider(['access_token' => 'test-token']);
        $client = new GitHubApiClient($sourceProvider);

        Http::fake([
            'api.github.com/repos/octocat/hello-world' => Http::response([
                'id' => 123,
                'name' => 'hello-world',
                'full_name' => 'octocat/hello-world',
                'default_branch' => 'main',
            ], 200),
        ]);

        // Act
        $response = $client->getRepository('octocat', 'hello-world');

        // Assert
        $this->assertTrue($response->successful());
        $this->assertEquals('hello-world', $response->json('name'));
        $this->assertEquals('main', $response->json('default_branch'));
    }

    /**
     * Test getBranches returns repository branches.
     */
    public function test_get_branches_returns_repository_branches(): void
    {
        // Arrange
        $sourceProvider = new SourceProvider(['access_token' => 'test-token']);
        $client = new GitHubApiClient($sourceProvider);

        Http::fake([
            'api.github.com/repos/octocat/hello-world/branches' => Http::response([
                ['name' => 'main', 'protected' => true],
                ['name' => 'develop', 'protected' => false],
                ['name' => 'feature-1', 'protected' => false],
            ], 200),
        ]);

        // Act
        $response = $client->getBranches('octocat', 'hello-world');

        // Assert
        $this->assertTrue($response->successful());
        $this->assertCount(3, $response->json());
        $this->assertEquals('main', $response->json()[0]['name']);
    }

    /**
     * Test createWebhook creates webhook with default events.
     */
    public function test_create_webhook_creates_webhook_with_default_events(): void
    {
        // Arrange
        $sourceProvider = new SourceProvider(['access_token' => 'test-token']);
        $client = new GitHubApiClient($sourceProvider);

        Http::fake([
            'api.github.com/repos/octocat/hello-world/hooks' => Http::response([
                'id' => 12345,
                'url' => 'https://example.com/webhook',
                'active' => true,
                'events' => ['push'],
            ], 201),
        ]);

        // Act
        $response = $client->createWebhook('octocat', 'hello-world', [
            'url' => 'https://example.com/webhook',
            'content_type' => 'json',
            'secret' => 'secret123',
        ]);

        // Assert
        $this->assertTrue($response->successful());
        $this->assertEquals(12345, $response->json('id'));
        $this->assertTrue($response->json('active'));
    }

    /**
     * Test createWebhook creates webhook with custom events.
     */
    public function test_create_webhook_creates_webhook_with_custom_events(): void
    {
        // Arrange
        $sourceProvider = new SourceProvider(['access_token' => 'test-token']);
        $client = new GitHubApiClient($sourceProvider);

        Http::fake();

        // Act
        $client->createWebhook('octocat', 'hello-world', [
            'url' => 'https://example.com/webhook',
            'content_type' => 'json',
            'secret' => 'secret123',
        ], ['push', 'pull_request']);

        // Assert
        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://api.github.com/repos/octocat/hello-world/hooks' &&
                   $data['events'] === ['push', 'pull_request'];
        });
    }

    /**
     * Test deleteWebhook deletes webhook successfully.
     */
    public function test_delete_webhook_deletes_webhook_successfully(): void
    {
        // Arrange
        $sourceProvider = new SourceProvider(['access_token' => 'test-token']);
        $client = new GitHubApiClient($sourceProvider);

        Http::fake([
            'api.github.com/repos/octocat/hello-world/hooks/12345' => Http::response(null, 204),
        ]);

        // Act
        $response = $client->deleteWebhook('octocat', 'hello-world', '12345');

        // Assert
        $this->assertTrue($response->successful());
        $this->assertEquals(204, $response->status());
    }

    /**
     * Test getWebhook returns webhook details.
     */
    public function test_get_webhook_returns_webhook_details(): void
    {
        // Arrange
        $sourceProvider = new SourceProvider(['access_token' => 'test-token']);
        $client = new GitHubApiClient($sourceProvider);

        Http::fake([
            'api.github.com/repos/octocat/hello-world/hooks/12345' => Http::response([
                'id' => 12345,
                'url' => 'https://example.com/webhook',
                'active' => true,
                'events' => ['push'],
            ], 200),
        ]);

        // Act
        $response = $client->getWebhook('octocat', 'hello-world', '12345');

        // Assert
        $this->assertTrue($response->successful());
        $this->assertEquals(12345, $response->json('id'));
        $this->assertEquals(['push'], $response->json('events'));
    }

    /**
     * Test updateWebhook updates webhook config.
     */
    public function test_update_webhook_updates_webhook_config(): void
    {
        // Arrange
        $sourceProvider = new SourceProvider(['access_token' => 'test-token']);
        $client = new GitHubApiClient($sourceProvider);

        Http::fake([
            'api.github.com/repos/octocat/hello-world/hooks/12345' => Http::response([
                'id' => 12345,
                'config' => [
                    'url' => 'https://new-url.com/webhook',
                    'content_type' => 'json',
                ],
                'active' => true,
            ], 200),
        ]);

        // Act
        $response = $client->updateWebhook('octocat', 'hello-world', '12345', [
            'url' => 'https://new-url.com/webhook',
            'content_type' => 'json',
        ]);

        // Assert
        $this->assertTrue($response->successful());
        $this->assertEquals('https://new-url.com/webhook', $response->json('config.url'));
    }

    /**
     * Test updateWebhook updates webhook with events.
     */
    public function test_update_webhook_updates_webhook_with_events(): void
    {
        // Arrange
        $sourceProvider = new SourceProvider(['access_token' => 'test-token']);
        $client = new GitHubApiClient($sourceProvider);

        Http::fake();

        // Act
        $client->updateWebhook('octocat', 'hello-world', '12345', [
            'url' => 'https://example.com/webhook',
        ], ['push', 'pull_request']);

        // Assert
        Http::assertSent(function ($request) {
            $data = $request->data();

            return $data['events'] === ['push', 'pull_request'];
        });
    }

    /**
     * Test getLatestCommit returns commit with default branch.
     */
    public function test_get_latest_commit_returns_commit_with_default_branch(): void
    {
        // Arrange
        $sourceProvider = new SourceProvider(['access_token' => 'test-token']);
        $client = new GitHubApiClient($sourceProvider);

        Http::fake([
            'api.github.com/repos/octocat/hello-world/commits/main' => Http::response([
                'sha' => 'abc123',
                'commit' => [
                    'message' => 'Fix bug',
                    'author' => ['name' => 'Octo Cat'],
                ],
            ], 200),
        ]);

        // Act
        $response = $client->getLatestCommit('octocat', 'hello-world');

        // Assert
        $this->assertTrue($response->successful());
        $this->assertEquals('abc123', $response->json('sha'));
        $this->assertEquals('Fix bug', $response->json('commit.message'));
    }

    /**
     * Test getLatestCommit returns commit with custom branch.
     */
    public function test_get_latest_commit_returns_commit_with_custom_branch(): void
    {
        // Arrange
        $sourceProvider = new SourceProvider(['access_token' => 'test-token']);
        $client = new GitHubApiClient($sourceProvider);

        Http::fake([
            'api.github.com/repos/octocat/hello-world/commits/develop' => Http::response([
                'sha' => 'def456',
                'commit' => ['message' => 'Add feature'],
            ], 200),
        ]);

        // Act
        $response = $client->getLatestCommit('octocat', 'hello-world', 'develop');

        // Assert
        $this->assertTrue($response->successful());
        $this->assertEquals('def456', $response->json('sha'));
    }

    /**
     * Test addDeployKey adds deploy key with default read-only.
     */
    public function test_add_deploy_key_adds_deploy_key_with_default_read_only(): void
    {
        // Arrange
        $sourceProvider = new SourceProvider(['access_token' => 'test-token']);
        $client = new GitHubApiClient($sourceProvider);

        Http::fake([
            'api.github.com/repos/octocat/hello-world/keys' => Http::response([
                'id' => 1,
                'key' => 'ssh-rsa AAAA...',
                'title' => 'Deploy Key',
                'read_only' => true,
            ], 201),
        ]);

        // Act
        $response = $client->addDeployKey('octocat', 'hello-world', 'Deploy Key', 'ssh-rsa AAAA...');

        // Assert
        $this->assertTrue($response->successful());
        $this->assertEquals(1, $response->json('id'));
        $this->assertTrue($response->json('read_only'));
    }

    /**
     * Test addDeployKey adds deploy key with write access.
     */
    public function test_add_deploy_key_adds_deploy_key_with_write_access(): void
    {
        // Arrange
        $sourceProvider = new SourceProvider(['access_token' => 'test-token']);
        $client = new GitHubApiClient($sourceProvider);

        Http::fake();

        // Act
        $client->addDeployKey('octocat', 'hello-world', 'Deploy Key', 'ssh-rsa AAAA...', false);

        // Assert
        Http::assertSent(function ($request) {
            $data = $request->data();

            return $data['read_only'] === false;
        });
    }

    /**
     * Test removeDeployKey removes deploy key.
     */
    public function test_remove_deploy_key_removes_deploy_key(): void
    {
        // Arrange
        $sourceProvider = new SourceProvider(['access_token' => 'test-token']);
        $client = new GitHubApiClient($sourceProvider);

        Http::fake([
            'api.github.com/repos/octocat/hello-world/keys/123' => Http::response(null, 204),
        ]);

        // Act
        $response = $client->removeDeployKey('octocat', 'hello-world', 123);

        // Assert
        $this->assertTrue($response->successful());
        $this->assertEquals(204, $response->status());
    }

    /**
     * Test getDeployKeys returns all deploy keys.
     */
    public function test_get_deploy_keys_returns_all_deploy_keys(): void
    {
        // Arrange
        $sourceProvider = new SourceProvider(['access_token' => 'test-token']);
        $client = new GitHubApiClient($sourceProvider);

        Http::fake([
            'api.github.com/repos/octocat/hello-world/keys' => Http::response([
                ['id' => 1, 'key' => 'ssh-rsa AAAA1...', 'title' => 'Key 1', 'read_only' => true],
                ['id' => 2, 'key' => 'ssh-rsa AAAA2...', 'title' => 'Key 2', 'read_only' => false],
            ], 200),
        ]);

        // Act
        $response = $client->getDeployKeys('octocat', 'hello-world');

        // Assert
        $this->assertTrue($response->successful());
        $this->assertCount(2, $response->json());
        $this->assertEquals('Key 1', $response->json()[0]['title']);
    }

    /**
     * Test getUserSshKeys returns user SSH keys.
     */
    public function test_get_user_ssh_keys_returns_user_ssh_keys(): void
    {
        // Arrange
        $sourceProvider = new SourceProvider(['access_token' => 'test-token']);
        $client = new GitHubApiClient($sourceProvider);

        Http::fake([
            'api.github.com/user/keys' => Http::response([
                ['id' => 1, 'key' => 'ssh-rsa AAAA1...', 'title' => 'Work Laptop'],
                ['id' => 2, 'key' => 'ssh-rsa AAAA2...', 'title' => 'Home Desktop'],
            ], 200),
        ]);

        // Act
        $response = $client->getUserSshKeys();

        // Assert
        $this->assertTrue($response->successful());
        $this->assertCount(2, $response->json());
        $this->assertEquals('Work Laptop', $response->json()[0]['title']);
    }

    /**
     * Test addUserSshKey adds SSH key to user account.
     */
    public function test_add_user_ssh_key_adds_ssh_key_to_user_account(): void
    {
        // Arrange
        $sourceProvider = new SourceProvider(['access_token' => 'test-token']);
        $client = new GitHubApiClient($sourceProvider);

        Http::fake([
            'api.github.com/user/keys' => Http::response([
                'id' => 123,
                'key' => 'ssh-rsa AAAA...',
                'title' => 'My Server',
            ], 201),
        ]);

        // Act
        $response = $client->addUserSshKey('My Server', 'ssh-rsa AAAA...');

        // Assert
        $this->assertTrue($response->successful());
        $this->assertEquals(123, $response->json('id'));
        $this->assertEquals('My Server', $response->json('title'));
    }

    /**
     * Test removeUserSshKey removes SSH key from user account.
     */
    public function test_remove_user_ssh_key_removes_ssh_key_from_user_account(): void
    {
        // Arrange
        $sourceProvider = new SourceProvider(['access_token' => 'test-token']);
        $client = new GitHubApiClient($sourceProvider);

        Http::fake([
            'api.github.com/user/keys/123' => Http::response(null, 204),
        ]);

        // Act
        $response = $client->removeUserSshKey(123);

        // Assert
        $this->assertTrue($response->successful());
        $this->assertEquals(204, $response->status());
    }

    /**
     * Test client uses correct base URL.
     */
    public function test_client_uses_correct_base_url(): void
    {
        // Arrange
        $sourceProvider = new SourceProvider(['access_token' => 'test-token']);
        $client = new GitHubApiClient($sourceProvider);

        Http::fake();

        // Act
        $client->getUser();

        // Assert
        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://api.github.com');
        });
    }

    /**
     * Test client uses bearer token authentication.
     */
    public function test_client_uses_bearer_token_authentication(): void
    {
        // Arrange
        $sourceProvider = new SourceProvider(['access_token' => 'my-secret-token']);
        $client = new GitHubApiClient($sourceProvider);

        Http::fake();

        // Act
        $client->getUser();

        // Assert
        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer my-secret-token');
        });
    }

    /**
     * Test client accepts JSON responses.
     */
    public function test_client_accepts_json_responses(): void
    {
        // Arrange
        $sourceProvider = new SourceProvider(['access_token' => 'test-token']);
        $client = new GitHubApiClient($sourceProvider);

        Http::fake();

        // Act
        $client->getUser();

        // Assert
        Http::assertSent(function ($request) {
            return $request->hasHeader('Accept', 'application/json');
        });
    }
}
