<?php

namespace Tests\Unit\Packages\Services\SourceProvider;

use App\Models\Server;
use App\Models\ServerCredential;
use App\Models\SourceProvider;
use App\Models\User;
use App\Packages\Enums\CredentialType;
use App\Packages\Services\SourceProvider\ServerSshKeyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ServerSshKeyManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_server_key_to_github_success(): void
    {
        Http::fake([
            'https://api.github.com/user/keys' => Http::response([
                'id' => 987654321,
                'key' => 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQC...',
                'title' => 'BrokeForge Server - Test Server',
            ], 201),
        ]);

        $user = User::factory()->create();
        $githubProvider = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
            'access_token' => 'valid-token',
        ]);

        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Test Server',
        ]);

        ServerCredential::factory()->create([
            'server_id' => $server->id,
            'credential_type' => CredentialType::BrokeForge->value,
            'public_key' => 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQC...',
        ]);

        $keyManager = new ServerSshKeyManager($server, $githubProvider);
        $result = $keyManager->addServerKeyToGitHub();

        $this->assertTrue($result);

        $server->refresh();
        $this->assertTrue($server->source_provider_ssh_key_added);
        $this->assertEquals('987654321', $server->source_provider_ssh_key_id);
        $this->assertEquals('BrokeForge Server - Test Server', $server->source_provider_ssh_key_title);
    }

    public function test_add_server_key_returns_false_on_404(): void
    {
        Http::fake([
            'https://api.github.com/user/keys' => Http::response([
                'message' => 'Not Found',
            ], 404),
        ]);

        $user = User::factory()->create();
        $githubProvider = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
            'access_token' => 'invalid-scope-token',
        ]);

        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerCredential::factory()->create([
            'server_id' => $server->id,
            'credential_type' => CredentialType::BrokeForge->value,
            'public_key' => 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQC...',
        ]);

        Log::spy();

        $keyManager = new ServerSshKeyManager($server, $githubProvider);
        $result = $keyManager->addServerKeyToGitHub();

        $this->assertFalse($result);

        Log::shouldHaveReceived('error')
            ->with('Failed to add server SSH key to GitHub', \Mockery::on(function ($arg) {
                return $arg['status'] === 404;
            }));
    }

    public function test_add_server_key_returns_false_on_401(): void
    {
        Http::fake([
            'https://api.github.com/user/keys' => Http::response([
                'message' => 'Requires authentication',
            ], 401),
        ]);

        $user = User::factory()->create();
        $githubProvider = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
            'access_token' => 'expired-token',
        ]);

        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerCredential::factory()->create([
            'server_id' => $server->id,
            'credential_type' => CredentialType::BrokeForge->value,
            'public_key' => 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQC...',
        ]);

        $keyManager = new ServerSshKeyManager($server, $githubProvider);
        $result = $keyManager->addServerKeyToGitHub();

        $this->assertFalse($result);
    }

    public function test_add_server_key_returns_false_when_credential_missing(): void
    {
        $user = User::factory()->create();
        $githubProvider = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
        ]);

        $server = Server::factory()->create(['user_id' => $user->id]);

        // No credential created

        Log::spy();

        $keyManager = new ServerSshKeyManager($server, $githubProvider);
        $result = $keyManager->addServerKeyToGitHub();

        $this->assertFalse($result);

        Log::shouldHaveReceived('error')
            ->with('Server SSH key not found', \Mockery::on(function ($arg) use ($server) {
                return $arg['server_id'] === $server->id;
            }));
    }

    public function test_remove_server_key_from_github_success(): void
    {
        Http::fake([
            'https://api.github.com/user/keys/*' => Http::response(null, 204),
        ]);

        $user = User::factory()->create();
        $githubProvider = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
            'access_token' => 'valid-token',
        ]);

        $server = Server::factory()->create([
            'user_id' => $user->id,
            'source_provider_ssh_key_added' => true,
            'source_provider_ssh_key_id' => '123456',
            'source_provider_ssh_key_title' => 'BrokeForge Server - Test',
        ]);

        $keyManager = new ServerSshKeyManager($server, $githubProvider);
        $result = $keyManager->removeServerKeyFromGitHub();

        $this->assertTrue($result);

        $server->refresh();
        $this->assertFalse($server->source_provider_ssh_key_added);
        $this->assertNull($server->source_provider_ssh_key_id);
        $this->assertNull($server->source_provider_ssh_key_title);
    }

    public function test_remove_server_key_handles_404_gracefully(): void
    {
        Http::fake([
            'https://api.github.com/user/keys/*' => Http::response([
                'message' => 'Not Found',
            ], 404),
        ]);

        $user = User::factory()->create();
        $githubProvider = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
            'access_token' => 'valid-token',
        ]);

        $server = Server::factory()->create([
            'user_id' => $user->id,
            'source_provider_ssh_key_added' => true,
            'source_provider_ssh_key_id' => '999999',
        ]);

        $keyManager = new ServerSshKeyManager($server, $githubProvider);
        $result = $keyManager->removeServerKeyFromGitHub();

        // 404 is treated as success (key already gone)
        $this->assertTrue($result);

        $server->refresh();
        $this->assertFalse($server->source_provider_ssh_key_added);
        $this->assertNull($server->source_provider_ssh_key_id);
    }

    public function test_remove_server_key_returns_false_on_other_errors(): void
    {
        Http::fake([
            'https://api.github.com/user/keys/*' => Http::response([
                'message' => 'Internal Server Error',
            ], 500),
        ]);

        $user = User::factory()->create();
        $githubProvider = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
            'access_token' => 'valid-token',
        ]);

        $server = Server::factory()->create([
            'user_id' => $user->id,
            'source_provider_ssh_key_added' => true,
            'source_provider_ssh_key_id' => '123456',
        ]);

        Log::spy();

        $keyManager = new ServerSshKeyManager($server, $githubProvider);
        $result = $keyManager->removeServerKeyFromGitHub();

        $this->assertFalse($result);

        Log::shouldHaveReceived('error')
            ->with('Failed to remove server SSH key from GitHub', \Mockery::any());

        // Server fields should NOT be cleared on failure
        $server->refresh();
        $this->assertTrue($server->source_provider_ssh_key_added);
        $this->assertEquals('123456', $server->source_provider_ssh_key_id);
    }

    public function test_remove_server_key_returns_true_when_no_key_to_remove(): void
    {
        $user = User::factory()->create();
        $githubProvider = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
        ]);

        $server = Server::factory()->create([
            'user_id' => $user->id,
            'source_provider_ssh_key_added' => false,
            'source_provider_ssh_key_id' => null,
        ]);

        Log::spy();

        $keyManager = new ServerSshKeyManager($server, $githubProvider);
        $result = $keyManager->removeServerKeyFromGitHub();

        // Nothing to remove = success
        $this->assertTrue($result);

        Log::shouldHaveReceived('warning')
            ->with('No server SSH key to remove from GitHub', \Mockery::any());

        // Verify no HTTP requests were made
        Http::assertNothingSent();
    }

    public function test_has_server_key_on_github_returns_true_when_key_exists(): void
    {
        Http::fake([
            'https://api.github.com/user/keys' => Http::response([
                [
                    'id' => 123,
                    'key' => 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQC...',
                    'title' => 'Personal Key',
                ],
                [
                    'id' => 456,
                    'key' => 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQD...',
                    'title' => 'BrokeForge Server',
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $githubProvider = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
            'access_token' => 'valid-token',
        ]);

        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerCredential::factory()->create([
            'server_id' => $server->id,
            'credential_type' => CredentialType::BrokeForge->value,
            'public_key' => 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQD...',
        ]);

        $keyManager = new ServerSshKeyManager($server, $githubProvider);
        $result = $keyManager->hasServerKeyOnGitHub();

        $this->assertTrue($result);
    }

    public function test_has_server_key_on_github_returns_false_when_key_does_not_exist(): void
    {
        Http::fake([
            'https://api.github.com/user/keys' => Http::response([
                [
                    'id' => 123,
                    'key' => 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQC...',
                    'title' => 'Personal Key',
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $githubProvider = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
            'access_token' => 'valid-token',
        ]);

        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerCredential::factory()->create([
            'server_id' => $server->id,
            'credential_type' => CredentialType::BrokeForge->value,
            'public_key' => 'ssh-rsa DIFFERENT_KEY_NOT_ON_GITHUB',
        ]);

        $keyManager = new ServerSshKeyManager($server, $githubProvider);
        $result = $keyManager->hasServerKeyOnGitHub();

        $this->assertFalse($result);
    }

    public function test_has_server_key_on_github_returns_false_on_api_error(): void
    {
        Http::fake([
            'https://api.github.com/user/keys' => Http::response(null, 500),
        ]);

        $user = User::factory()->create();
        $githubProvider = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
            'access_token' => 'valid-token',
        ]);

        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerCredential::factory()->create([
            'server_id' => $server->id,
            'credential_type' => CredentialType::BrokeForge->value,
            'public_key' => 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQD...',
        ]);

        Log::spy();

        $keyManager = new ServerSshKeyManager($server, $githubProvider);
        $result = $keyManager->hasServerKeyOnGitHub();

        $this->assertFalse($result);

        Log::shouldHaveReceived('error')
            ->with('Failed to fetch user SSH keys from GitHub', \Mockery::any());
    }
}
