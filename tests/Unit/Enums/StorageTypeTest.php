<?php

namespace Tests\Unit\Enums;

use App\Enums\StorageType;
use Tests\TestCase;

class StorageTypeTest extends TestCase
{
    /**
     * Test StorageType Memory has correct value.
     */
    public function test_storage_type_memory_has_correct_value(): void
    {
        // Arrange
        $storageType = StorageType::Memory;

        // Act & Assert
        $this->assertEquals('memory', $storageType->value);
    }

    /**
     * Test StorageType Disk has correct value.
     */
    public function test_storage_type_disk_has_correct_value(): void
    {
        // Arrange
        $storageType = StorageType::Disk;

        // Act & Assert
        $this->assertEquals('disk', $storageType->value);
    }

    /**
     * Test StorageType enum has exactly two cases.
     */
    public function test_storage_type_enum_has_exactly_two_cases(): void
    {
        // Act
        $cases = StorageType::cases();

        // Assert
        $this->assertCount(2, $cases);
    }

    /**
     * Test StorageType can be created from string value.
     */
    public function test_storage_type_can_be_created_from_string_value(): void
    {
        // Act
        $memory = StorageType::from('memory');
        $disk = StorageType::from('disk');

        // Assert
        $this->assertEquals(StorageType::Memory, $memory);
        $this->assertEquals(StorageType::Disk, $disk);
    }
}
