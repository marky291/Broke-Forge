<?php

namespace Tests\Unit\Packages\Services\Sites\Deployment;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerDeployment;
use App\Models\ServerSite;
use App\Packages\Services\Sites\Deployment\SiteGitDeploymentInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SiteGitDeploymentInstallerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test rewritePhpCommands rewrites php command at start of line.
     */
    public function test_rewrite_php_commands_rewrites_php_at_start_of_line(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new SiteGitDeploymentInstaller($server);

        // Act - use reflection to access protected method
        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('rewritePhpCommands');
        $method->setAccessible(true);

        $result = $method->invoke($installer, 'php artisan migrate', '8.3');

        // Assert
        $this->assertEquals('/usr/bin/php8.3 artisan migrate', $result);
    }

    /**
     * Test rewritePhpCommands rewrites composer command at start of line.
     */
    public function test_rewrite_php_commands_rewrites_composer_at_start_of_line(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new SiteGitDeploymentInstaller($server);

        // Act
        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('rewritePhpCommands');
        $method->setAccessible(true);

        $result = $method->invoke($installer, 'composer install --no-dev', '8.3');

        // Assert
        $this->assertEquals('/usr/bin/php8.3 /usr/local/bin/composer install --no-dev', $result);
    }

    /**
     * Test rewritePhpCommands rewrites php after && separator.
     */
    public function test_rewrite_php_commands_rewrites_php_after_ampersand_separator(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new SiteGitDeploymentInstaller($server);

        // Act
        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('rewritePhpCommands');
        $method->setAccessible(true);

        $result = $method->invoke($installer, 'cd /var/www && php artisan migrate', '8.3');

        // Assert
        $this->assertEquals('cd /var/www && /usr/bin/php8.3 artisan migrate', $result);
    }

    /**
     * Test rewritePhpCommands rewrites composer after && separator.
     */
    public function test_rewrite_php_commands_rewrites_composer_after_ampersand_separator(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new SiteGitDeploymentInstaller($server);

        // Act
        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('rewritePhpCommands');
        $method->setAccessible(true);

        $result = $method->invoke($installer, 'cd /var/www && composer install', '8.3');

        // Assert
        $this->assertEquals('cd /var/www && /usr/bin/php8.3 /usr/local/bin/composer install', $result);
    }

    /**
     * Test rewritePhpCommands rewrites php after semicolon separator.
     */
    public function test_rewrite_php_commands_rewrites_php_after_semicolon_separator(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new SiteGitDeploymentInstaller($server);

        // Act
        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('rewritePhpCommands');
        $method->setAccessible(true);

        $result = $method->invoke($installer, 'ls -la; php artisan cache:clear', '8.3');

        // Assert
        $this->assertEquals('ls -la; /usr/bin/php8.3 artisan cache:clear', $result);
    }

    /**
     * Test rewritePhpCommands uses correct PHP version.
     */
    public function test_rewrite_php_commands_uses_correct_php_version(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new SiteGitDeploymentInstaller($server);

        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('rewritePhpCommands');
        $method->setAccessible(true);

        // Act & Assert - PHP 8.1
        $result = $method->invoke($installer, 'php artisan migrate', '8.1');
        $this->assertEquals('/usr/bin/php8.1 artisan migrate', $result);

        // Act & Assert - PHP 8.4
        $result = $method->invoke($installer, 'php artisan migrate', '8.4');
        $this->assertEquals('/usr/bin/php8.4 artisan migrate', $result);
    }

    /**
     * Test rewritePhpCommands does not rewrite unrelated commands.
     */
    public function test_rewrite_php_commands_does_not_rewrite_unrelated_commands(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new SiteGitDeploymentInstaller($server);

        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('rewritePhpCommands');
        $method->setAccessible(true);

        // Act & Assert - npm command unchanged
        $result = $method->invoke($installer, 'npm install', '8.3');
        $this->assertEquals('npm install', $result);

        // Act & Assert - git command unchanged
        $result = $method->invoke($installer, 'git pull origin main', '8.3');
        $this->assertEquals('git pull origin main', $result);
    }

    /**
     * Test rewritePhpCommands handles multiple php commands in one line.
     */
    public function test_rewrite_php_commands_handles_multiple_php_commands(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new SiteGitDeploymentInstaller($server);

        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('rewritePhpCommands');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($installer, 'php artisan config:cache && php artisan route:cache', '8.3');

        // Assert
        $this->assertEquals('/usr/bin/php8.3 artisan config:cache && /usr/bin/php8.3 artisan route:cache', $result);
    }

    /**
     * Test rewritePhpCommands handles mixed php and composer commands.
     */
    public function test_rewrite_php_commands_handles_mixed_php_and_composer_commands(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new SiteGitDeploymentInstaller($server);

        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('rewritePhpCommands');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($installer, 'composer install && php artisan migrate', '8.3');

        // Assert
        $this->assertEquals('/usr/bin/php8.3 /usr/local/bin/composer install && /usr/bin/php8.3 artisan migrate', $result);
    }

    /**
     * Test rewritePhpCommands does not modify paths containing php.
     */
    public function test_rewrite_php_commands_does_not_modify_paths_containing_php(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new SiteGitDeploymentInstaller($server);

        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('rewritePhpCommands');
        $method->setAccessible(true);

        // Act - command that contains php in path but doesn't start with 'php '
        $result = $method->invoke($installer, 'cat /etc/php/8.3/fpm/php.ini', '8.3');

        // Assert - should not be modified
        $this->assertEquals('cat /etc/php/8.3/fpm/php.ini', $result);
    }

    /**
     * Test that execute re-throws exceptions after marking deployment as failed.
     */
    public function test_execute_rethrows_exception_when_install_fails(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()
            ->withGit()
            ->create(['server_id' => $server->id]);
        $deployment = ServerDeployment::factory()
            ->pending()
            ->create([
                'server_id' => $server->id,
                'server_site_id' => $site->id,
            ]);

        $installer = $this->getMockBuilder(SiteGitDeploymentInstaller::class)
            ->setConstructorArgs([$server])
            ->onlyMethods(['install'])
            ->getMock();

        $installer->method('install')
            ->willThrowException(new RuntimeException('Failed to clone repository'));

        $installer->setSite($site);

        // Assert - exception should be re-thrown
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to clone repository');

        // Act
        $installer->execute($site, $deployment);
    }

    /**
     * Test that execute marks deployment as failed when install fails.
     */
    public function test_execute_marks_deployment_as_failed_when_install_fails(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()
            ->withGit()
            ->create(['server_id' => $server->id]);
        $deployment = ServerDeployment::factory()
            ->pending()
            ->create([
                'server_id' => $server->id,
                'server_site_id' => $site->id,
            ]);

        $installer = $this->getMockBuilder(SiteGitDeploymentInstaller::class)
            ->setConstructorArgs([$server])
            ->onlyMethods(['install'])
            ->getMock();

        $installer->method('install')
            ->willThrowException(new RuntimeException('Failed to clone repository'));

        $installer->setSite($site);

        // Act - catch the exception to verify status
        try {
            $installer->execute($site, $deployment);
        } catch (RuntimeException $e) {
            // Expected
        }

        // Assert - deployment should be marked as failed
        $deployment->refresh();
        $this->assertEquals(TaskStatus::Failed, $deployment->status);
        $this->assertEquals(1, $deployment->exit_code);
        $this->assertNotNull($deployment->completed_at);
    }
}
