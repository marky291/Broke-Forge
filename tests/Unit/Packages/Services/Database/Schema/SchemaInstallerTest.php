<?php

namespace Tests\Unit\Packages\Services\Database\Schema;

use App\Enums\DatabaseType;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Packages\Services\Database\Schema\DatabaseSchemaInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaInstallerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test generates create database command for MySQL.
     */
    public function test_generates_create_database_command_for_mysql(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'root_password' => 'test_password',
        ]);

        $installer = new DatabaseSchemaInstaller($server, $database);

        // Act - Use reflection to access protected commands() method
        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);
        $commands = $method->invoke($installer, 'test_schema', 'utf8mb4', 'utf8mb4_unicode_ci');

        // Assert
        $this->assertIsArray($commands);
        $this->assertStringContainsString('CREATE DATABASE', $commands[0]);
        $this->assertStringContainsString('test_schema', $commands[0]);
        $this->assertStringContainsString('CHARACTER SET utf8mb4', $commands[0]);
        $this->assertStringContainsString('COLLATE utf8mb4_unicode_ci', $commands[0]);
    }

    /**
     * Test generates create database command for MariaDB.
     */
    public function test_generates_create_database_command_for_mariadb(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MariaDB,
            'root_password' => 'test_password',
        ]);

        $installer = new DatabaseSchemaInstaller($server, $database);

        // Act - Use reflection to access protected commands() method
        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);
        $commands = $method->invoke($installer, 'mariadb_schema', 'utf8mb4', 'utf8mb4_unicode_ci');

        // Assert
        $this->assertIsArray($commands);
        $this->assertStringContainsString('CREATE DATABASE', $commands[0]);
        $this->assertStringContainsString('mariadb_schema', $commands[0]);
        $this->assertStringContainsString('CHARACTER SET utf8mb4', $commands[0]);
        $this->assertStringContainsString('COLLATE utf8mb4_unicode_ci', $commands[0]);
    }

    /**
     * Test generates create database command for PostgreSQL.
     */
    public function test_generates_create_database_command_for_postgresql(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::PostgreSQL,
        ]);

        $installer = new DatabaseSchemaInstaller($server, $database);

        // Act - Use reflection to access protected commands() method
        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);
        $commands = $method->invoke($installer, 'postgres_db', 'utf8mb4', 'utf8mb4_unicode_ci');

        // Assert
        $this->assertIsArray($commands);
        $this->assertStringContainsString('CREATE DATABASE', $commands[0]);
        $this->assertStringContainsString('postgres_db', $commands[0]);
        $this->assertStringContainsString("ENCODING 'UTF8'", $commands[0]);
    }

    /**
     * Test uses utf8mb4 character set.
     */
    public function test_uses_utf8mb4_character_set(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'root_password' => 'password',
        ]);

        $installer = new DatabaseSchemaInstaller($server, $database);

        // Act - Use reflection to access protected commands() method
        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);
        $commands = $method->invoke($installer, 'my_db', 'utf8mb4', 'utf8mb4_unicode_ci');

        // Assert
        $this->assertStringContainsString('utf8mb4', $commands[0]);
    }

    /**
     * Test throws exception for unsupported database type.
     */
    public function test_throws_exception_for_unsupported_database_type(): void
    {
        // Arrange
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported database type');

        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::Redis, // Unsupported for schema creation
        ]);

        $installer = new DatabaseSchemaInstaller($server, $database);

        // Act - Use reflection to access protected commands() method
        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        // Assert - exception should be thrown
        $method->invoke($installer, 'test_db', 'utf8mb4', 'utf8mb4_unicode_ci');
    }
}
