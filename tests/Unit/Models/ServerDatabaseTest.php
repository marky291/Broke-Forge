<?php

namespace Tests\Unit\Models;

use App\Enums\DatabaseEngine;
use App\Enums\StorageType;
use App\Enums\TaskStatus;
use App\Events\ServerUpdated;
use App\Models\Server;
use App\Models\ServerDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ServerDatabaseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that database belongs to a server.
     */
    public function test_database_belongs_to_server(): void
    {
        // Arrange
        $server = Server::factory()->create(['vanity_name' => 'test-server']);
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        // Act
        $relatedServer = $database->server;

        // Assert
        $this->assertInstanceOf(Server::class, $relatedServer);
        $this->assertEquals($server->id, $relatedServer->id);
        $this->assertEquals('test-server', $relatedServer->vanity_name);
    }

    /**
     * Test that ServerUpdated event is dispatched when database is created.
     */
    public function test_server_updated_event_dispatched_when_database_is_created(): void
    {
        // Arrange
        Event::fake([ServerUpdated::class]);
        $server = Server::factory()->create();

        // Act
        ServerDatabase::factory()->create(['server_id' => $server->id]);

        // Assert
        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    /**
     * Test that ServerUpdated event is dispatched when database is updated.
     */
    public function test_server_updated_event_dispatched_when_database_is_updated(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        Event::fake([ServerUpdated::class]);

        // Act
        $database->update(['status' => TaskStatus::Failed]);

        // Assert
        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    /**
     * Test that ServerUpdated event is dispatched when database is deleted.
     */
    public function test_server_updated_event_dispatched_when_database_is_deleted(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        Event::fake([ServerUpdated::class]);

        // Act
        $database->delete();

        // Assert
        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    /**
     * Test that engine is cast to DatabaseEngine enum.
     */
    public function test_engine_is_cast_to_database_engine_enum(): void
    {
        // Arrange
        $database = ServerDatabase::factory()->create(['engine' => DatabaseEngine::MySQL]);

        // Act & Assert
        $this->assertInstanceOf(DatabaseEngine::class, $database->engine);
        $this->assertEquals(DatabaseEngine::MySQL, $database->engine);
    }

    /**
     * Test that status is cast to DatabaseStatus enum.
     */
    public function test_status_is_cast_to_database_status_enum(): void
    {
        // Arrange
        $database = ServerDatabase::factory()->create(['status' => TaskStatus::Active]);

        // Act & Assert
        $this->assertInstanceOf(TaskStatus::class, $database->status);
        $this->assertEquals(TaskStatus::Active, $database->status);
    }

    /**
     * Test that port is cast to integer.
     */
    public function test_port_is_cast_to_integer(): void
    {
        // Arrange
        $database = ServerDatabase::factory()->create(['port' => '3306']);

        // Act & Assert
        $this->assertIsInt($database->port);
        $this->assertEquals(3306, $database->port);
    }

    /**
     * Test that root_password is hidden in array serialization.
     */
    public function test_root_password_is_hidden_in_array(): void
    {
        // Arrange
        $database = ServerDatabase::factory()->create(['root_password' => 'secret123']);

        // Act
        $array = $database->toArray();

        // Assert
        $this->assertArrayNotHasKey('root_password', $array);
    }

    /**
     * Test that root_password is hidden in JSON serialization.
     */
    public function test_root_password_is_hidden_in_json(): void
    {
        // Arrange
        $database = ServerDatabase::factory()->create(['root_password' => 'secret123']);

        // Act
        $json = $database->toJson();

        // Assert
        $this->assertStringNotContainsString('secret123', $json);
        $this->assertStringNotContainsString('root_password', $json);
    }

    /**
     * Test that all fillable fields can be mass assigned.
     */
    public function test_all_fillable_fields_can_be_mass_assigned(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act
        $database = ServerDatabase::create([
            'server_id' => $server->id,
            'name' => 'test_db',
            'engine' => DatabaseEngine::PostgreSQL,
            'storage_type' => StorageType::Disk,
            'version' => '15.2',
            'port' => 5432,
            'status' => TaskStatus::Pending,
            'root_password' => 'password123',
            'error_log' => 'Test error',
        ]);

        // Assert
        $this->assertEquals('test_db', $database->name);
        $this->assertEquals(DatabaseEngine::PostgreSQL, $database->engine);
        $this->assertEquals(StorageType::Disk, $database->storage_type);
        $this->assertEquals('15.2', $database->version);
        $this->assertEquals(5432, $database->port);
        $this->assertEquals(TaskStatus::Pending, $database->status);
        $this->assertEquals('password123', $database->root_password);
        $this->assertEquals('Test error', $database->error_log);
    }

    /**
     * Test that factory creates valid database.
     */
    public function test_factory_creates_valid_database(): void
    {
        // Act
        $database = ServerDatabase::factory()->create();

        // Assert
        $this->assertInstanceOf(ServerDatabase::class, $database);
        $this->assertNotNull($database->server_id);
        $this->assertNotNull($database->name);
        $this->assertInstanceOf(DatabaseEngine::class, $database->engine);
        $this->assertNotNull($database->version);
        $this->assertIsInt($database->port);
        $this->assertInstanceOf(TaskStatus::class, $database->status);
        $this->assertNotNull($database->root_password);
    }

    /**
     * Test database can have Pending status.
     */
    public function test_database_can_have_pending_status(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['status' => TaskStatus::Pending]);

        // Assert
        $this->assertEquals(TaskStatus::Pending, $database->status);
    }

    /**
     * Test database can have Installing status.
     */
    public function test_database_can_have_installing_status(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['status' => TaskStatus::Installing]);

        // Assert
        $this->assertEquals(TaskStatus::Installing, $database->status);
    }

    /**
     * Test database can have Active status.
     */
    public function test_database_can_have_active_status(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['status' => TaskStatus::Active]);

        // Assert
        $this->assertEquals(TaskStatus::Active, $database->status);
    }

    /**
     * Test database can have Failed status.
     */
    public function test_database_can_have_failed_status(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['status' => TaskStatus::Failed]);

        // Assert
        $this->assertEquals(TaskStatus::Failed, $database->status);
    }

    /**
     * Test database can have Stopped status.
     */
    public function test_database_can_have_stopped_status(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['status' => TaskStatus::Paused]);

        // Assert
        $this->assertEquals(TaskStatus::Paused, $database->status);
    }

    /**
     * Test database can have Uninstalling status.
     */
    public function test_database_can_have_uninstalling_status(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['status' => TaskStatus::Removing]);

        // Assert
        $this->assertEquals(TaskStatus::Removing, $database->status);
    }

    /**
     * Test database can have Updating status.
     */
    public function test_database_can_have_updating_status(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['status' => TaskStatus::Updating]);

        // Assert
        $this->assertEquals(TaskStatus::Updating, $database->status);
    }

    /**
     * Test database can have MySQL engine.
     */
    public function test_database_can_have_mysql_engine(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['engine' => DatabaseEngine::MySQL]);

        // Assert
        $this->assertEquals(DatabaseEngine::MySQL, $database->engine);
    }

    /**
     * Test database can have MariaDB engine.
     */
    public function test_database_can_have_mariadb_engine(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['engine' => DatabaseEngine::MariaDB]);

        // Assert
        $this->assertEquals(DatabaseEngine::MariaDB, $database->engine);
    }

    /**
     * Test database can have PostgreSQL engine.
     */
    public function test_database_can_have_postgresql_engine(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['engine' => DatabaseEngine::PostgreSQL]);

        // Assert
        $this->assertEquals(DatabaseEngine::PostgreSQL, $database->engine);
    }

    /**
     * Test database can be MongoDB type.
     */
    public function test_database_can_be_mongodb_type(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['engine' => DatabaseEngine::MongoDB]);

        // Assert
        $this->assertEquals(DatabaseEngine::MongoDB, $database->engine);
    }

    /**
     * Test database can be Redis type.
     */
    public function test_database_can_be_redis_type(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['engine' => DatabaseEngine::Redis]);

        // Assert
        $this->assertEquals(DatabaseEngine::Redis, $database->engine);
    }

    /**
     * Test database can store error message.
     */
    public function test_database_can_store_error_log(): void
    {
        // Arrange
        $errorMessage = 'Failed to install: Connection timeout';

        // Act
        $database = ServerDatabase::factory()->create([
            'status' => TaskStatus::Failed,
            'error_log' => $errorMessage,
        ]);

        // Assert
        $this->assertEquals($errorMessage, $database->error_log);
    }

    /**
     * Test database can have null error message.
     */
    public function test_database_can_have_null_error_log(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['error_log' => null]);

        // Assert
        $this->assertNull($database->error_log);
    }

    /**
     * Test database stores different port numbers correctly.
     */
    public function test_database_stores_different_port_numbers(): void
    {
        // Arrange & Act
        $mysqlDb = ServerDatabase::factory()->create(['engine' => DatabaseEngine::MySQL, 'port' => 3306]);
        $postgresDb = ServerDatabase::factory()->create(['engine' => DatabaseEngine::PostgreSQL, 'port' => 5432]);
        $redisDb = ServerDatabase::factory()->create(['engine' => DatabaseEngine::Redis, 'port' => 6379]);

        // Assert
        $this->assertEquals(3306, $mysqlDb->port);
        $this->assertEquals(5432, $postgresDb->port);
        $this->assertEquals(6379, $redisDb->port);
    }

    /**
     * Test database stores version information.
     */
    public function test_database_stores_version_information(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['version' => '8.0.35']);

        // Assert
        $this->assertEquals('8.0.35', $database->version);
    }

    /**
     * Test database has many sites relationship.
     */
    public function test_database_has_many_sites(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'name' => 'production_db',
        ]);

        $site1 = \App\Models\ServerSite::factory()->create([
            'server_id' => $server->id,
            'database_id' => $database->id,
            'domain' => 'example.com',
        ]);

        $site2 = \App\Models\ServerSite::factory()->create([
            'server_id' => $server->id,
            'database_id' => $database->id,
            'domain' => 'another.com',
        ]);

        // Create a site without this database
        \App\Models\ServerSite::factory()->create([
            'server_id' => $server->id,
            'database_id' => null,
            'domain' => 'unrelated.com',
        ]);

        // Act
        $sites = $database->sites;

        // Assert
        $this->assertCount(2, $sites);
        $this->assertTrue($sites->contains($site1));
        $this->assertTrue($sites->contains($site2));
        $this->assertEquals('example.com', $sites->first()->domain);
    }

    /**
     * Test database with no sites returns empty collection.
     */
    public function test_database_with_no_sites_returns_empty_collection(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'name' => 'unused_db',
        ]);

        // Act
        $sites = $database->sites;

        // Assert
        $this->assertCount(0, $sites);
        $this->assertTrue($sites->isEmpty());
    }

    /**
     * Test that storage_type is cast to StorageType enum.
     */
    public function test_storage_type_is_cast_to_storage_type_enum(): void
    {
        // Arrange
        $database = ServerDatabase::factory()->create(['storage_type' => StorageType::Disk]);

        // Act & Assert
        $this->assertInstanceOf(StorageType::class, $database->storage_type);
        $this->assertEquals(StorageType::Disk, $database->storage_type);
    }

    /**
     * Test database can have memory storage type.
     */
    public function test_database_can_have_memory_storage_type(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['storage_type' => StorageType::Memory]);

        // Assert
        $this->assertEquals(StorageType::Memory, $database->storage_type);
    }

    /**
     * Test database can have disk storage type.
     */
    public function test_database_can_have_disk_storage_type(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['storage_type' => StorageType::Disk]);

        // Assert
        $this->assertEquals(StorageType::Disk, $database->storage_type);
    }

    /**
     * Test factory generates valid storage_type based on database type.
     */
    public function test_factory_generates_valid_storage_type(): void
    {
        // Act
        $database = ServerDatabase::factory()->create();

        // Assert
        $this->assertInstanceOf(StorageType::class, $database->storage_type);
        $this->assertContains($database->storage_type, StorageType::cases());
    }

    /**
     * Test storage_type can be mass assigned.
     */
    public function test_storage_type_can_be_mass_assigned(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act
        $database = ServerDatabase::create([
            'server_id' => $server->id,
            'name' => 'test_db',
            'engine' => DatabaseEngine::MySQL,
            'version' => '8.0',
            'port' => 3306,
            'status' => TaskStatus::Active,
            'root_password' => 'password123',
            'storage_type' => StorageType::Disk,
        ]);

        // Assert
        $this->assertEquals(StorageType::Disk, $database->storage_type);
    }

    /**
     * Test Redis database uses memory storage type.
     */
    public function test_redis_database_uses_memory_storage_type(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create([
            'engine' => DatabaseEngine::Redis,
            'storage_type' => DatabaseEngine::Redis->storageType(),
        ]);

        // Assert
        $this->assertEquals(StorageType::Memory, $database->storage_type);
    }

    /**
     * Test MySQL database uses disk storage type.
     */
    public function test_mysql_database_uses_disk_storage_type(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create([
            'engine' => DatabaseEngine::MySQL,
            'storage_type' => DatabaseEngine::MySQL->storageType(),
        ]);

        // Assert
        $this->assertEquals(StorageType::Disk, $database->storage_type);
    }
}
