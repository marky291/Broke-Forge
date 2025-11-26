<?php

namespace Tests\Unit\Packages\Services\Sites\Framework;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Services\Sites\Framework\BaseFrameworkInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BaseFrameworkInstallerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test nginx configuration escapes double quotes properly.
     */
    public function test_nginx_configuration_escapes_double_quotes_properly(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'test.com',
            'document_root' => '/home/brokeforge/test.com/public',
            'php_version' => '8.2',
            'ssl_enabled' => false,
        ]);

        $installer = new class($server, $site->id) extends BaseFrameworkInstaller
        {
            protected function getFrameworkSteps(ServerSite $site): array
            {
                return [['name' => 'Test', 'description' => 'Test Step']];
            }

            protected function installFramework(ServerSite $site): void
            {
                // Not needed for this test
            }

            protected function getOperationName(): string
            {
                return 'test installation';
            }

            // Expose protected method for testing
            public function test_configure_nginx(ServerSite $site): array
            {
                return $this->configureNginx($site);
            }
        };

        // Act
        $commands = $installer->test_configure_nginx($site);

        // Assert
        $this->assertIsArray($commands);
        $this->assertCount(5, $commands);

        // Find the command that writes the nginx config
        $writeConfigCommand = $commands[1];

        // Verify that the command contains escaped double quotes
        $this->assertStringContainsString('bash -c', $writeConfigCommand);
        $this->assertStringContainsString('NGINX_CONFIG_EOF', $writeConfigCommand);

        // Verify that double quotes in the config are escaped with backslashes
        // The security headers in the nginx template contain double quotes that should be escaped
        $this->assertStringContainsString('\\"SAMEORIGIN\\"', $writeConfigCommand);
        $this->assertStringContainsString('\\"nosniff\\"', $writeConfigCommand);
        $this->assertStringContainsString('\\"1; mode=block\\"', $writeConfigCommand);
    }

    /**
     * Test nginx configuration commands are in correct order.
     */
    public function test_nginx_configuration_commands_in_correct_order(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'test.com',
            'document_root' => '/home/brokeforge/test.com/public',
        ]);

        $installer = new class($server, $site->id) extends BaseFrameworkInstaller
        {
            protected function getFrameworkSteps(ServerSite $site): array
            {
                return [['name' => 'Test', 'description' => 'Test Step']];
            }

            protected function installFramework(ServerSite $site): void
            {
                // Not needed for this test
            }

            protected function getOperationName(): string
            {
                return 'test installation';
            }

            public function test_configure_nginx(ServerSite $site): array
            {
                return $this->configureNginx($site);
            }
        };

        // Act
        $commands = $installer->test_configure_nginx($site);

        // Assert
        $this->assertStringContainsString('mkdir -p /var/log/nginx/', $commands[0]);
        $this->assertStringContainsString('bash -c', $commands[1]);
        $this->assertStringContainsString('ln -sf /etc/nginx/sites-available/', $commands[2]);
        $this->assertStringContainsString('nginx -t', $commands[3]);
        $this->assertStringContainsString('systemctl reload nginx', $commands[4]);
    }

    /**
     * Test nginx configuration includes correct domain.
     */
    public function test_nginx_configuration_includes_correct_domain(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
            'document_root' => '/home/brokeforge/example.com/public',
        ]);

        $installer = new class($server, $site->id) extends BaseFrameworkInstaller
        {
            protected function getFrameworkSteps(ServerSite $site): array
            {
                return [['name' => 'Test', 'description' => 'Test Step']];
            }

            protected function installFramework(ServerSite $site): void
            {
                // Not needed for this test
            }

            protected function getOperationName(): string
            {
                return 'test installation';
            }

            public function test_configure_nginx(ServerSite $site): array
            {
                return $this->configureNginx($site);
            }
        };

        // Act
        $commands = $installer->test_configure_nginx($site);

        // Assert - domain should appear in multiple commands
        $writeConfigCommand = $commands[1];
        $this->assertStringContainsString('example.com', $writeConfigCommand);
        $this->assertStringContainsString('server_name example.com', $writeConfigCommand);
    }

    /**
     * Test nginx configuration includes correct document root.
     */
    public function test_nginx_configuration_includes_correct_document_root(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $documentRoot = '/home/brokeforge/mysite/public';
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'mysite.com',
            'document_root' => $documentRoot,
        ]);

        $installer = new class($server, $site->id) extends BaseFrameworkInstaller
        {
            protected function getFrameworkSteps(ServerSite $site): array
            {
                return [['name' => 'Test', 'description' => 'Test Step']];
            }

            protected function installFramework(ServerSite $site): void
            {
                // Not needed for this test
            }

            protected function getOperationName(): string
            {
                return 'test installation';
            }

            public function test_configure_nginx(ServerSite $site): array
            {
                return $this->configureNginx($site);
            }
        };

        // Act
        $commands = $installer->test_configure_nginx($site);

        // Assert
        $writeConfigCommand = $commands[1];
        $this->assertStringContainsString($documentRoot, $writeConfigCommand);
        $this->assertStringContainsString("root {$documentRoot}", $writeConfigCommand);
    }

    /**
     * Test get error context provides helpful messages for nginx errors.
     */
    public function test_get_error_context_provides_helpful_messages_for_nginx_errors(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);

        $installer = new class($server, $site->id) extends BaseFrameworkInstaller
        {
            protected function getFrameworkSteps(ServerSite $site): array
            {
                return [['name' => 'Test', 'description' => 'Test Step']];
            }

            protected function installFramework(ServerSite $site): void
            {
                // Not needed for this test
            }

            protected function getOperationName(): string
            {
                return 'test installation';
            }

            public function test_get_error_context(string $command, string $errorOutput): ?string
            {
                return $this->getErrorContext($command, $errorOutput);
            }
        };

        // Act & Assert - nginx -t errors
        $context = $installer->test_get_error_context('sudo nginx -t', 'config test failed');
        $this->assertNotNull($context);
        $this->assertStringContainsString('Nginx configuration validation failed', $context);

        // Act & Assert - nginx config writing errors
        $context = $installer->test_get_error_context('cat > /etc/nginx/sites-available/test', '');
        $this->assertNotNull($context);
        $this->assertStringContainsString('Failed to write nginx configuration', $context);

        // Act & Assert - permission errors
        $context = $installer->test_get_error_context('some command', 'Permission denied');
        $this->assertNotNull($context);
        $this->assertStringContainsString('Permission denied', $context);

        // Act & Assert - git clone errors
        $context = $installer->test_get_error_context('git clone https://github.com/test/repo', 'failed');
        $this->assertNotNull($context);
        $this->assertStringContainsString('Failed to clone git repository', $context);
    }

    /**
     * Test nginx configuration properly escapes nginx variables.
     *
     * This test ensures that Blade variables like $uri, $query_string, etc.
     * are NOT interpreted by Blade as PHP variables but are output as literal
     * nginx variable references.
     */
    public function test_nginx_configuration_contains_literal_nginx_variables(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'test.com',
            'document_root' => '/home/brokeforge/test.com/public',
            'php_version' => '8.2',
            'ssl_enabled' => false,
        ]);

        $installer = new class($server, $site->id) extends BaseFrameworkInstaller
        {
            protected function getFrameworkSteps(ServerSite $site): array
            {
                return [['name' => 'Test', 'description' => 'Test Step']];
            }

            protected function installFramework(ServerSite $site): void
            {
                // Not needed for this test
            }

            protected function getOperationName(): string
            {
                return 'test installation';
            }

            public function test_configure_nginx(ServerSite $site): array
            {
                return $this->configureNginx($site);
            }
        };

        // Act
        $commands = $installer->test_configure_nginx($site);
        $writeConfigCommand = $commands[1];

        // Assert - nginx variables should be escaped for bash, not empty
        // Critical: try_files directive must have \$uri (escaped) to produce $uri after bash processing
        $this->assertStringContainsString('try_files \\$uri \\$uri/', $writeConfigCommand,
            'try_files directive must contain escaped $uri variables for bash');

        $this->assertStringContainsString('\\$query_string', $writeConfigCommand,
            'Nginx config must contain escaped $query_string variable for bash');

        $this->assertStringContainsString('\\$document_root', $writeConfigCommand,
            'Nginx config must contain escaped $document_root variable for bash');

        $this->assertStringContainsString('\\$fastcgi_script_name', $writeConfigCommand,
            'Nginx config must contain escaped $fastcgi_script_name variable for bash');

        // Also verify that try_files specifically has the correct syntax on line ~21 (PHP location block)
        $this->assertStringContainsString('try_files \\$uri =404', $writeConfigCommand,
            'PHP location block must have correct try_files syntax with escaped $uri');
    }

    /**
     * Test failed method records correct status.
     */
    public function test_failed_method_records_correct_status(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Installing->value,
        ]);

        $installer = new class($server, $site->id) extends BaseFrameworkInstaller
        {
            protected function getFrameworkSteps(ServerSite $site): array
            {
                return [['name' => 'Test', 'description' => 'Test Step']];
            }

            protected function installFramework(ServerSite $site): void
            {
                // Not needed for this test
            }

            protected function getOperationName(): string
            {
                return 'test installation';
            }
        };

        $exception = new \Exception('Test failure');

        // Act
        $installer->failed($exception);

        // Assert
        $site->refresh();
        $this->assertEquals(TaskStatus::Failed->value, $site->status);
        $this->assertEquals('Test failure', $site->error_log);
    }

    /**
     * Test error context provides helpful messages for Laravel migration failures.
     */
    public function test_get_error_context_provides_helpful_messages_for_migration_failures(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);

        $installer = new class($server, $site->id) extends BaseFrameworkInstaller
        {
            protected function getFrameworkSteps(ServerSite $site): array
            {
                return [['name' => 'Test', 'description' => 'Test Step']];
            }

            protected function installFramework(ServerSite $site): void
            {
                // Not needed for this test
            }

            protected function getOperationName(): string
            {
                return 'test installation';
            }

            public function test_get_error_context(string $command, string $errorOutput): ?string
            {
                return $this->getErrorContext($command, $errorOutput);
            }
        };

        // Act & Assert - migration errors
        $context = $installer->test_get_error_context(
            "cd '/home/brokeforge/deployments/test.com/123' && php artisan migrate --force",
            ''
        );
        $this->assertNotNull($context);
        $this->assertStringContainsString('Migration failed', $context);
        $this->assertStringContainsString('database connection issues', $context);

        // Act & Assert - composer install errors
        $context = $installer->test_get_error_context('composer install --no-dev', 'some error');
        $this->assertNotNull($context);
        $this->assertStringContainsString('Composer install failed', $context);
    }

    /**
     * Test getPhpBinaryPath returns correct path for site's PHP version.
     */
    public function test_get_php_binary_path_returns_correct_path_for_php_version(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'php_version' => '8.3',
        ]);

        $installer = new class($server, $site->id) extends BaseFrameworkInstaller
        {
            protected function getFrameworkSteps(ServerSite $site): array
            {
                return [['name' => 'Test', 'description' => 'Test Step']];
            }

            protected function installFramework(ServerSite $site): void
            {
                // Not needed for this test
            }

            protected function getOperationName(): string
            {
                return 'test installation';
            }

            public function test_get_php_binary_path(ServerSite $site): string
            {
                return $this->getPhpBinaryPath($site);
            }
        };

        // Act
        $phpPath = $installer->test_get_php_binary_path($site);

        // Assert
        $this->assertEquals('/usr/bin/php8.3', $phpPath);
    }

    /**
     * Test getPhpBinaryPath works with different PHP versions.
     */
    public function test_get_php_binary_path_works_with_different_php_versions(): void
    {
        // Arrange
        $server = Server::factory()->create();

        $installer = new class($server, 1) extends BaseFrameworkInstaller
        {
            protected function getFrameworkSteps(ServerSite $site): array
            {
                return [['name' => 'Test', 'description' => 'Test Step']];
            }

            protected function installFramework(ServerSite $site): void
            {
                // Not needed for this test
            }

            protected function getOperationName(): string
            {
                return 'test installation';
            }

            public function test_get_php_binary_path(ServerSite $site): string
            {
                return $this->getPhpBinaryPath($site);
            }
        };

        // Test PHP 8.1
        $site81 = ServerSite::factory()->create([
            'server_id' => $server->id,
            'php_version' => '8.1',
        ]);
        $this->assertEquals('/usr/bin/php8.1', $installer->test_get_php_binary_path($site81));

        // Test PHP 8.2
        $site82 = ServerSite::factory()->create([
            'server_id' => $server->id,
            'php_version' => '8.2',
        ]);
        $this->assertEquals('/usr/bin/php8.2', $installer->test_get_php_binary_path($site82));

        // Test PHP 8.4
        $site84 = ServerSite::factory()->create([
            'server_id' => $server->id,
            'php_version' => '8.4',
        ]);
        $this->assertEquals('/usr/bin/php8.4', $installer->test_get_php_binary_path($site84));
    }

    /**
     * Test getComposerCommand returns command with site's PHP version.
     */
    public function test_get_composer_command_returns_command_with_php_version(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'php_version' => '8.3',
        ]);

        $installer = new class($server, $site->id) extends BaseFrameworkInstaller
        {
            protected function getFrameworkSteps(ServerSite $site): array
            {
                return [['name' => 'Test', 'description' => 'Test Step']];
            }

            protected function installFramework(ServerSite $site): void
            {
                // Not needed for this test
            }

            protected function getOperationName(): string
            {
                return 'test installation';
            }

            public function test_get_composer_command(ServerSite $site): string
            {
                return $this->getComposerCommand($site);
            }
        };

        // Act
        $composerCommand = $installer->test_get_composer_command($site);

        // Assert
        $this->assertEquals('/usr/bin/php8.3 /usr/local/bin/composer', $composerCommand);
    }

    /**
     * Test getComposerCommand uses correct PHP binary for different versions.
     */
    public function test_get_composer_command_uses_correct_php_binary_for_different_versions(): void
    {
        // Arrange
        $server = Server::factory()->create();

        $installer = new class($server, 1) extends BaseFrameworkInstaller
        {
            protected function getFrameworkSteps(ServerSite $site): array
            {
                return [['name' => 'Test', 'description' => 'Test Step']];
            }

            protected function installFramework(ServerSite $site): void
            {
                // Not needed for this test
            }

            protected function getOperationName(): string
            {
                return 'test installation';
            }

            public function test_get_composer_command(ServerSite $site): string
            {
                return $this->getComposerCommand($site);
            }
        };

        // Test PHP 8.1
        $site81 = ServerSite::factory()->create([
            'server_id' => $server->id,
            'php_version' => '8.1',
        ]);
        $this->assertEquals('/usr/bin/php8.1 /usr/local/bin/composer', $installer->test_get_composer_command($site81));

        // Test PHP 8.4
        $site84 = ServerSite::factory()->create([
            'server_id' => $server->id,
            'php_version' => '8.4',
        ]);
        $this->assertEquals('/usr/bin/php8.4 /usr/local/bin/composer', $installer->test_get_composer_command($site84));
    }

    /**
     * Test createSharedSymlinks removes existing directories before creating symlinks.
     *
     * This is critical because git clone creates a storage/ directory from the
     * repository, and ln -sfn will create a symlink INSIDE an existing directory
     * rather than replacing it.
     */
    public function test_create_shared_symlinks_removes_existing_directories_before_symlink(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);

        $installer = new class($server, $site->id) extends BaseFrameworkInstaller
        {
            protected function getFrameworkSteps(ServerSite $site): array
            {
                return [['name' => 'Test', 'description' => 'Test Step']];
            }

            protected function installFramework(ServerSite $site): void
            {
                // Not needed for this test
            }

            protected function getOperationName(): string
            {
                return 'test installation';
            }

            public function test_create_shared_symlinks(string $deploymentPath): array
            {
                return $this->createSharedSymlinks($deploymentPath);
            }
        };

        // Act
        $commands = $installer->test_create_shared_symlinks('/home/brokeforge/deployments/test.com/26112024-123456');

        // Assert - storage must be removed before symlink
        $this->assertCount(4, $commands);
        $this->assertStringContainsString('rm -rf', $commands[0]);
        $this->assertStringContainsString('/storage', $commands[0]);
        $this->assertStringContainsString('ln -sfn ../shared/storage', $commands[0]);

        // Assert - .env uses rm -f (file, not directory)
        $this->assertStringContainsString('rm -f', $commands[1]);
        $this->assertStringContainsString('/.env', $commands[1]);
        $this->assertStringContainsString('ln -sfn ../shared/.env', $commands[1]);

        // Assert - vendor must be removed before symlink
        $this->assertStringContainsString('rm -rf', $commands[2]);
        $this->assertStringContainsString('/vendor', $commands[2]);
        $this->assertStringContainsString('ln -sfn ../shared/vendor', $commands[2]);

        // Assert - node_modules must be removed before symlink
        $this->assertStringContainsString('rm -rf', $commands[3]);
        $this->assertStringContainsString('/node_modules', $commands[3]);
        $this->assertStringContainsString('ln -sfn ../shared/node_modules', $commands[3]);
    }

    /**
     * Test createSharedSymlinks uses correct deployment path in commands.
     */
    public function test_create_shared_symlinks_uses_correct_deployment_path(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);
        $deploymentPath = '/home/brokeforge/deployments/example.com/26112024-150000';

        $installer = new class($server, $site->id) extends BaseFrameworkInstaller
        {
            protected function getFrameworkSteps(ServerSite $site): array
            {
                return [['name' => 'Test', 'description' => 'Test Step']];
            }

            protected function installFramework(ServerSite $site): void
            {
                // Not needed for this test
            }

            protected function getOperationName(): string
            {
                return 'test installation';
            }

            public function test_create_shared_symlinks(string $deploymentPath): array
            {
                return $this->createSharedSymlinks($deploymentPath);
            }
        };

        // Act
        $commands = $installer->test_create_shared_symlinks($deploymentPath);

        // Assert - all commands should reference the deployment path
        foreach ($commands as $command) {
            $this->assertStringContainsString($deploymentPath, $command);
        }
    }
}
