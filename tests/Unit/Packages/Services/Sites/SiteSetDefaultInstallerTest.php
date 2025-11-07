<?php

namespace Tests\Unit\Packages\Services\Sites;

use App\Models\Server;
use App\Models\ServerDeployment;
use App\Models\ServerSite;
use App\Packages\Services\Sites\SiteSetDefaultInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class SiteSetDefaultInstallerTest extends TestCase
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
     * Test generates correct commands for setting default site.
     */
    public function test_generates_correct_commands_for_default_site(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
            'php_version' => '8.4',
            'status' => 'active',
        ]);

        $installer = new SiteSetDefaultInstaller($server);

        // Act
        $commands = $this->invokeProtectedMethod($installer, 'commands', [$site]);

        // Assert
        $this->assertCount(3, $commands);
        $this->assertStringContainsString('ln -sfn example.com /home/brokeforge/default', $commands[0]);
        $this->assertEquals('sudo service php8.4-fpm reload', $commands[1]);
        $this->assertEquals('readlink /home/brokeforge/default', $commands[2]);
    }

    /**
     * Test determines correct source path for original default site.
     */
    public function test_determines_source_path_for_original_default_site(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'default',
            'status' => 'active',
            'configuration' => [
                'default_deployment_path' => '/home/brokeforge/deployments/default/07112025-120000',
            ],
        ]);

        $installer = new SiteSetDefaultInstaller($server);

        // Act
        $sourcePath = $this->invokeProtectedMethod($installer, 'determineSourcePath', [$site]);

        // Assert
        $this->assertEquals('deployments/default/07112025-120000', $sourcePath);
    }

    /**
     * Test determines correct source path for site with active deployment.
     */
    public function test_determines_source_path_for_site_with_active_deployment(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
            'status' => 'active',
        ]);

        $deployment = ServerDeployment::factory()->create([
            'server_id' => $server->id,
            'server_site_id' => $site->id,
            'deployment_path' => '/home/brokeforge/deployments/example.com/07112025-150000',
            'status' => 'success',
        ]);

        // Set active deployment
        $site->update(['active_deployment_id' => $deployment->id]);
        $site->refresh();

        $installer = new SiteSetDefaultInstaller($server);

        // Act
        $sourcePath = $this->invokeProtectedMethod($installer, 'determineSourcePath', [$site]);

        // Assert
        $this->assertEquals('deployments/example.com/07112025-150000', $sourcePath);
    }

    /**
     * Test falls back to domain when no deployment info available.
     */
    public function test_falls_back_to_domain_when_no_deployment_available(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
            'status' => 'active',
        ]);

        $installer = new SiteSetDefaultInstaller($server);

        // Act
        $sourcePath = $this->invokeProtectedMethod($installer, 'determineSourcePath', [$site]);

        // Assert
        $this->assertEquals('example.com', $sourcePath);
    }

    /**
     * Test symlink command uses atomic ln -sfn flag.
     */
    public function test_symlink_command_uses_atomic_flag(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
            'status' => 'active',
        ]);

        $installer = new SiteSetDefaultInstaller($server);

        // Act
        $commands = $this->invokeProtectedMethod($installer, 'commands', [$site]);

        // Assert - verify atomic symlink swap with -sfn flags
        $this->assertStringContainsString('ln -sfn', $commands[0]);
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
            'status' => 'active',
        ]);

        $installer = new SiteSetDefaultInstaller($server);

        // Act
        $commands = $this->invokeProtectedMethod($installer, 'commands', [$site]);

        // Assert
        $this->assertStringContainsString('php8.3-fpm', $commands[1]);
    }

    /**
     * Test commands include symlink verification.
     */
    public function test_commands_include_symlink_verification(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
            'status' => 'active',
        ]);

        $installer = new SiteSetDefaultInstaller($server);

        // Act
        $commands = $this->invokeProtectedMethod($installer, 'commands', [$site]);

        // Assert
        $this->assertStringContainsString('readlink /home/brokeforge/default', $commands[2]);
    }

    /**
     * Test source path strips absolute path prefix correctly.
     */
    public function test_source_path_strips_absolute_path_prefix(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'default',
            'status' => 'active',
            'configuration' => [
                'default_deployment_path' => '/home/brokeforge/deployments/default/31072025-143022',
            ],
        ]);

        $installer = new SiteSetDefaultInstaller($server);

        // Act
        $sourcePath = $this->invokeProtectedMethod($installer, 'determineSourcePath', [$site]);

        // Assert - should be relative path without /home/brokeforge/ prefix
        $this->assertStringStartsNotWith('/home/brokeforge/', $sourcePath);
        $this->assertEquals('deployments/default/31072025-143022', $sourcePath);
    }

    /**
     * Test handles site with deployment path containing brokeforge prefix.
     */
    public function test_handles_deployment_path_with_brokeforge_prefix(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
            'status' => 'active',
        ]);

        $deployment = ServerDeployment::factory()->create([
            'server_id' => $server->id,
            'server_site_id' => $site->id,
            'deployment_path' => '/home/brokeforge/deployments/example.com/08112025-100000',
            'status' => 'success',
        ]);

        $site->update(['active_deployment_id' => $deployment->id]);
        $site->refresh();

        $installer = new SiteSetDefaultInstaller($server);

        // Act
        $sourcePath = $this->invokeProtectedMethod($installer, 'determineSourcePath', [$site]);

        // Assert
        $this->assertEquals('deployments/example.com/08112025-100000', $sourcePath);
        $this->assertStringStartsNotWith('/home/', $sourcePath);
    }
}
