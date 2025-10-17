<?php

namespace Tests\Unit\Packages\Services\SourceProvider;

use App\Models\Server;
use App\Models\ServerCredential;
use App\Models\SourceProvider;
use App\Packages\Services\SourceProvider\ServerSshKeyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ServerSshKeyManagerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test addServerKeyToGitHub returns false when server has no credentials.
     */
    public function test_add_server_key_to_github_returns_false_when_no_credentials(): void
    {
        // Arrange
        $user = \App\Models\User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // No credentials created for this server

        $sourceProvider = SourceProvider::factory()->create();
        $manager = new ServerSshKeyManager($server, $sourceProvider);

        // Act
        $result = $manager->addServerKeyToGitHub();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test addServerKeyToGitHub returns false when credential has no public key.
     */
    public function test_add_server_key_to_github_returns_false_when_no_public_key(): void
    {
        // Arrange
        $user = \App\Models\User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $credential = ServerCredential::factory()->create([
            'server_id' => $server->id,
            'user' => 'brokeforge',
            'public_key' => '', // Empty public key
        ]);

        $sourceProvider = SourceProvider::factory()->create();
        $manager = new ServerSshKeyManager($server, $sourceProvider);

        // Act
        $result = $manager->addServerKeyToGitHub();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test addServerKeyToGitHub adds key successfully.
     */
    public function test_add_server_key_to_github_adds_key_successfully(): void
    {
        // Arrange
        $user = \App\Models\User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'test-server',
            'source_provider_ssh_key_added' => false,
            'source_provider_ssh_key_id' => null,
        ]);

        $credential = ServerCredential::factory()->create([
            'server_id' => $server->id,
            'user' => 'brokeforge',
            'public_key' => 'ssh-rsa AAAA123...',
        ]);

        $sourceProvider = SourceProvider::factory()->create(['access_token' => 'test-token']);

        \Illuminate\Support\Facades\Http::fake([
            'api.github.com/user/keys' => \Illuminate\Support\Facades\Http::response([
                'id' => 12345,
                'key' => 'ssh-rsa AAAA123...',
                'title' => 'test-server (BrokeForge)',
            ], 201),
        ]);

        $manager = new ServerSshKeyManager($server, $sourceProvider);

        // Act
        $result = $manager->addServerKeyToGitHub();

        // Assert
        $this->assertTrue($result);
        $server->refresh();
        $this->assertTrue($server->source_provider_ssh_key_added);
        $this->assertEquals('12345', $server->source_provider_ssh_key_id);
        $this->assertNotNull($server->source_provider_ssh_key_title);
    }

    /**
     * Test addServerKeyToGitHub returns false when API fails.
     */
    public function test_add_server_key_to_github_returns_false_when_api_fails(): void
    {
        // Arrange
        $user = \App\Models\User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $credential = ServerCredential::factory()->create([
            'server_id' => $server->id,
            'user' => 'brokeforge',
            'public_key' => 'ssh-rsa AAAA123...',
        ]);

        $sourceProvider = SourceProvider::factory()->create(['access_token' => 'test-token']);

        \Illuminate\Support\Facades\Http::fake([
            'api.github.com/user/keys' => \Illuminate\Support\Facades\Http::response([
                'message' => 'Key already exists',
            ], 422),
        ]);

        $manager = new ServerSshKeyManager($server, $sourceProvider);

        // Act
        $result = $manager->addServerKeyToGitHub();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test addServerKeyToGitHub handles exceptions.
     */
    public function test_add_server_key_to_github_handles_exceptions(): void
    {
        // Arrange
        $server = Mockery::mock(Server::class);
        $server->shouldReceive('credentials')->andThrow(new \Exception('Database error'));
        $server->shouldReceive('getAttribute')->with('id')->andReturn(1);

        $sourceProvider = SourceProvider::factory()->create();
        $manager = new ServerSshKeyManager($server, $sourceProvider);

        // Act
        $result = $manager->addServerKeyToGitHub();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test removeServerKeyFromGitHub returns true when no key to remove.
     */
    public function test_remove_server_key_from_github_returns_true_when_no_key_to_remove(): void
    {
        // Arrange
        $server = Mockery::mock(Server::class);
        $server->shouldReceive('getAttribute')->with('source_provider_ssh_key_added')->andReturn(false);
        $server->shouldReceive('getAttribute')->with('source_provider_ssh_key_id')->andReturn(null);
        $server->shouldReceive('getAttribute')->with('id')->andReturn(1);

        $sourceProvider = SourceProvider::factory()->create();
        $manager = new ServerSshKeyManager($server, $sourceProvider);

        // Act
        $result = $manager->removeServerKeyFromGitHub();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test removeServerKeyFromGitHub removes key successfully.
     */
    public function test_remove_server_key_from_github_removes_key_successfully(): void
    {
        // Arrange
        $user = \App\Models\User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'source_provider_ssh_key_added' => true,
            'source_provider_ssh_key_id' => '12345',
            'source_provider_ssh_key_title' => 'Test Server',
        ]);

        $sourceProvider = SourceProvider::factory()->create(['access_token' => 'test-token']);

        \Illuminate\Support\Facades\Http::fake([
            'api.github.com/user/keys/12345' => \Illuminate\Support\Facades\Http::response(null, 204),
        ]);

        $manager = new ServerSshKeyManager($server, $sourceProvider);

        // Act
        $result = $manager->removeServerKeyFromGitHub();

        // Assert
        $this->assertTrue($result);
        $server->refresh();
        $this->assertFalse($server->source_provider_ssh_key_added);
        $this->assertNull($server->source_provider_ssh_key_id);
        $this->assertNull($server->source_provider_ssh_key_title);
    }

    /**
     * Test removeServerKeyFromGitHub treats 404 as success.
     */
    public function test_remove_server_key_from_github_treats_404_as_success(): void
    {
        // Arrange
        $user = \App\Models\User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'source_provider_ssh_key_added' => true,
            'source_provider_ssh_key_id' => '12345',
        ]);

        $sourceProvider = SourceProvider::factory()->create(['access_token' => 'test-token']);

        \Illuminate\Support\Facades\Http::fake([
            'api.github.com/user/keys/12345' => \Illuminate\Support\Facades\Http::response(null, 404),
        ]);

        $manager = new ServerSshKeyManager($server, $sourceProvider);

        // Act
        $result = $manager->removeServerKeyFromGitHub();

        // Assert
        $this->assertTrue($result);
        $server->refresh();
        $this->assertFalse($server->source_provider_ssh_key_added);
    }

    /**
     * Test removeServerKeyFromGitHub returns false when API fails.
     */
    public function test_remove_server_key_from_github_returns_false_when_api_fails(): void
    {
        // Arrange
        $user = \App\Models\User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'source_provider_ssh_key_added' => true,
            'source_provider_ssh_key_id' => '12345',
        ]);

        $sourceProvider = SourceProvider::factory()->create(['access_token' => 'test-token']);

        \Illuminate\Support\Facades\Http::fake([
            'api.github.com/user/keys/12345' => \Illuminate\Support\Facades\Http::response(['message' => 'Server error'], 500),
        ]);

        $manager = new ServerSshKeyManager($server, $sourceProvider);

        // Act
        $result = $manager->removeServerKeyFromGitHub();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test removeServerKeyFromGitHub handles exceptions.
     */
    public function test_remove_server_key_from_github_handles_exceptions(): void
    {
        // Arrange
        $server = Mockery::mock(Server::class);
        $server->shouldReceive('getAttribute')->with('source_provider_ssh_key_added')->andReturn(true);
        $server->shouldReceive('getAttribute')->with('source_provider_ssh_key_id')->andThrow(new \Exception('Error'));
        $server->shouldReceive('getAttribute')->with('id')->andReturn(1);

        $sourceProvider = SourceProvider::factory()->create();
        $manager = new ServerSshKeyManager($server, $sourceProvider);

        // Act
        $result = $manager->removeServerKeyFromGitHub();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test hasServerKeyOnGitHub returns false when server has no credentials.
     */
    public function test_has_server_key_on_github_returns_false_when_no_credentials(): void
    {
        // Arrange
        $user = \App\Models\User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // No credentials created for this server

        $sourceProvider = SourceProvider::factory()->create();
        $manager = new ServerSshKeyManager($server, $sourceProvider);

        // Act
        $result = $manager->hasServerKeyOnGitHub();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test hasServerKeyOnGitHub returns true when key exists.
     */
    public function test_has_server_key_on_github_returns_true_when_key_exists(): void
    {
        // Arrange
        $user = \App\Models\User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $credential = ServerCredential::factory()->create([
            'server_id' => $server->id,
            'user' => 'brokeforge',
            'public_key' => 'ssh-rsa AAAA123...',
        ]);

        $sourceProvider = SourceProvider::factory()->create(['access_token' => 'test-token']);

        \Illuminate\Support\Facades\Http::fake([
            'api.github.com/user/keys' => \Illuminate\Support\Facades\Http::response([
                ['id' => 1, 'key' => 'ssh-rsa AAAA456...', 'title' => 'Other Key'],
                ['id' => 2, 'key' => 'ssh-rsa AAAA123...', 'title' => 'Server Key'],
            ], 200),
        ]);

        $manager = new ServerSshKeyManager($server, $sourceProvider);

        // Act
        $result = $manager->hasServerKeyOnGitHub();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test hasServerKeyOnGitHub returns false when key does not exist.
     */
    public function test_has_server_key_on_github_returns_false_when_key_does_not_exist(): void
    {
        // Arrange
        $user = \App\Models\User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $credential = ServerCredential::factory()->create([
            'server_id' => $server->id,
            'user' => 'brokeforge',
            'public_key' => 'ssh-rsa AAAA123...',
        ]);

        $sourceProvider = SourceProvider::factory()->create(['access_token' => 'test-token']);

        \Illuminate\Support\Facades\Http::fake([
            'api.github.com/user/keys' => \Illuminate\Support\Facades\Http::response([
                ['id' => 1, 'key' => 'ssh-rsa AAAA456...', 'title' => 'Other Key'],
                ['id' => 2, 'key' => 'ssh-rsa AAAA789...', 'title' => 'Another Key'],
            ], 200),
        ]);

        $manager = new ServerSshKeyManager($server, $sourceProvider);

        // Act
        $result = $manager->hasServerKeyOnGitHub();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test hasServerKeyOnGitHub returns false when API fails.
     */
    public function test_has_server_key_on_github_returns_false_when_api_fails(): void
    {
        // Arrange
        $user = \App\Models\User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $credential = ServerCredential::factory()->create([
            'server_id' => $server->id,
            'user' => 'brokeforge',
            'public_key' => 'ssh-rsa AAAA123...',
        ]);

        $sourceProvider = SourceProvider::factory()->create(['access_token' => 'test-token']);

        \Illuminate\Support\Facades\Http::fake([
            'api.github.com/user/keys' => \Illuminate\Support\Facades\Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $manager = new ServerSshKeyManager($server, $sourceProvider);

        // Act
        $result = $manager->hasServerKeyOnGitHub();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test hasServerKeyOnGitHub handles exceptions.
     */
    public function test_has_server_key_on_github_handles_exceptions(): void
    {
        // Arrange
        $server = Mockery::mock(Server::class);
        $server->shouldReceive('credentials')->andThrow(new \Exception('Error'));
        $server->shouldReceive('getAttribute')->with('id')->andReturn(1);

        $sourceProvider = SourceProvider::factory()->create();
        $manager = new ServerSshKeyManager($server, $sourceProvider);

        // Act
        $result = $manager->hasServerKeyOnGitHub();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test hasServerKeyOnGitHub matches keys with trimming.
     */
    public function test_has_server_key_on_github_matches_keys_with_trimming(): void
    {
        // Arrange
        $user = \App\Models\User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $credential = ServerCredential::factory()->create([
            'server_id' => $server->id,
            'user' => 'brokeforge',
            'public_key' => '  ssh-rsa AAAA123...  ', // With extra whitespace
        ]);

        $sourceProvider = SourceProvider::factory()->create(['access_token' => 'test-token']);

        \Illuminate\Support\Facades\Http::fake([
            'api.github.com/user/keys' => \Illuminate\Support\Facades\Http::response([
                ['id' => 1, 'key' => 'ssh-rsa AAAA123...  ', 'title' => 'Server Key'], // With whitespace
            ], 200),
        ]);

        $manager = new ServerSshKeyManager($server, $sourceProvider);

        // Act
        $result = $manager->hasServerKeyOnGitHub();

        // Assert
        $this->assertTrue($result);
    }
}
