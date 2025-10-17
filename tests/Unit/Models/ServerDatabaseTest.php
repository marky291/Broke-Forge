<?php

namespace Tests\Unit\Models;

use App\Enums\DatabaseStatus;
use App\Enums\DatabaseType;
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
        $database->update(['status' => DatabaseStatus::Failed]);

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
     * Test that type is cast to DatabaseType enum.
     */
    public function test_type_is_cast_to_database_type_enum(): void
    {
        // Arrange
        $database = ServerDatabase::factory()->create(['type' => DatabaseType::MySQL]);

        // Act & Assert
        $this->assertInstanceOf(DatabaseType::class, $database->type);
        $this->assertEquals(DatabaseType::MySQL, $database->type);
    }

    /**
     * Test that status is cast to DatabaseStatus enum.
     */
    public function test_status_is_cast_to_database_status_enum(): void
    {
        // Arrange
        $database = ServerDatabase::factory()->create(['status' => DatabaseStatus::Active]);

        // Act & Assert
        $this->assertInstanceOf(DatabaseStatus::class, $database->status);
        $this->assertEquals(DatabaseStatus::Active, $database->status);
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
            'type' => DatabaseType::PostgreSQL,
            'version' => '15.2',
            'port' => 5432,
            'status' => DatabaseStatus::Pending,
            'root_password' => 'password123',
            'error_message' => 'Test error',
        ]);

        // Assert
        $this->assertEquals('test_db', $database->name);
        $this->assertEquals(DatabaseType::PostgreSQL, $database->type);
        $this->assertEquals('15.2', $database->version);
        $this->assertEquals(5432, $database->port);
        $this->assertEquals(DatabaseStatus::Pending, $database->status);
        $this->assertEquals('password123', $database->root_password);
        $this->assertEquals('Test error', $database->error_message);
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
        $this->assertInstanceOf(DatabaseType::class, $database->type);
        $this->assertNotNull($database->version);
        $this->assertIsInt($database->port);
        $this->assertInstanceOf(DatabaseStatus::class, $database->status);
        $this->assertNotNull($database->root_password);
    }

    /**
     * Test database can have Pending status.
     */
    public function test_database_can_have_pending_status(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['status' => DatabaseStatus::Pending]);

        // Assert
        $this->assertEquals(DatabaseStatus::Pending, $database->status);
    }

    /**
     * Test database can have Installing status.
     */
    public function test_database_can_have_installing_status(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['status' => DatabaseStatus::Installing]);

        // Assert
        $this->assertEquals(DatabaseStatus::Installing, $database->status);
    }

    /**
     * Test database can have Active status.
     */
    public function test_database_can_have_active_status(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['status' => DatabaseStatus::Active]);

        // Assert
        $this->assertEquals(DatabaseStatus::Active, $database->status);
    }

    /**
     * Test database can have Failed status.
     */
    public function test_database_can_have_failed_status(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['status' => DatabaseStatus::Failed]);

        // Assert
        $this->assertEquals(DatabaseStatus::Failed, $database->status);
    }

    /**
     * Test database can have Stopped status.
     */
    public function test_database_can_have_stopped_status(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['status' => DatabaseStatus::Stopped]);

        // Assert
        $this->assertEquals(DatabaseStatus::Stopped, $database->status);
    }

    /**
     * Test database can have Uninstalling status.
     */
    public function test_database_can_have_uninstalling_status(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['status' => DatabaseStatus::Uninstalling]);

        // Assert
        $this->assertEquals(DatabaseStatus::Uninstalling, $database->status);
    }

    /**
     * Test database can have Updating status.
     */
    public function test_database_can_have_updating_status(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['status' => DatabaseStatus::Updating]);

        // Assert
        $this->assertEquals(DatabaseStatus::Updating, $database->status);
    }

    /**
     * Test database can be MySQL type.
     */
    public function test_database_can_be_mysql_type(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['type' => DatabaseType::MySQL]);

        // Assert
        $this->assertEquals(DatabaseType::MySQL, $database->type);
    }

    /**
     * Test database can be MariaDB type.
     */
    public function test_database_can_be_mariadb_type(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['type' => DatabaseType::MariaDB]);

        // Assert
        $this->assertEquals(DatabaseType::MariaDB, $database->type);
    }

    /**
     * Test database can be PostgreSQL type.
     */
    public function test_database_can_be_postgresql_type(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['type' => DatabaseType::PostgreSQL]);

        // Assert
        $this->assertEquals(DatabaseType::PostgreSQL, $database->type);
    }

    /**
     * Test database can be MongoDB type.
     */
    public function test_database_can_be_mongodb_type(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['type' => DatabaseType::MongoDB]);

        // Assert
        $this->assertEquals(DatabaseType::MongoDB, $database->type);
    }

    /**
     * Test database can be Redis type.
     */
    public function test_database_can_be_redis_type(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['type' => DatabaseType::Redis]);

        // Assert
        $this->assertEquals(DatabaseType::Redis, $database->type);
    }

    /**
     * Test database can store error message.
     */
    public function test_database_can_store_error_message(): void
    {
        // Arrange
        $errorMessage = 'Failed to install: Connection timeout';

        // Act
        $database = ServerDatabase::factory()->create([
            'status' => DatabaseStatus::Failed,
            'error_message' => $errorMessage,
        ]);

        // Assert
        $this->assertEquals($errorMessage, $database->error_message);
    }

    /**
     * Test database can have null error message.
     */
    public function test_database_can_have_null_error_message(): void
    {
        // Arrange & Act
        $database = ServerDatabase::factory()->create(['error_message' => null]);

        // Assert
        $this->assertNull($database->error_message);
    }

    /**
     * Test database stores different port numbers correctly.
     */
    public function test_database_stores_different_port_numbers(): void
    {
        // Arrange & Act
        $mysqlDb = ServerDatabase::factory()->create(['type' => DatabaseType::MySQL, 'port' => 3306]);
        $postgresDb = ServerDatabase::factory()->create(['type' => DatabaseType::PostgreSQL, 'port' => 5432]);
        $redisDb = ServerDatabase::factory()->create(['type' => DatabaseType::Redis, 'port' => 6379]);

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
}
