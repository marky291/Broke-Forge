<?php

namespace Tests\Unit\Packages\Enums;

use App\Packages\Enums\Connection;
use Tests\TestCase;

class ConnectionTest extends TestCase
{
    public function test_connection_constants_exist(): void
    {
        $this->assertEquals('pending', Connection::PENDING);
        $this->assertEquals('connecting', Connection::CONNECTING);
        $this->assertEquals('connected', Connection::CONNECTED);
        $this->assertEquals('failed', Connection::FAILED);
    }

    public function test_connection_constants_are_strings(): void
    {
        $this->assertIsString(Connection::PENDING);
        $this->assertIsString(Connection::CONNECTING);
        $this->assertIsString(Connection::CONNECTED);
        $this->assertIsString(Connection::FAILED);
    }

    public function test_connection_constants_have_unique_values(): void
    {
        $constants = [
            Connection::PENDING,
            Connection::CONNECTING,
            Connection::CONNECTED,
            Connection::FAILED,
        ];

        $this->assertEquals(count($constants), count(array_unique($constants)));
    }

    public function test_connection_constants_are_lowercase(): void
    {
        $this->assertEquals(strtolower(Connection::PENDING), Connection::PENDING);
        $this->assertEquals(strtolower(Connection::CONNECTING), Connection::CONNECTING);
        $this->assertEquals(strtolower(Connection::CONNECTED), Connection::CONNECTED);
        $this->assertEquals(strtolower(Connection::FAILED), Connection::FAILED);
    }

    public function test_all_connection_constants_are_accessible(): void
    {
        $reflection = new \ReflectionClass(Connection::class);
        $constants = $reflection->getConstants();

        $this->assertCount(4, $constants);
        $this->assertArrayHasKey('PENDING', $constants);
        $this->assertArrayHasKey('CONNECTING', $constants);
        $this->assertArrayHasKey('CONNECTED', $constants);
        $this->assertArrayHasKey('FAILED', $constants);
    }

    public function test_connection_constant_values(): void
    {
        $reflection = new \ReflectionClass(Connection::class);
        $constants = $reflection->getConstants();

        $this->assertEquals('pending', $constants['PENDING']);
        $this->assertEquals('connecting', $constants['CONNECTING']);
        $this->assertEquals('connected', $constants['CONNECTED']);
        $this->assertEquals('failed', $constants['FAILED']);
    }
}
