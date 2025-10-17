<?php

namespace Tests\Unit\Packages\Services\SourceProvider\Github;

use App\Models\Server;
use App\Models\ServerSite;
use App\Models\SourceProvider;
use App\Models\User;
use App\Packages\Services\SourceProvider\Github\GitHubApiClient;
use App\Packages\Services\SourceProvider\Github\GitHubWebhookManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Mockery;
use Tests\TestCase;

class GitHubWebhookManagerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test forSite returns instance when GitHub provider exists.
     */
    public function test_for_site_returns_instance_when_github_provider_exists(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create(['server_id' => $server->id]);

        $sourceProvider = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
            'access_token' => 'test-token',
        ]);

        // Act
        $manager = GitHubWebhookManager::forSite($site);

        // Assert
        $this->assertInstanceOf(GitHubWebhookManager::class, $manager);
    }

    /**
     * Test forSite returns null when no GitHub provider exists.
     */
    public function test_for_site_returns_null_when_no_github_provider_exists(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create(['server_id' => $server->id]);

        // Act
        $manager = GitHubWebhookManager::forSite($site);

        // Assert
        $this->assertNull($manager);
    }

    /**
     * Test createWebhook returns error when no Git repository configured.
     */
    public function test_create_webhook_returns_error_when_no_git_repository_configured(): void
    {
        // Arrange
        $sourceProvider = SourceProvider::factory()->create();
        $apiClient = Mockery::mock(GitHubApiClient::class);
        $manager = new GitHubWebhookManager($sourceProvider, $apiClient);

        $site = Mockery::mock(ServerSite::class);
        $site->shouldReceive('getGitConfiguration')->andReturn(['repository' => null]);

        // Act
        $result = $manager->createWebhook($site);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertNull($result['webhook_id']);
        $this->assertStringContainsString('No Git repository', $result['error']);
    }

    /**
     * Test createWebhook returns error for invalid repository format.
     */
    public function test_create_webhook_returns_error_for_invalid_repository_format(): void
    {
        // Arrange
        $sourceProvider = SourceProvider::factory()->create();
        $apiClient = Mockery::mock(GitHubApiClient::class);
        $manager = new GitHubWebhookManager($sourceProvider, $apiClient);

        $site = Mockery::mock(ServerSite::class);
        $site->shouldReceive('getGitConfiguration')->andReturn(['repository' => 'invalid-format']);
        $site->shouldReceive('getAttribute')->with('id')->andReturn(1);

        // Act
        $result = $manager->createWebhook($site);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertNull($result['webhook_id']);
        $this->assertStringContainsString('Invalid repository format', $result['error']);
    }

    /**
     * Test createWebhook creates webhook successfully.
     */
    public function test_create_webhook_creates_webhook_successfully(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'webhook_id' => null,
            'webhook_secret' => null,
            'auto_deploy_enabled' => false,
            'configuration' => [
                'git_repository' => [
                    'repository' => 'octocat/hello-world',
                    'branch' => 'main',
                ],
            ],
        ]);

        $sourceProvider = SourceProvider::factory()->create();
        $apiClient = Mockery::mock(GitHubApiClient::class);
        $manager = new GitHubWebhookManager($sourceProvider, $apiClient);

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('successful')->andReturn(true);
        $mockResponse->shouldReceive('json')->with('id')->andReturn(12345);

        $apiClient->shouldReceive('createWebhook')
            ->once()
            ->with('octocat', 'hello-world', Mockery::type('array'), ['push'])
            ->andReturn($mockResponse);

        // Act
        $result = $manager->createWebhook($site);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('12345', $result['webhook_id']);
        $this->assertNull($result['error']);

        // Verify site was updated
        $site->refresh();
        $this->assertEquals('12345', $site->webhook_id);
        $this->assertNotNull($site->webhook_secret);
        $this->assertTrue($site->auto_deploy_enabled);
    }

    /**
     * Test createWebhook handles API failures.
     */
    public function test_create_webhook_handles_api_failures(): void
    {
        // Arrange
        $sourceProvider = SourceProvider::factory()->create();
        $apiClient = Mockery::mock(GitHubApiClient::class);
        $manager = new GitHubWebhookManager($sourceProvider, $apiClient);

        $site = Mockery::mock(ServerSite::class);
        $site->shouldReceive('getGitConfiguration')->andReturn(['repository' => 'octocat/hello-world']);
        $site->shouldReceive('getAttribute')->with('id')->andReturn(1);

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('successful')->andReturn(false);
        $mockResponse->shouldReceive('status')->andReturn(422);
        $mockResponse->shouldReceive('json')->with('message')->andReturn('Validation failed');

        $apiClient->shouldReceive('createWebhook')->andReturn($mockResponse);

        // Act
        $result = $manager->createWebhook($site);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertNull($result['webhook_id']);
        $this->assertEquals('Validation failed', $result['error']);
    }

    /**
     * Test deleteWebhook returns error when no webhook ID.
     */
    public function test_delete_webhook_returns_error_when_no_webhook_id(): void
    {
        // Arrange
        $sourceProvider = SourceProvider::factory()->create();
        $apiClient = Mockery::mock(GitHubApiClient::class);
        $manager = new GitHubWebhookManager($sourceProvider, $apiClient);

        $site = Mockery::mock(ServerSite::class);
        $site->shouldReceive('getAttribute')->with('webhook_id')->andReturn(null);

        // Act
        $result = $manager->deleteWebhook($site);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No webhook configured', $result['error']);
    }

    /**
     * Test deleteWebhook returns error when no Git repository configured.
     */
    public function test_delete_webhook_returns_error_when_no_git_repository_configured(): void
    {
        // Arrange
        $sourceProvider = SourceProvider::factory()->create();
        $apiClient = Mockery::mock(GitHubApiClient::class);
        $manager = new GitHubWebhookManager($sourceProvider, $apiClient);

        $site = Mockery::mock(ServerSite::class);
        $site->shouldReceive('getAttribute')->with('webhook_id')->andReturn('12345');
        $site->shouldReceive('getGitConfiguration')->andReturn(['repository' => null]);

        // Act
        $result = $manager->deleteWebhook($site);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No Git repository', $result['error']);
    }

    /**
     * Test deleteWebhook deletes webhook successfully.
     */
    public function test_delete_webhook_deletes_webhook_successfully(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'webhook_id' => '12345',
            'webhook_secret' => 'secret123',
            'auto_deploy_enabled' => true,
            'configuration' => [
                'git_repository' => [
                    'repository' => 'octocat/hello-world',
                ],
            ],
        ]);

        $sourceProvider = SourceProvider::factory()->create();
        $apiClient = Mockery::mock(GitHubApiClient::class);
        $manager = new GitHubWebhookManager($sourceProvider, $apiClient);

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('successful')->andReturn(true);
        $mockResponse->shouldReceive('status')->andReturn(204);

        $apiClient->shouldReceive('deleteWebhook')
            ->once()
            ->with('octocat', 'hello-world', '12345')
            ->andReturn($mockResponse);

        // Act
        $result = $manager->deleteWebhook($site);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertNull($result['error']);

        // Verify site was updated
        $site->refresh();
        $this->assertNull($site->webhook_id);
        $this->assertNull($site->webhook_secret);
        $this->assertFalse($site->auto_deploy_enabled);
    }

    /**
     * Test deleteWebhook treats 404 as success.
     */
    public function test_delete_webhook_treats_404_as_success(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'webhook_id' => '12345',
            'webhook_secret' => 'secret123',
            'auto_deploy_enabled' => true,
            'configuration' => [
                'git_repository' => [
                    'repository' => 'octocat/hello-world',
                ],
            ],
        ]);

        $sourceProvider = SourceProvider::factory()->create();
        $apiClient = Mockery::mock(GitHubApiClient::class);
        $manager = new GitHubWebhookManager($sourceProvider, $apiClient);

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('successful')->andReturn(false);
        $mockResponse->shouldReceive('status')->andReturn(404);

        $apiClient->shouldReceive('deleteWebhook')
            ->once()
            ->with('octocat', 'hello-world', '12345')
            ->andReturn($mockResponse);

        // Act
        $result = $manager->deleteWebhook($site);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertNull($result['error']);

        // Verify site was updated
        $site->refresh();
        $this->assertNull($site->webhook_id);
    }

    /**
     * Test deleteWebhook handles API failures.
     */
    public function test_delete_webhook_handles_api_failures(): void
    {
        // Arrange
        $sourceProvider = SourceProvider::factory()->create();
        $apiClient = Mockery::mock(GitHubApiClient::class);
        $manager = new GitHubWebhookManager($sourceProvider, $apiClient);

        $site = Mockery::mock(ServerSite::class);
        $site->shouldReceive('getAttribute')->with('webhook_id')->andReturn('12345');
        $site->shouldReceive('getGitConfiguration')->andReturn(['repository' => 'octocat/hello-world']);
        $site->shouldReceive('getAttribute')->with('id')->andReturn(1);

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('successful')->andReturn(false);
        $mockResponse->shouldReceive('status')->andReturn(500);
        $mockResponse->shouldReceive('json')->with('message')->andReturn('Internal Server Error');

        $apiClient->shouldReceive('deleteWebhook')->andReturn($mockResponse);

        // Act
        $result = $manager->deleteWebhook($site);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('Internal Server Error', $result['error']);
    }

    /**
     * Test parseRepository rejects invalid repository formats.
     */
    public function test_parse_repository_rejects_invalid_repository_formats(): void
    {
        // Arrange
        $sourceProvider = SourceProvider::factory()->create();
        $apiClient = Mockery::mock(GitHubApiClient::class);
        $manager = new GitHubWebhookManager($sourceProvider, $apiClient);

        // Test invalid format (no slash)
        $site1 = Mockery::mock(ServerSite::class);
        $site1->shouldReceive('getGitConfiguration')->andReturn(['repository' => 'invalid']);
        $site1->shouldReceive('getAttribute')->with('id')->andReturn(1);

        // Test invalid format (too many parts)
        $site2 = Mockery::mock(ServerSite::class);
        $site2->shouldReceive('getGitConfiguration')->andReturn(['repository' => 'owner/repo/extra']);
        $site2->shouldReceive('getAttribute')->with('id')->andReturn(2);

        // Test only slash
        $site3 = Mockery::mock(ServerSite::class);
        $site3->shouldReceive('getGitConfiguration')->andReturn(['repository' => '/']);
        $site3->shouldReceive('getAttribute')->with('id')->andReturn(3);

        // Act
        $result1 = $manager->createWebhook($site1);
        $result2 = $manager->createWebhook($site2);
        $result3 = $manager->createWebhook($site3);

        // Assert
        $this->assertFalse($result1['success']);
        $this->assertStringContainsString('Invalid repository format', $result1['error']);

        $this->assertFalse($result2['success']);
        $this->assertStringContainsString('Invalid repository format', $result2['error']);

        $this->assertFalse($result3['success']);
        $this->assertStringContainsString('Invalid repository format', $result3['error']);
    }
}
