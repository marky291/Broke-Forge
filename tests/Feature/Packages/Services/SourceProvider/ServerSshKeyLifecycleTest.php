<?php

namespace Tests\Feature\Packages\Services\SourceProvider;

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

class ServerSshKeyLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_adds_ssh_key_to_github_during_provisioning(): void
    {
        Http::fake([
            'https://api.github.com/user/keys' => Http::response(['id' => 12345678], 201),
        ]);

        $user = User::factory()->create();
        $githubProvider = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
            'access_token' => 'fake-token',
        ]);

        $server = Server::factory()->create([
            'user_id' => $user->id,
            'source_provider_ssh_key_added' => false,
        ]);

        // Create BrokeForge credential with SSH keys
        ServerCredential::factory()->create([
            'server_id' => $server->id,
            'credential_type' => CredentialType::BrokeForge->value,
            'public_key' => 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQC... brokeforge@server',
            'private_key' => '-----BEGIN RSA PRIVATE KEY-----...',
        ]);

        $keyManager = new ServerSshKeyManager($server, $githubProvider);
        $result = $keyManager->addServerKeyToGitHub();

        $this->assertTrue($result);

        // Verify server fields were updated
        $server->refresh();
        $this->assertTrue($server->source_provider_ssh_key_added);
        $this->assertEquals('12345678', $server->source_provider_ssh_key_id);
        $this->assertStringContainsString('BrokeForge Server', $server->source_provider_ssh_key_title);

        // Verify HTTP request was made
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.github.com/user/keys' &&
                   $request->method() === 'POST' &&
                   $request['key'] === 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQC... brokeforge@server';
        });
    }

    public function test_adding_ssh_key_failure_does_not_block_provisioning(): void
    {
        Http::fake([
            'https://api.github.com/user/keys' => Http::response([
                'message' => 'Not Found',
                'documentation_url' => 'https://docs.github.com/rest/users/keys',
            ], 404),
        ]);

        $user = User::factory()->create();
        $githubProvider = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
            'access_token' => 'invalid-token',
        ]);

        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerCredential::factory()->create([
            'server_id' => $server->id,
            'credential_type' => CredentialType::BrokeForge->value,
            'public_key' => 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQC... brokeforge@server',
        ]);

        Log::spy();

        $keyManager = new ServerSshKeyManager($server, $githubProvider);
        $result = $keyManager->addServerKeyToGitHub();

        // Should return false but not throw exception
        $this->assertFalse($result);

        // Verify error was logged
        Log::shouldHaveReceived('error')
            ->with('Failed to add server SSH key to GitHub', \Mockery::any());

        // Verify server fields were NOT updated
        $server->refresh();
        $this->assertFalse($server->source_provider_ssh_key_added);
        $this->assertNull($server->source_provider_ssh_key_id);
    }

    public function test_removes_ssh_key_from_github_when_server_is_deleted(): void
    {
        Http::fake([
            'https://api.github.com/user/keys/*' => Http::response(null, 204),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $githubProvider = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
            'access_token' => 'fake-token',
        ]);

        $server = Server::factory()->create([
            'user_id' => $user->id,
            'source_provider_ssh_key_added' => true,
            'source_provider_ssh_key_id' => '12345678',
            'source_provider_ssh_key_title' => 'BrokeForge Server - Test Server',
        ]);

        ServerCredential::factory()->create([
            'server_id' => $server->id,
            'credential_type' => CredentialType::BrokeForge->value,
        ]);

        Log::spy();

        // Delete server - should trigger deleting event
        $server->delete();

        // Verify HTTP request was made to remove key
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'https://api.github.com/user/keys/12345678') &&
                   $request->method() === 'DELETE';
        });

        // Verify log entry
        Log::shouldHaveReceived('info')
            ->with('Removed server SSH key from GitHub during deletion', \Mockery::any());
    }

    public function test_handles_missing_key_gracefully_during_deletion(): void
    {
        Http::fake([
            'https://api.github.com/user/keys/*' => Http::response([
                'message' => 'Not Found',
            ], 404),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $githubProvider = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
            'access_token' => 'fake-token',
        ]);

        $server = Server::factory()->create([
            'user_id' => $user->id,
            'source_provider_ssh_key_added' => true,
            'source_provider_ssh_key_id' => '12345678',
        ]);

        ServerCredential::factory()->create([
            'server_id' => $server->id,
            'credential_type' => CredentialType::BrokeForge->value,
        ]);

        Log::spy();

        // Delete server - should handle 404 gracefully
        $server->delete();

        // Verify server was deleted despite 404
        $this->assertModelMissing($server);

        // Verify info log (404 is treated as success)
        Log::shouldHaveReceived('info')
            ->with('Removed server SSH key from GitHub during deletion', \Mockery::any());
    }

    public function test_deletion_continues_when_github_api_fails(): void
    {
        Http::fake([
            'https://api.github.com/user/keys/*' => Http::response([
                'message' => 'Internal Server Error',
            ], 500),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $githubProvider = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
            'access_token' => 'fake-token',
        ]);

        $server = Server::factory()->create([
            'user_id' => $user->id,
            'source_provider_ssh_key_added' => true,
            'source_provider_ssh_key_id' => '12345678',
        ]);

        ServerCredential::factory()->create([
            'server_id' => $server->id,
            'credential_type' => CredentialType::BrokeForge->value,
        ]);

        Log::spy();

        // Delete server - should NOT fail despite API error
        $server->delete();

        // Verify server was deleted
        $this->assertModelMissing($server);

        // Verify error was logged from ServerSshKeyManager
        Log::shouldHaveReceived('error')
            ->with('Failed to remove server SSH key from GitHub', \Mockery::any());
    }

    public function test_skips_removal_when_user_has_no_github_connected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // No GitHub provider for this user

        $server = Server::factory()->create([
            'user_id' => $user->id,
            'source_provider_ssh_key_added' => true,
            'source_provider_ssh_key_id' => '12345678',
        ]);

        Log::spy();

        // Delete server - should skip GitHub removal
        $server->delete();

        // Verify server was deleted
        $this->assertModelMissing($server);

        // Verify no HTTP requests were made
        Http::assertNothingSent();

        // Verify no error logs
        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('warning');
    }

    public function test_skips_removal_when_key_was_never_added(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

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

        // Delete server - should skip GitHub removal
        $server->delete();

        // Verify server was deleted
        $this->assertModelMissing($server);

        // Verify no HTTP requests were made
        Http::assertNothingSent();
    }

    public function test_creates_activity_log_on_server_deletion(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Production Server',
            'public_ip' => '192.168.1.100',
            'source_provider_ssh_key_added' => true,
        ]);

        $server->delete();

        // Verify activity log was created
        $this->assertDatabaseHas('activity_log', [
            'causer_id' => $user->id,
            'event' => 'server.deleted',
            'description' => 'Server Production Server deleted',
        ]);
    }
}
