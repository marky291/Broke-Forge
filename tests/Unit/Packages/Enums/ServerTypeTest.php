<?php

namespace Tests\Unit\Packages\Enums;

use App\Packages\Enums\ServerType;
use Tests\TestCase;

class ServerTypeTest extends TestCase
{
    public function test_server_type_enum_values(): void
    {
        $this->assertEquals('webserver', ServerType::WebServer->value);
        $this->assertEquals('database', ServerType::DatabaseServer->value);
        $this->assertEquals('application', ServerType::ApplicationServer->value);
        $this->assertEquals('cache', ServerType::CacheServer->value);
        $this->assertEquals('queue', ServerType::QueueWorker->value);
    }

    public function test_server_type_labels(): void
    {
        $this->assertEquals('Web Server', ServerType::WebServer->label());
        $this->assertEquals('Database Server', ServerType::DatabaseServer->label());
        $this->assertEquals('Application Server', ServerType::ApplicationServer->label());
        $this->assertEquals('Cache Server', ServerType::CacheServer->label());
        $this->assertEquals('Queue Worker', ServerType::QueueWorker->label());
    }

    public function test_server_type_from_string(): void
    {
        $this->assertEquals(ServerType::WebServer, ServerType::from('webserver'));
        $this->assertEquals(ServerType::DatabaseServer, ServerType::from('database'));
        $this->assertEquals(ServerType::ApplicationServer, ServerType::from('application'));
        $this->assertEquals(ServerType::CacheServer, ServerType::from('cache'));
        $this->assertEquals(ServerType::QueueWorker, ServerType::from('queue'));
    }

    public function test_server_type_try_from_string(): void
    {
        $this->assertEquals(ServerType::WebServer, ServerType::tryFrom('webserver'));
        $this->assertNull(ServerType::tryFrom('invalid_type'));
    }

    public function test_server_type_cases(): void
    {
        $cases = ServerType::cases();

        $this->assertCount(5, $cases);
        $this->assertContains(ServerType::WebServer, $cases);
        $this->assertContains(ServerType::DatabaseServer, $cases);
        $this->assertContains(ServerType::ApplicationServer, $cases);
        $this->assertContains(ServerType::CacheServer, $cases);
        $this->assertContains(ServerType::QueueWorker, $cases);
    }

    public function test_server_type_is_backed_enum(): void
    {
        $reflection = new \ReflectionEnum(ServerType::class);
        $this->assertTrue($reflection->isBacked());
        $this->assertEquals('string', $reflection->getBackingType()->getName());
    }

    public function test_each_server_type_has_unique_value(): void
    {
        $values = array_map(fn ($case) => $case->value, ServerType::cases());
        $this->assertEquals(count($values), count(array_unique($values)));
    }

    public function test_each_server_type_has_unique_label(): void
    {
        $labels = array_map(fn ($case) => $case->label(), ServerType::cases());
        $this->assertEquals(count($labels), count(array_unique($labels)));
    }
}
