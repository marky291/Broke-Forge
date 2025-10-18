<?php

namespace Tests\Unit\Packages\Services\Sites;

use App\Models\Server;
use App\Models\ServerSite;
use App\Models\User;
use App\Packages\Services\Sites\SiteDeployKeyGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteDeployKeyGeneratorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test commands generates correct SSH key generation command.
     */
    public function test_commands_generates_correct_ssh_key_generation_command(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
        ]);

        $generator = new SiteDeployKeyGenerator($server);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($generator);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $keyPath = "/home/brokeforge/.ssh/site_{$site->id}_rsa";
        $keyTitle = "BrokeForge Site - {$site->domain}";

        // Act
        $commands = $method->invoke($generator, $site, $keyPath, $keyTitle);

        // Assert
        $this->assertIsArray($commands);
        $this->assertCount(4, $commands);

        // Find the ssh-keygen command
        $foundKeygenCommand = false;
        foreach ($commands as $command) {
            if (is_string($command) && str_contains($command, 'ssh-keygen -t ed25519')) {
                $foundKeygenCommand = true;
                $this->assertStringContainsString('site_', $command);
                $this->assertStringContainsString('_rsa', $command);
                $this->assertStringContainsString('-N ""', $command); // No passphrase
                break;
            }
        }

        $this->assertTrue($foundKeygenCommand, 'ssh-keygen command not found');
    }

    /**
     * Test commands includes chmod 600 for private key.
     */
    public function test_commands_includes_chmod_600_for_private_key(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
        ]);

        $generator = new SiteDeployKeyGenerator($server);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($generator);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $keyPath = "/home/brokeforge/.ssh/site_{$site->id}_rsa";
        $keyTitle = "BrokeForge Site - {$site->domain}";

        // Act
        $commands = $method->invoke($generator, $site, $keyPath, $keyTitle);

        // Assert
        $foundChmod600Command = false;
        foreach ($commands as $command) {
            if (is_string($command) && str_contains($command, 'chmod 600')) {
                $foundChmod600Command = true;
                break;
            }
        }

        $this->assertTrue($foundChmod600Command, 'chmod 600 command not found');
    }

    /**
     * Test commands includes chmod 644 for public key.
     */
    public function test_commands_includes_chmod_644_for_public_key(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
        ]);

        $generator = new SiteDeployKeyGenerator($server);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($generator);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $keyPath = "/home/brokeforge/.ssh/site_{$site->id}_rsa";
        $keyTitle = "BrokeForge Site - {$site->domain}";

        // Act
        $commands = $method->invoke($generator, $site, $keyPath, $keyTitle);

        // Assert
        $foundChmod644Command = false;
        foreach ($commands as $command) {
            if (is_string($command) && str_contains($command, 'chmod 644')) {
                $foundChmod644Command = true;
                $this->assertStringContainsString('.pub', $command);
                break;
            }
        }

        $this->assertTrue($foundChmod644Command, 'chmod 644 command for .pub file not found');
    }

    /**
     * Test commands includes closure to read public key.
     */
    public function test_commands_includes_closure_to_read_public_key(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
        ]);

        $generator = new SiteDeployKeyGenerator($server);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($generator);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $keyPath = "/home/brokeforge/.ssh/site_{$site->id}_rsa";
        $keyTitle = "BrokeForge Site - {$site->domain}";

        // Act
        $commands = $method->invoke($generator, $site, $keyPath, $keyTitle);

        // Assert - Find closure in commands
        $foundClosure = false;
        foreach ($commands as $command) {
            if ($command instanceof \Closure) {
                $foundClosure = true;
                break;
            }
        }

        $this->assertTrue($foundClosure, 'Closure to read public key not found in commands');
    }

    /**
     * Test commands uses site-specific key path.
     */
    public function test_commands_uses_site_specific_key_path(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
        ]);

        $generator = new SiteDeployKeyGenerator($server);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($generator);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $keyPath = "/home/brokeforge/.ssh/site_{$site->id}_rsa";
        $keyTitle = "BrokeForge Site - {$site->domain}";

        // Act
        $commands = $method->invoke($generator, $site, $keyPath, $keyTitle);

        // Assert - Verify the key path is site-specific
        $this->assertStringContainsString((string) $site->id, $keyPath);
        $this->assertStringContainsString('/home/brokeforge/.ssh/', $keyPath);
    }

    /**
     * Test commands includes site domain in key title.
     */
    public function test_commands_includes_site_domain_in_key_title(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'test-domain.com',
        ]);

        $generator = new SiteDeployKeyGenerator($server);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($generator);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $keyPath = "/home/brokeforge/.ssh/site_{$site->id}_rsa";
        $keyTitle = "BrokeForge Site - {$site->domain}";

        // Act
        $commands = $method->invoke($generator, $site, $keyPath, $keyTitle);

        // Assert - Find command with title
        $foundTitleInCommand = false;
        foreach ($commands as $command) {
            if (is_string($command) && str_contains($command, $site->domain)) {
                $foundTitleInCommand = true;
                break;
            }
        }

        $this->assertTrue($foundTitleInCommand, 'Site domain not found in commands');
    }

    /**
     * Test commands uses ed25519 key type.
     */
    public function test_commands_uses_ed25519_key_type(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
        ]);

        $generator = new SiteDeployKeyGenerator($server);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($generator);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $keyPath = "/home/brokeforge/.ssh/site_{$site->id}_rsa";
        $keyTitle = "BrokeForge Site - {$site->domain}";

        // Act
        $commands = $method->invoke($generator, $site, $keyPath, $keyTitle);

        // Assert - Verify ed25519 key type is used
        $foundEd25519 = false;
        foreach ($commands as $command) {
            if (is_string($command) && str_contains($command, '-t ed25519')) {
                $foundEd25519 = true;
                break;
            }
        }

        $this->assertTrue($foundEd25519, 'ed25519 key type not found in commands');
    }
}
