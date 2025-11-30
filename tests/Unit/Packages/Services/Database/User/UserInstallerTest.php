<?php

namespace Tests\Unit\Packages\Services\Database\User;

use App\Enums\DatabaseEngine;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Packages\Services\Database\User\DatabaseUserInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserInstallerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test generates create user command for MySQL.
     */
    public function test_generates_create_user_command_for_mysql(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::MySQL,
            'root_password' => 'test_password',
        ]);

        $installer = new DatabaseUserInstaller($server, $database);

        // Act - Use reflection to access protected commands() method
        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);
        $commands = $method->invoke($installer, 'app_user', 'SecurePass123', '%', 'all', ['my_schema']);

        // Assert
        $this->assertIsArray($commands);
        $this->assertStringContainsString('CREATE USER', $commands[0]);
        $this->assertStringContainsString('app_user', $commands[0]);
        $this->assertStringContainsString('SecurePass123', $commands[0]);
    }

    /**
     * Test generates grant privileges command.
     */
    public function test_generates_grant_privileges_command(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::MySQL,
            'root_password' => 'root_pass',
        ]);

        $installer = new DatabaseUserInstaller($server, $database);

        // Act
        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);
        $commands = $method->invoke($installer, 'db_user', 'password123', '%', 'all', ['test_db']);

        // Assert
        $this->assertIsArray($commands);
        // Find GRANT command (should be second command)
        $grantCommand = $commands[1];
        $this->assertStringContainsString('GRANT', $grantCommand);
        $this->assertStringContainsString('ALL PRIVILEGES', $grantCommand);
        $this->assertStringContainsString('test_db', $grantCommand);
        $this->assertStringContainsString('db_user', $grantCommand);
    }

    /**
     * Test includes flush privileges command.
     */
    public function test_includes_flush_privileges_command(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::MySQL,
            'root_password' => 'password',
        ]);

        $installer = new DatabaseUserInstaller($server, $database);

        // Act
        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);
        $commands = $method->invoke($installer, 'user1', 'pass1', '%', 'all', ['schema1']);

        // Assert
        $this->assertIsArray($commands);
        // Find FLUSH PRIVILEGES command
        $flushFound = false;
        foreach ($commands as $command) {
            if (str_contains($command, 'FLUSH PRIVILEGES')) {
                $flushFound = true;
                break;
            }
        }
        $this->assertTrue($flushFound, 'FLUSH PRIVILEGES command not found');
    }

    /**
     * Test generates PostgreSQL create user command.
     */
    public function test_generates_postgresql_create_user_command(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::PostgreSQL,
        ]);

        $installer = new DatabaseUserInstaller($server, $database);

        // Act
        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);
        $commands = $method->invoke($installer, 'pg_user', 'pgpass123', 'localhost', 'all', ['pg_schema']);

        // Assert
        $this->assertIsArray($commands);
        $this->assertStringContainsString('CREATE ROLE', $commands[0]);
        $this->assertStringContainsString('pg_user', $commands[0]);
        $this->assertStringContainsString('pgpass123', $commands[0]);
    }

    /**
     * Test grants to multiple schemas.
     */
    public function test_grants_to_multiple_schemas(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::MySQL,
            'root_password' => 'password',
        ]);

        $installer = new DatabaseUserInstaller($server, $database);

        // Act
        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);
        $commands = $method->invoke($installer, 'multi_user', 'pass', '%', 'all', ['schema1', 'schema2', 'schema3']);

        // Assert
        $this->assertIsArray($commands);

        // Count GRANT commands (should be 3, one for each schema)
        $grantCount = 0;
        foreach ($commands as $command) {
            if (str_contains($command, 'GRANT') && str_contains($command, 'multi_user')) {
                $grantCount++;
            }
        }
        $this->assertEquals(3, $grantCount, 'Should have 3 GRANT commands for 3 schemas');
    }

    /**
     * Test supports read_only privileges.
     */
    public function test_supports_read_only_privileges(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::MySQL,
            'root_password' => 'password',
        ]);

        $installer = new DatabaseUserInstaller($server, $database);

        // Act
        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);
        $commands = $method->invoke($installer, 'readonly_user', 'pass', '%', 'read_only', ['my_db']);

        // Assert
        $grantCommand = $commands[1];
        $this->assertStringContainsString('GRANT SELECT', $grantCommand);
        $this->assertStringNotContainsString('INSERT', $grantCommand);
        $this->assertStringNotContainsString('UPDATE', $grantCommand);
    }

    /**
     * Test supports read_write privileges.
     */
    public function test_supports_read_write_privileges(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::MySQL,
            'root_password' => 'password',
        ]);

        $installer = new DatabaseUserInstaller($server, $database);

        // Act
        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);
        $commands = $method->invoke($installer, 'rw_user', 'pass', '%', 'read_write', ['my_db']);

        // Assert
        $grantCommand = $commands[1];
        $this->assertStringContainsString('SELECT', $grantCommand);
        $this->assertStringContainsString('INSERT', $grantCommand);
        $this->assertStringContainsString('UPDATE', $grantCommand);
        $this->assertStringContainsString('DELETE', $grantCommand);
    }

    /**
     * Test throws exception for unsupported database type.
     */
    public function test_throws_exception_for_unsupported_database_engine(): void
    {
        // Arrange
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported database type');

        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::Redis,
        ]);

        $installer = new DatabaseUserInstaller($server, $database);

        // Act
        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        // Assert - exception should be thrown
        $method->invoke($installer, 'user', 'pass', '%', 'all', ['schema']);
    }
}
