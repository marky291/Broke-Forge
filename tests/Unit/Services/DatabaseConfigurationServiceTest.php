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
}
