<?php

namespace Tests\Unit\Services;

use App\Enums\DatabaseType;
use App\Services\DatabaseConfigurationService;
use Tests\TestCase;

class DatabaseConfigurationServiceTest extends TestCase
{
    /**
     * Test getAvailableTypes returns all database types.
     */
    public function test_get_available_types_returns_all_database_types(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $types = $service->getAvailableTypes();

        // Assert
        $this->assertIsArray($types);
        $this->assertArrayHasKey('mysql', $types);
        $this->assertArrayHasKey('mariadb', $types);
        $this->assertArrayHasKey('postgresql', $types);
    }

    /**
     * Test getAvailableTypes returns correct MySQL configuration.
     */
    public function test_get_available_types_returns_correct_mysql_configuration(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $types = $service->getAvailableTypes();
        $mysql = $types['mysql'];

        // Assert
        $this->assertEquals('MySQL', $mysql['name']);
        $this->assertArrayHasKey('description', $mysql);
        $this->assertArrayHasKey('versions', $mysql);
        $this->assertEquals('8.0', $mysql['default_version']);
        $this->assertEquals(3306, $mysql['default_port']);
    }

    /**
     * Test getAvailableTypes returns correct MariaDB configuration.
     */
    public function test_get_available_types_returns_correct_mariadb_configuration(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $types = $service->getAvailableTypes();
        $mariadb = $types['mariadb'];

        // Assert
        $this->assertEquals('MariaDB', $mariadb['name']);
        $this->assertArrayHasKey('description', $mariadb);
        $this->assertArrayHasKey('versions', $mariadb);
        $this->assertEquals('11.4', $mariadb['default_version']);
        $this->assertEquals(3306, $mariadb['default_port']);
    }

    /**
     * Test getAvailableTypes returns correct PostgreSQL configuration.
     */
    public function test_get_available_types_returns_correct_postgresql_configuration(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $types = $service->getAvailableTypes();
        $postgresql = $types['postgresql'];

        // Assert
        $this->assertEquals('PostgreSQL', $postgresql['name']);
        $this->assertArrayHasKey('description', $postgresql);
        $this->assertArrayHasKey('versions', $postgresql);
        $this->assertEquals('16', $postgresql['default_version']);
        $this->assertEquals(5432, $postgresql['default_port']);
    }

    /**
     * Test getTypeConfiguration returns MySQL configuration.
     */
    public function test_get_type_configuration_returns_mysql_configuration(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $config = $service->getTypeConfiguration(DatabaseType::MySQL);

        // Assert
        $this->assertIsArray($config);
        $this->assertEquals('MySQL', $config['name']);
        $this->assertEquals(3306, $config['default_port']);
        $this->assertEquals('8.0', $config['default_version']);
    }

    /**
     * Test getTypeConfiguration returns MariaDB configuration.
     */
    public function test_get_type_configuration_returns_mariadb_configuration(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $config = $service->getTypeConfiguration(DatabaseType::MariaDB);

        // Assert
        $this->assertIsArray($config);
        $this->assertEquals('MariaDB', $config['name']);
        $this->assertEquals(3306, $config['default_port']);
        $this->assertEquals('11.4', $config['default_version']);
    }

    /**
     * Test getTypeConfiguration returns PostgreSQL configuration.
     */
    public function test_get_type_configuration_returns_postgresql_configuration(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $config = $service->getTypeConfiguration(DatabaseType::PostgreSQL);

        // Assert
        $this->assertIsArray($config);
        $this->assertEquals('PostgreSQL', $config['name']);
        $this->assertEquals(5432, $config['default_port']);
        $this->assertEquals('16', $config['default_version']);
    }

    /**
     * Test getDefaultPort returns correct port for MySQL.
     */
    public function test_get_default_port_returns_correct_port_for_mysql(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $port = $service->getDefaultPort(DatabaseType::MySQL);

        // Assert
        $this->assertEquals(3306, $port);
    }

    /**
     * Test getDefaultPort returns correct port for MariaDB.
     */
    public function test_get_default_port_returns_correct_port_for_mariadb(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $port = $service->getDefaultPort(DatabaseType::MariaDB);

        // Assert
        $this->assertEquals(3306, $port);
    }

    /**
     * Test getDefaultPort returns correct port for PostgreSQL.
     */
    public function test_get_default_port_returns_correct_port_for_postgresql(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $port = $service->getDefaultPort(DatabaseType::PostgreSQL);

        // Assert
        $this->assertEquals(5432, $port);
    }

    /**
     * Test getDefaultVersion returns correct version for MySQL.
     */
    public function test_get_default_version_returns_correct_version_for_mysql(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $version = $service->getDefaultVersion(DatabaseType::MySQL);

        // Assert
        $this->assertEquals('8.0', $version);
    }

    /**
     * Test getDefaultVersion returns correct version for MariaDB.
     */
    public function test_get_default_version_returns_correct_version_for_mariadb(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $version = $service->getDefaultVersion(DatabaseType::MariaDB);

        // Assert
        $this->assertEquals('11.4', $version);
    }

    /**
     * Test getDefaultVersion returns correct version for PostgreSQL.
     */
    public function test_get_default_version_returns_correct_version_for_postgresql(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $version = $service->getDefaultVersion(DatabaseType::PostgreSQL);

        // Assert
        $this->assertEquals('16', $version);
    }

    /**
     * Test MySQL and MariaDB use same default port.
     */
    public function test_mysql_and_mariadb_use_same_default_port(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $mysqlPort = $service->getDefaultPort(DatabaseType::MySQL);
        $mariadbPort = $service->getDefaultPort(DatabaseType::MariaDB);

        // Assert
        $this->assertEquals($mysqlPort, $mariadbPort);
        $this->assertEquals(3306, $mysqlPort);
    }

    /**
     * Test PostgreSQL uses different port than MySQL/MariaDB.
     */
    public function test_postgresql_uses_different_port_than_mysql_mariadb(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $mysqlPort = $service->getDefaultPort(DatabaseType::MySQL);
        $postgresqlPort = $service->getDefaultPort(DatabaseType::PostgreSQL);

        // Assert
        $this->assertNotEquals($mysqlPort, $postgresqlPort);
        $this->assertEquals(3306, $mysqlPort);
        $this->assertEquals(5432, $postgresqlPort);
    }

    /**
     * Test all database types have required configuration keys.
     */
    public function test_all_database_types_have_required_configuration_keys(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;
        $requiredKeys = ['name', 'description', 'versions', 'default_version', 'default_port'];

        // Act
        $types = $service->getAvailableTypes();

        // Assert
        foreach ($types as $type => $config) {
            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey($key, $config, "Database type '{$type}' is missing required key '{$key}'");
            }
        }
    }

    /**
     * Test all database types have at least one version.
     */
    public function test_all_database_types_have_at_least_one_version(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $types = $service->getAvailableTypes();

        // Assert
        foreach ($types as $type => $config) {
            $this->assertIsArray($config['versions']);
            $this->assertNotEmpty($config['versions'], "Database type '{$type}' has no versions defined");
        }
    }

    /**
     * Test all default versions exist in versions array.
     */
    public function test_all_default_versions_exist_in_versions_array(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $types = $service->getAvailableTypes();

        // Assert
        foreach ($types as $type => $config) {
            $defaultVersion = $config['default_version'];
            $this->assertArrayHasKey($defaultVersion, $config['versions'], "Default version '{$defaultVersion}' for '{$type}' not found in versions array");
        }
    }

    /**
     * Test MariaDB has multiple LTS versions.
     */
    public function test_mariadb_has_multiple_lts_versions(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $config = $service->getTypeConfiguration(DatabaseType::MariaDB);

        // Assert
        $this->assertIsArray($config['versions']);
        $this->assertGreaterThanOrEqual(2, count($config['versions']));
        $this->assertArrayHasKey('11.4', $config['versions']);
        $this->assertArrayHasKey('10.11', $config['versions']);
    }

    /**
     * Test getAvailableDatabases only returns actual databases (not cache/queue).
     */
    public function test_get_available_databases_only_returns_actual_databases(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $databases = $service->getAvailableDatabases();

        // Assert
        $this->assertArrayHasKey('mysql', $databases);
        $this->assertArrayHasKey('mariadb', $databases);
        $this->assertArrayHasKey('postgresql', $databases);
        $this->assertArrayNotHasKey('redis', $databases);
    }

    /**
     * Test getAvailableCacheQueue only returns cache/queue services.
     */
    public function test_get_available_cache_queue_only_returns_cache_queue_services(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $cacheQueue = $service->getAvailableCacheQueue();

        // Assert
        $this->assertArrayHasKey('redis', $cacheQueue);
        $this->assertArrayNotHasKey('mysql', $cacheQueue);
        $this->assertArrayNotHasKey('mariadb', $cacheQueue);
        $this->assertArrayNotHasKey('postgresql', $cacheQueue);
    }

    /**
     * Test getAvailableCacheQueue returns correct Redis configuration.
     */
    public function test_get_available_cache_queue_returns_correct_redis_configuration(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $cacheQueue = $service->getAvailableCacheQueue();
        $redis = $cacheQueue['redis'];

        // Assert
        $this->assertEquals('Redis', $redis['name']);
        $this->assertArrayHasKey('description', $redis);
        $this->assertArrayHasKey('versions', $redis);
        $this->assertEquals('7.2', $redis['default_version']);
        $this->assertEquals(6379, $redis['default_port']);
    }

    /**
     * Test getAvailableTypes combines databases and cache/queue.
     */
    public function test_get_available_types_combines_databases_and_cache_queue(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $databases = $service->getAvailableDatabases();
        $cacheQueue = $service->getAvailableCacheQueue();
        $allTypes = $service->getAvailableTypes();

        // Assert
        $this->assertCount(count($databases) + count($cacheQueue), $allTypes);
        $this->assertArrayHasKey('mysql', $allTypes);
        $this->assertArrayHasKey('mariadb', $allTypes);
        $this->assertArrayHasKey('postgresql', $allTypes);
        $this->assertArrayHasKey('redis', $allTypes);
    }

    /**
     * Test databases and cache/queue services are mutually exclusive.
     */
    public function test_databases_and_cache_queue_are_mutually_exclusive(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $databases = $service->getAvailableDatabases();
        $cacheQueue = $service->getAvailableCacheQueue();

        // Assert - No overlap between the two sets
        foreach (array_keys($databases) as $dbType) {
            $this->assertArrayNotHasKey($dbType, $cacheQueue, "Type '{$dbType}' should not appear in both databases and cache/queue");
        }

        foreach (array_keys($cacheQueue) as $cqType) {
            $this->assertArrayNotHasKey($cqType, $databases, "Type '{$cqType}' should not appear in both databases and cache/queue");
        }
    }

    /**
     * Test Redis type configuration is accessible via getTypeConfiguration.
     */
    public function test_get_type_configuration_returns_redis_configuration(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $config = $service->getTypeConfiguration(DatabaseType::Redis);

        // Assert
        $this->assertIsArray($config);
        $this->assertEquals('Redis', $config['name']);
        $this->assertEquals(6379, $config['default_port']);
        $this->assertEquals('7.2', $config['default_version']);
    }

    /**
     * Test getDefaultPort returns correct port for Redis.
     */
    public function test_get_default_port_returns_correct_port_for_redis(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $port = $service->getDefaultPort(DatabaseType::Redis);

        // Assert
        $this->assertEquals(6379, $port);
    }

    /**
     * Test getDefaultVersion returns correct version for Redis.
     */
    public function test_get_default_version_returns_correct_version_for_redis(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $version = $service->getDefaultVersion(DatabaseType::Redis);

        // Assert
        $this->assertEquals('7.2', $version);
    }

    /**
     * Test isDatabaseCategory returns true for MySQL.
     */
    public function test_is_database_category_returns_true_for_mysql(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $result = $service->isDatabaseCategory(DatabaseType::MySQL);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isDatabaseCategory returns true for MariaDB.
     */
    public function test_is_database_category_returns_true_for_mariadb(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $result = $service->isDatabaseCategory(DatabaseType::MariaDB);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isDatabaseCategory returns true for PostgreSQL.
     */
    public function test_is_database_category_returns_true_for_postgresql(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $result = $service->isDatabaseCategory(DatabaseType::PostgreSQL);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isDatabaseCategory returns true for MongoDB.
     */
    public function test_is_database_category_returns_true_for_mongodb(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $result = $service->isDatabaseCategory(DatabaseType::MongoDB);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isDatabaseCategory returns false for Redis.
     */
    public function test_is_database_category_returns_false_for_redis(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;

        // Act
        $result = $service->isDatabaseCategory(DatabaseType::Redis);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test hasExistingDatabaseInCategory detects MySQL.
     */
    public function test_has_existing_database_in_category_detects_mysql(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;
        $server = \App\Models\Server::factory()->create();
        $server->databases()->create([
            'name' => 'test-mysql',
            'type' => 'mysql',
            'version' => '8.0',
            'port' => 3306,
            'status' => 'active',
            'root_password' => 'password123',
        ]);

        // Act
        $result = $service->hasExistingDatabaseInCategory($server, DatabaseType::MariaDB);

        // Assert - Should detect MySQL when checking for MariaDB (same category)
        $this->assertTrue($result);
    }

    /**
     * Test hasExistingDatabaseInCategory ignores different category.
     */
    public function test_has_existing_database_in_category_ignores_different_category(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;
        $server = \App\Models\Server::factory()->create();
        $server->databases()->create([
            'name' => 'test-redis',
            'type' => 'redis',
            'version' => '7.2',
            'port' => 6379,
            'status' => 'active',
            'root_password' => 'password123',
        ]);

        // Act
        $result = $service->hasExistingDatabaseInCategory($server, DatabaseType::MySQL);

        // Assert - Should not detect Redis when checking for MySQL (different category)
        $this->assertFalse($result);
    }

    /**
     * Test hasExistingDatabaseInCategory ignores failed installations.
     */
    public function test_has_existing_database_in_category_ignores_failed_installations(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;
        $server = \App\Models\Server::factory()->create();
        $server->databases()->create([
            'name' => 'test-mysql-failed',
            'type' => 'mysql',
            'version' => '8.0',
            'port' => 3306,
            'status' => 'failed',
            'root_password' => 'password123',
        ]);

        // Act
        $result = $service->hasExistingDatabaseInCategory($server, DatabaseType::MySQL);

        // Assert - Should ignore failed installations
        $this->assertFalse($result);
    }

    /**
     * Test hasExistingDatabaseInCategory ignores uninstalling databases.
     */
    public function test_has_existing_database_in_category_ignores_uninstalling_databases(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;
        $server = \App\Models\Server::factory()->create();
        $server->databases()->create([
            'name' => 'test-mysql-uninstalling',
            'type' => 'mysql',
            'version' => '8.0',
            'port' => 3306,
            'status' => 'removing',
            'root_password' => 'password123',
        ]);

        // Act
        $result = $service->hasExistingDatabaseInCategory($server, DatabaseType::MySQL);

        // Assert - Should ignore uninstalling databases
        $this->assertFalse($result);
    }

    /**
     * Test hasExistingDatabaseInCategory detects pending installations.
     */
    public function test_has_existing_database_in_category_detects_pending_installations(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;
        $server = \App\Models\Server::factory()->create();
        $server->databases()->create([
            'name' => 'test-mysql-pending',
            'type' => 'mysql',
            'version' => '8.0',
            'port' => 3306,
            'status' => 'pending',
            'root_password' => 'password123',
        ]);

        // Act
        $result = $service->hasExistingDatabaseInCategory($server, DatabaseType::MySQL);

        // Assert - Should detect pending installations
        $this->assertTrue($result);
    }

    /**
     * Test hasExistingDatabaseInCategory detects installing databases.
     */
    public function test_has_existing_database_in_category_detects_installing_databases(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;
        $server = \App\Models\Server::factory()->create();
        $server->databases()->create([
            'name' => 'test-mysql-installing',
            'type' => 'mysql',
            'version' => '8.0',
            'port' => 3306,
            'status' => 'installing',
            'root_password' => 'password123',
        ]);

        // Act
        $result = $service->hasExistingDatabaseInCategory($server, DatabaseType::MySQL);

        // Assert - Should detect installing databases
        $this->assertTrue($result);
    }

    /**
     * Test hasExistingDatabaseInCategory returns false for empty server.
     */
    public function test_has_existing_database_in_category_returns_false_for_empty_server(): void
    {
        // Arrange
        $service = new DatabaseConfigurationService;
        $server = \App\Models\Server::factory()->create();

        // Act
        $result = $service->hasExistingDatabaseInCategory($server, DatabaseType::MySQL);

        // Assert
        $this->assertFalse($result);
    }
}
