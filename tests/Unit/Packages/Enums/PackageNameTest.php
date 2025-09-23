<?php

namespace Tests\Unit\Packages\Enums;

use App\Packages\Enums\PackageName;
use Tests\TestCase;

class PackageNameTest extends TestCase
{
    public function test_package_name_constants_exist(): void
    {
        $this->assertEquals('database', PackageName::DATABASE);
        $this->assertEquals('server', PackageName::SERVER);
        $this->assertEquals('webserver', PackageName::WEBSERVER);
        $this->assertEquals('site', PackageName::SITE);
    }

    public function test_package_name_constants_are_strings(): void
    {
        $this->assertIsString(PackageName::DATABASE);
        $this->assertIsString(PackageName::SERVER);
        $this->assertIsString(PackageName::WEBSERVER);
        $this->assertIsString(PackageName::SITE);
    }

    public function test_package_name_constants_have_unique_values(): void
    {
        $constants = [
            PackageName::DATABASE,
            PackageName::SERVER,
            PackageName::WEBSERVER,
            PackageName::SITE,
        ];

        $this->assertEquals(count($constants), count(array_unique($constants)));
    }

    public function test_package_name_constants_are_lowercase(): void
    {
        $this->assertEquals(strtolower(PackageName::DATABASE), PackageName::DATABASE);
        $this->assertEquals(strtolower(PackageName::SERVER), PackageName::SERVER);
        $this->assertEquals(strtolower(PackageName::WEBSERVER), PackageName::WEBSERVER);
        $this->assertEquals(strtolower(PackageName::SITE), PackageName::SITE);
    }

    public function test_all_package_name_constants_are_accessible(): void
    {
        $reflection = new \ReflectionClass(PackageName::class);
        $constants = $reflection->getConstants();

        $this->assertCount(4, $constants);
        $this->assertArrayHasKey('DATABASE', $constants);
        $this->assertArrayHasKey('SERVER', $constants);
        $this->assertArrayHasKey('WEBSERVER', $constants);
        $this->assertArrayHasKey('SITE', $constants);
    }
}
