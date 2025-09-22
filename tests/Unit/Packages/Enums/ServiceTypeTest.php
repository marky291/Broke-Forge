<?php

namespace Tests\Unit\Packages\Enums;

use App\Packages\Enums\ServiceType;
use Tests\TestCase;

class ServiceTypeTest extends TestCase
{
    public function test_service_type_constants_exist(): void
    {
        $this->assertEquals('database', ServiceType::DATABASE);
        $this->assertEquals('server', ServiceType::SERVER);
        $this->assertEquals('webserver', ServiceType::WEBSERVER);
        $this->assertEquals('site', ServiceType::SITE);
    }

    public function test_service_type_constants_are_strings(): void
    {
        $this->assertIsString(ServiceType::DATABASE);
        $this->assertIsString(ServiceType::SERVER);
        $this->assertIsString(ServiceType::WEBSERVER);
        $this->assertIsString(ServiceType::SITE);
    }

    public function test_service_type_constants_have_unique_values(): void
    {
        $constants = [
            ServiceType::DATABASE,
            ServiceType::SERVER,
            ServiceType::WEBSERVER,
            ServiceType::SITE,
        ];

        $this->assertEquals(count($constants), count(array_unique($constants)));
    }

    public function test_service_type_constants_are_lowercase(): void
    {
        $this->assertEquals(strtolower(ServiceType::DATABASE), ServiceType::DATABASE);
        $this->assertEquals(strtolower(ServiceType::SERVER), ServiceType::SERVER);
        $this->assertEquals(strtolower(ServiceType::WEBSERVER), ServiceType::WEBSERVER);
        $this->assertEquals(strtolower(ServiceType::SITE), ServiceType::SITE);
    }

    public function test_all_service_type_constants_are_accessible(): void
    {
        $reflection = new \ReflectionClass(ServiceType::class);
        $constants = $reflection->getConstants();

        $this->assertCount(4, $constants);
        $this->assertArrayHasKey('DATABASE', $constants);
        $this->assertArrayHasKey('SERVER', $constants);
        $this->assertArrayHasKey('WEBSERVER', $constants);
        $this->assertArrayHasKey('SITE', $constants);
    }
}
