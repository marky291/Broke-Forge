<?php

namespace Tests\Unit;

use App\Enums\DatabaseType;
use App\Services\DatabaseConfigurationService;
use PHPUnit\Framework\TestCase;

class DatabaseConfigurationServiceTest extends TestCase
{
    public function test_available_types_include_mysql_mariadb_and_postgresql(): void
    {
        $service = new DatabaseConfigurationService;

        $types = $service->getAvailableTypes();

        $this->assertArrayHasKey(DatabaseType::MySQL->value, $types);
        $this->assertArrayHasKey(DatabaseType::MariaDB->value, $types);
        $this->assertArrayHasKey(DatabaseType::PostgreSQL->value, $types);

        $this->assertSame('8.0', $types[DatabaseType::MySQL->value]['default_version']);
        $this->assertSame(3306, $types[DatabaseType::MySQL->value]['default_port']);

        $this->assertSame('11.4', $types[DatabaseType::MariaDB->value]['default_version']);
        $this->assertSame(3306, $types[DatabaseType::MariaDB->value]['default_port']);

        $this->assertSame('16', $types[DatabaseType::PostgreSQL->value]['default_version']);
        $this->assertSame(5432, $types[DatabaseType::PostgreSQL->value]['default_port']);
    }

    public function test_default_helpers_leverage_type_configuration(): void
    {
        $service = new DatabaseConfigurationService;

        $this->assertSame(3306, $service->getDefaultPort(DatabaseType::MySQL));
        $this->assertSame('16', $service->getDefaultVersion(DatabaseType::PostgreSQL));
    }
}
