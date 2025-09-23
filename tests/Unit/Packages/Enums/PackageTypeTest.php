<?php

namespace Tests\Unit\Packages\Enums;

use App\Packages\Enums\PackageType;
use Tests\TestCase;

class PackageTypeTest extends TestCase
{
    public function test_package_type_enum_values(): void
    {
        $this->assertEquals('webserver', PackageType::WebServer->value);
        $this->assertEquals('database', PackageType::DatabaseServer->value);
        $this->assertEquals('application', PackageType::ApplicationServer->value);
        $this->assertEquals('cache', PackageType::CacheServer->value);
        $this->assertEquals('queue', PackageType::QueueWorker->value);
    }

    public function test_package_type_labels(): void
    {
        $this->assertEquals('Web Server', PackageType::WebServer->label());
        $this->assertEquals('Database Server', PackageType::DatabaseServer->label());
        $this->assertEquals('Application Server', PackageType::ApplicationServer->label());
        $this->assertEquals('Cache Server', PackageType::CacheServer->label());
        $this->assertEquals('Queue Worker', PackageType::QueueWorker->label());
    }

    public function test_package_type_from_string(): void
    {
        $this->assertEquals(PackageType::WebServer, PackageType::from('webserver'));
        $this->assertEquals(PackageType::DatabaseServer, PackageType::from('database'));
        $this->assertEquals(PackageType::ApplicationServer, PackageType::from('application'));
        $this->assertEquals(PackageType::CacheServer, PackageType::from('cache'));
        $this->assertEquals(PackageType::QueueWorker, PackageType::from('queue'));
    }

    public function test_package_type_try_from_string(): void
    {
        $this->assertEquals(PackageType::WebServer, PackageType::tryFrom('webserver'));
        $this->assertNull(PackageType::tryFrom('invalid_type'));
    }

    public function test_package_type_cases(): void
    {
        $cases = PackageType::cases();

        $this->assertCount(5, $cases);
        $this->assertContains(PackageType::WebServer, $cases);
        $this->assertContains(PackageType::DatabaseServer, $cases);
        $this->assertContains(PackageType::ApplicationServer, $cases);
        $this->assertContains(PackageType::CacheServer, $cases);
        $this->assertContains(PackageType::QueueWorker, $cases);
    }

    public function test_package_type_is_backed_enum(): void
    {
        $reflection = new \ReflectionEnum(PackageType::class);
        $this->assertTrue($reflection->isBacked());
        $this->assertEquals('string', $reflection->getBackingType()->getName());
    }

    public function test_each_package_type_has_unique_value(): void
    {
        $values = array_map(fn ($case) => $case->value, PackageType::cases());
        $this->assertEquals(count($values), count(array_unique($values)));
    }

    public function test_each_package_type_has_unique_label(): void
    {
        $labels = array_map(fn ($case) => $case->label(), PackageType::cases());
        $this->assertEquals(count($labels), count(array_unique($labels)));
    }
}
