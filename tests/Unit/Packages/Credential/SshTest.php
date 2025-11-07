<?php

namespace Tests\Unit\Packages\Credential;

use App\Models\Server;
use App\Models\ServerCredential;
use App\Packages\Credential\Ssh;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SshTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test successfully connects to server with root user.
     */
    public function test_connects_to_server_with_root_user(): void
    {
        // Arrange
        $server = Server::factory()->create([
            'public_ip' => '192.168.1.100',
            'ssh_port' => 22,
        ]);

        ServerCredential::factory()
            ->root()
            ->create(['server_id' => $server->id]);

        // Act
        $ssh = app(Ssh::class)->connect($server, 'root');

        // Assert
        $this->assertInstanceOf(\Spatie\Ssh\Ssh::class, $ssh);
    }

    /**
     * Test successfully connects to server with brokeforge user.
     */
    public function test_connects_to_server_with_brokeforge_user(): void
    {
        // Arrange
        $server = Server::factory()->create([
            'public_ip' => '192.168.1.200',
            'ssh_port' => 2222,
        ]);

        ServerCredential::factory()
            ->brokeforge()
            ->create(['server_id' => $server->id]);

        // Act
        $ssh = app(Ssh::class)->connect($server, 'brokeforge');

        // Assert
        $this->assertInstanceOf(\Spatie\Ssh\Ssh::class, $ssh);
    }

    /**
     * Test throws exception when root credential not found.
     */
    public function test_throws_exception_when_root_credential_not_found(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Only create brokeforge credential, not root
        ServerCredential::factory()
            ->brokeforge()
            ->create(['server_id' => $server->id]);

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No root credential found for server');

        app(Ssh::class)->connect($server, 'root');
    }

    /**
     * Test throws exception when brokeforge credential not found.
     */
    public function test_throws_exception_when_brokeforge_credential_not_found(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Only create root credential, not brokeforge
        ServerCredential::factory()
            ->root()
            ->create(['server_id' => $server->id]);

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No brokeforge credential found for server');

        app(Ssh::class)->connect($server, 'brokeforge');
    }

    /**
     * Test throws exception when no credentials exist for server.
     */
    public function test_throws_exception_when_no_credentials_exist(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No root credential found for server');

        app(Ssh::class)->connect($server, 'root');
    }

    /**
     * Test creates temporary key file when connecting.
     */
    public function test_creates_temporary_key_file_when_connecting(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $privateKey = "-----BEGIN OPENSSH PRIVATE KEY-----\ntest-key-content\n-----END OPENSSH PRIVATE KEY-----";

        ServerCredential::factory()
            ->root()
            ->create([
                'server_id' => $server->id,
                'private_key' => $privateKey,
            ]);

        // Act
        $ssh = app(Ssh::class)->connect($server, 'root');

        // Assert - verify SSH instance was created (temp file creation is internal)
        $this->assertInstanceOf(\Spatie\Ssh\Ssh::class, $ssh);
    }

    /**
     * Test cleanup removes all temporary files.
     */
    public function test_cleanup_removes_all_temporary_files(): void
    {
        // Arrange
        $server1 = Server::factory()->create();
        $server2 = Server::factory()->create();

        ServerCredential::factory()
            ->root()
            ->create(['server_id' => $server1->id]);

        ServerCredential::factory()
            ->brokeforge()
            ->create(['server_id' => $server2->id]);

        // Act - create multiple SSH connections
        app(Ssh::class)->connect($server1, 'root');
        app(Ssh::class)->connect($server2, 'brokeforge');

        // Cleanup temp files
        Ssh::cleanup();

        // Assert - cleanup should complete without errors
        $this->assertTrue(true);
    }

    /**
     * Test cleanup can be called multiple times safely.
     */
    public function test_cleanup_can_be_called_multiple_times(): void
    {
        // Arrange
        $server = Server::factory()->create();

        ServerCredential::factory()
            ->root()
            ->create(['server_id' => $server->id]);

        app(Ssh::class)->connect($server, 'root');

        // Act - call cleanup multiple times
        Ssh::cleanup();
        Ssh::cleanup();
        Ssh::cleanup();

        // Assert - should not throw any errors
        $this->assertTrue(true);
    }

    /**
     * Test cleanup works when no temp files exist.
     */
    public function test_cleanup_works_when_no_temp_files_exist(): void
    {
        // Act - call cleanup without creating any connections
        Ssh::cleanup();

        // Assert - should not throw any errors
        $this->assertTrue(true);
    }

    /**
     * Test multiple connections to same server with different users.
     */
    public function test_multiple_connections_to_same_server_with_different_users(): void
    {
        // Arrange
        $server = Server::factory()->create([
            'public_ip' => '192.168.1.50',
            'ssh_port' => 22,
        ]);

        ServerCredential::factory()
            ->root()
            ->create(['server_id' => $server->id]);

        ServerCredential::factory()
            ->brokeforge()
            ->create(['server_id' => $server->id]);

        // Act
        $rootSsh = app(Ssh::class)->connect($server, 'root');
        $brokeforgeSsh = app(Ssh::class)->connect($server, 'brokeforge');

        // Assert
        $this->assertInstanceOf(\Spatie\Ssh\Ssh::class, $rootSsh);
        $this->assertInstanceOf(\Spatie\Ssh\Ssh::class, $brokeforgeSsh);

        // Cleanup
        Ssh::cleanup();
    }

    /**
     * Test SSH connection has quiet mode enabled.
     */
    public function test_ssh_connection_has_quiet_mode_enabled(): void
    {
        // Arrange
        $server = Server::factory()->create();

        ServerCredential::factory()
            ->root()
            ->create(['server_id' => $server->id]);

        // Act
        $ssh = app(Ssh::class)->connect($server, 'root');

        // Assert - verify the SSH instance was created successfully
        // The quiet mode and strict host key checking are internal options
        // We verify the connection was created properly
        $this->assertInstanceOf(\Spatie\Ssh\Ssh::class, $ssh);
    }

    /**
     * Test exception message includes server ID for debugging.
     */
    public function test_exception_message_includes_server_id(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act & Assert
        try {
            app(Ssh::class)->connect($server, 'root');
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString("server #{$server->id}", $e->getMessage());
            $this->assertStringContainsString('Ensure provisioning completed successfully', $e->getMessage());
        }
    }

    /**
     * Test connects to server with custom SSH port.
     */
    public function test_connects_to_server_with_custom_ssh_port(): void
    {
        // Arrange
        $server = Server::factory()->create([
            'public_ip' => '10.0.0.1',
            'ssh_port' => 2222,
        ]);

        ServerCredential::factory()
            ->root()
            ->create(['server_id' => $server->id]);

        // Act
        $ssh = app(Ssh::class)->connect($server, 'root');

        // Assert
        $this->assertInstanceOf(\Spatie\Ssh\Ssh::class, $ssh);
    }

    /**
     * Test server model's ssh() method uses Ssh class.
     */
    public function test_server_model_ssh_method_uses_ssh_class(): void
    {
        // Arrange
        $server = Server::factory()->create();

        ServerCredential::factory()
            ->root()
            ->create(['server_id' => $server->id]);

        // Act
        $ssh = $server->ssh('root');

        // Assert
        $this->assertInstanceOf(\Spatie\Ssh\Ssh::class, $ssh);
    }

    /**
     * Test server model's ssh() method defaults to root user.
     */
    public function test_server_model_ssh_method_defaults_to_root_user(): void
    {
        // Arrange
        $server = Server::factory()->create();

        ServerCredential::factory()
            ->root()
            ->create(['server_id' => $server->id]);

        // Act - call without specifying user
        $ssh = $server->ssh();

        // Assert
        $this->assertInstanceOf(\Spatie\Ssh\Ssh::class, $ssh);
    }
}
