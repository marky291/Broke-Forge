<?php

namespace Tests\Unit\Enums;

use App\Enums\DatabaseEngine;
use App\Enums\StorageType;
use Tests\TestCase;

class DatabaseEngineTest extends TestCase
{
    /**
     * Test MySQL returns disk storage type.
     */
    public function test_mysql_returns_disk_storage_type(): void
    {
        // Arrange
        $engine = DatabaseEngine::MySQL;

        // Act
        $storageType = $engine->storageType();

        // Assert
        $this->assertEquals(StorageType::Disk, $storageType);
    }

    /**
     * Test MariaDB returns disk storage type.
     */
    public function test_mariadb_returns_disk_storage_type(): void
    {
        // Arrange
        $engine = DatabaseEngine::MariaDB;

        // Act
        $storageType = $engine->storageType();

        // Assert
        $this->assertEquals(StorageType::Disk, $storageType);
    }

    /**
     * Test PostgreSQL returns disk storage type.
     */
    public function test_postgresql_returns_disk_storage_type(): void
    {
        // Arrange
        $engine = DatabaseEngine::PostgreSQL;

        // Act
        $storageType = $engine->storageType();

        // Assert
        $this->assertEquals(StorageType::Disk, $storageType);
    }

    /**
     * Test MongoDB returns disk storage type.
     */
    public function test_mongodb_returns_disk_storage_type(): void
    {
        // Arrange
        $engine = DatabaseEngine::MongoDB;

        // Act
        $storageType = $engine->storageType();

        // Assert
        $this->assertEquals(StorageType::Disk, $storageType);
    }

    /**
     * Test Redis returns memory storage type.
     */
    public function test_redis_returns_memory_storage_type(): void
    {
        // Arrange
        $engine = DatabaseEngine::Redis;

        // Act
        $storageType = $engine->storageType();

        // Assert
        $this->assertEquals(StorageType::Memory, $storageType);
    }

    /**
     * Test all database engines have a storage type.
     */
    public function test_all_database_engines_have_storage_type(): void
    {
        // Arrange & Act & Assert
        foreach (DatabaseEngine::cases() as $engine) {
            $storageType = $engine->storageType();

            $this->assertInstanceOf(
                StorageType::class,
                $storageType,
                "{$engine->value} should return a StorageType instance"
            );
        }
    }

    /**
     * Test MySQL has correct value.
     */
    public function test_mysql_has_correct_value(): void
    {
        $this->assertEquals('mysql', DatabaseEngine::MySQL->value);
    }

    /**
     * Test MariaDB has correct value.
     */
    public function test_mariadb_has_correct_value(): void
    {
        $this->assertEquals('mariadb', DatabaseEngine::MariaDB->value);
    }

    /**
     * Test PostgreSQL has correct value.
     */
    public function test_postgresql_has_correct_value(): void
    {
        $this->assertEquals('postgresql', DatabaseEngine::PostgreSQL->value);
    }

    /**
     * Test MongoDB has correct value.
     */
    public function test_mongodb_has_correct_value(): void
    {
        $this->assertEquals('mongodb', DatabaseEngine::MongoDB->value);
    }

    /**
     * Test Redis has correct value.
     */
    public function test_redis_has_correct_value(): void
    {
        $this->assertEquals('redis', DatabaseEngine::Redis->value);
    }

    /**
     * Test enum has exactly five cases.
     */
    public function test_enum_has_exactly_five_cases(): void
    {
        $this->assertCount(5, DatabaseEngine::cases());
    }
}
