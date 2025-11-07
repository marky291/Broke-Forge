<?php

namespace Tests\Unit\Packages\Services\Sites;

use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Services\Sites\SiteUnsetDefaultInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class SiteUnsetDefaultInstallerTest extends TestCase
{
    use RefreshDatabase;

    private function invokeProtectedMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Test generates correct commands for unsetting default site.
     */
    public function test_generates_correct_commands_for_unsetting_default(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
            'php_version' => '8.4',
            'is_default' => true,
            'status' => 'active',
        ]);

        $installer = new SiteUnsetDefaultInstaller($server);

        // Act
        $commands = $this->invokeProtectedMethod($installer, 'commands', [$site]);

        // Assert
        $this->assertCount(3, $commands);
        $this->assertEquals('rm -f /home/brokeforge/default', $commands[0]);
        $this->assertEquals('sudo service php8.4-fpm reload', $commands[1]);
        $this->assertEquals('test ! -e /home/brokeforge/default', $commands[2]);
    }

    /**
     * Test symlink removal command uses rm -f flag.
     */
    public function test_symlink_removal_uses_force_flag(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
            'is_default' => true,
            'status' => 'active',
        ]);

        $installer = new SiteUnsetDefaultInstaller($server);

        // Act
        $commands = $this->invokeProtectedMethod($installer, 'commands', [$site]);

        // Assert - verify force removal flag
        $this->assertStringContainsString('rm -f', $commands[0]);
    }

    /**
     * Test commands include PHP-FPM reload for site's PHP version.
     */
    public function test_commands_include_php_fpm_reload_for_correct_version(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
            'php_version' => '8.3',
            'is_default' => true,
            'status' => 'active',
        ]);

        $installer = new SiteUnsetDefaultInstaller($server);

        // Act
        $commands = $this->invokeProtectedMethod($installer, 'commands', [$site]);

        // Assert
        $this->assertStringContainsString('php8.3-fpm', $commands[1]);
    }

    /**
     * Test commands include verification that symlink was removed.
     */
    public function test_commands_include_symlink_removal_verification(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
            'is_default' => true,
            'status' => 'active',
        ]);

        $installer = new SiteUnsetDefaultInstaller($server);

        // Act
        $commands = $this->invokeProtectedMethod($installer, 'commands', [$site]);

        // Assert - verify command checks symlink doesn't exist
        $this->assertStringContainsString('test ! -e /home/brokeforge/default', $commands[2]);
    }

    /**
     * Test commands use correct brokeforge user path.
     */
    public function test_commands_use_correct_app_user_path(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
            'is_default' => true,
            'status' => 'active',
        ]);

        $installer = new SiteUnsetDefaultInstaller($server);

        // Act
        $commands = $this->invokeProtectedMethod($installer, 'commands', [$site]);

        // Assert - verify all commands use /home/brokeforge path
        $this->assertStringContainsString('/home/brokeforge/default', $commands[0]);
        $this->assertStringContainsString('/home/brokeforge/default', $commands[2]);
    }

    /**
     * Test handles PHP version 8.1 correctly.
     */
    public function test_handles_php_8_1_version_correctly(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
            'php_version' => '8.1',
            'is_default' => true,
            'status' => 'active',
        ]);

        $installer = new SiteUnsetDefaultInstaller($server);

        // Act
        $commands = $this->invokeProtectedMethod($installer, 'commands', [$site]);

        // Assert
        $this->assertStringContainsString('php8.1-fpm', $commands[1]);
    }

    /**
     * Test command array contains exactly 3 commands in correct order.
     */
    public function test_command_array_has_correct_order(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
            'is_default' => true,
            'status' => 'active',
        ]);

        $installer = new SiteUnsetDefaultInstaller($server);

        // Act
        $commands = $this->invokeProtectedMethod($installer, 'commands', [$site]);

        // Assert - verify order: remove, reload, verify
        $this->assertCount(3, $commands);
        $this->assertStringContainsString('rm -f', $commands[0]);
        $this->assertStringContainsString('service', $commands[1]);
        $this->assertStringContainsString('test !', $commands[2]);
    }
}
