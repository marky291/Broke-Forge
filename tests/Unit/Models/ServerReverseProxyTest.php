<?php

namespace Tests\Unit\Models;

use App\Enums\ReverseProxyStatus;
use App\Enums\ReverseProxyType;
use App\Models\Server;
use App\Models\ServerReverseProxy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerReverseProxyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test server reverse proxy belongs to a server.
     */
    public function test_belongs_to_server(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $reverseProxy = ServerReverseProxy::factory()->create([
            'server_id' => $server->id,
        ]);

        // Act
        $result = $reverseProxy->server;

        // Assert
        $this->assertInstanceOf(Server::class, $result);
        $this->assertEquals($server->id, $result->id);
    }

    /**
     * Test type is cast to ReverseProxyType enum.
     */
    public function test_type_is_cast_to_reverse_proxy_type_enum(): void
    {
        // Arrange
        $reverseProxy = ServerReverseProxy::factory()->create([
            'type' => ReverseProxyType::Nginx,
        ]);

        // Act
        $type = $reverseProxy->type;

        // Assert
        $this->assertInstanceOf(ReverseProxyType::class, $type);
        $this->assertEquals(ReverseProxyType::Nginx, $type);
    }

    /**
     * Test status is cast to ReverseProxyStatus enum.
     */
    public function test_status_is_cast_to_reverse_proxy_status_enum(): void
    {
        // Arrange
        $reverseProxy = ServerReverseProxy::factory()->create([
            'status' => ReverseProxyStatus::Active,
        ]);

        // Act
        $status = $reverseProxy->status;

        // Assert
        $this->assertInstanceOf(ReverseProxyStatus::class, $status);
        $this->assertEquals(ReverseProxyStatus::Active, $status);
    }

    /**
     * Test worker connections is cast to integer.
     */
    public function test_worker_connections_is_cast_to_integer(): void
    {
        // Arrange
        $reverseProxy = ServerReverseProxy::factory()->create([
            'worker_connections' => 2048,
        ]);

        // Act
        $workerConnections = $reverseProxy->worker_connections;

        // Assert
        $this->assertIsInt($workerConnections);
        $this->assertEquals(2048, $workerConnections);
    }

    /**
     * Test can create nginx reverse proxy.
     */
    public function test_can_create_nginx_reverse_proxy(): void
    {
        // Arrange & Act
        $reverseProxy = ServerReverseProxy::factory()->create([
            'type' => ReverseProxyType::Nginx,
            'version' => '1.24.0',
        ]);

        // Assert
        $this->assertEquals(ReverseProxyType::Nginx, $reverseProxy->type);
        $this->assertEquals('1.24.0', $reverseProxy->version);
    }

    /**
     * Test can create apache reverse proxy.
     */
    public function test_can_create_apache_reverse_proxy(): void
    {
        // Arrange & Act
        $reverseProxy = ServerReverseProxy::factory()->create([
            'type' => ReverseProxyType::Apache,
            'version' => '2.4.57',
        ]);

        // Assert
        $this->assertEquals(ReverseProxyType::Apache, $reverseProxy->type);
        $this->assertEquals('2.4.57', $reverseProxy->version);
    }

    /**
     * Test can create caddy reverse proxy.
     */
    public function test_can_create_caddy_reverse_proxy(): void
    {
        // Arrange & Act
        $reverseProxy = ServerReverseProxy::factory()->create([
            'type' => ReverseProxyType::Caddy,
            'version' => '2.7.6',
        ]);

        // Assert
        $this->assertEquals(ReverseProxyType::Caddy, $reverseProxy->type);
        $this->assertEquals('2.7.6', $reverseProxy->version);
    }

    /**
     * Test status can be set to different enum values.
     */
    public function test_status_can_be_set_to_different_enum_values(): void
    {
        // Arrange & Act
        $reverseProxy = ServerReverseProxy::factory()->create([
            'status' => ReverseProxyStatus::Installing,
        ]);

        // Assert
        $this->assertEquals(ReverseProxyStatus::Installing, $reverseProxy->status);

        // Act - update status
        $reverseProxy->update(['status' => ReverseProxyStatus::Active]);

        // Assert
        $this->assertEquals(ReverseProxyStatus::Active, $reverseProxy->fresh()->status);
    }

    /**
     * Test can set status to failed.
     */
    public function test_can_set_status_to_failed(): void
    {
        // Arrange & Act
        $reverseProxy = ServerReverseProxy::factory()->create([
            'status' => ReverseProxyStatus::Failed,
        ]);

        // Assert
        $this->assertEquals(ReverseProxyStatus::Failed, $reverseProxy->status);
    }

    /**
     * Test can set status to stopped.
     */
    public function test_can_set_status_to_stopped(): void
    {
        // Arrange & Act
        $reverseProxy = ServerReverseProxy::factory()->create([
            'status' => ReverseProxyStatus::Stopped,
        ]);

        // Assert
        $this->assertEquals(ReverseProxyStatus::Stopped, $reverseProxy->status);
    }

    /**
     * Test worker processes can be set to auto.
     */
    public function test_worker_processes_can_be_set_to_auto(): void
    {
        // Arrange & Act
        $reverseProxy = ServerReverseProxy::factory()->create([
            'worker_processes' => 'auto',
        ]);

        // Assert
        $this->assertEquals('auto', $reverseProxy->worker_processes);
    }

    /**
     * Test worker processes can be set to numeric value.
     */
    public function test_worker_processes_can_be_set_to_numeric_value(): void
    {
        // Arrange & Act
        $reverseProxy = ServerReverseProxy::factory()->create([
            'worker_processes' => '4',
        ]);

        // Assert
        $this->assertEquals('4', $reverseProxy->worker_processes);
    }

    /**
     * Test worker connections can store different values.
     */
    public function test_worker_connections_can_store_different_values(): void
    {
        // Arrange & Act
        $proxy1 = ServerReverseProxy::factory()->create(['worker_connections' => 512]);
        $proxy2 = ServerReverseProxy::factory()->create(['worker_connections' => 1024]);
        $proxy3 = ServerReverseProxy::factory()->create(['worker_connections' => 4096]);

        // Assert
        $this->assertEquals(512, $proxy1->worker_connections);
        $this->assertEquals(1024, $proxy2->worker_connections);
        $this->assertEquals(4096, $proxy3->worker_connections);
    }

    /**
     * Test version can be nullable.
     */
    public function test_version_can_be_nullable(): void
    {
        // Arrange & Act
        $reverseProxy = ServerReverseProxy::factory()->create([
            'version' => null,
        ]);

        // Assert
        $this->assertNull($reverseProxy->version);
    }

    /**
     * Test version can store different formats.
     */
    public function test_version_can_store_different_formats(): void
    {
        // Arrange & Act
        $proxy1 = ServerReverseProxy::factory()->create(['version' => '1.24.0']);
        $proxy2 = ServerReverseProxy::factory()->create(['version' => '2.4.57']);
        $proxy3 = ServerReverseProxy::factory()->create(['version' => '2.7.6']);

        // Assert
        $this->assertEquals('1.24.0', $proxy1->version);
        $this->assertEquals('2.4.57', $proxy2->version);
        $this->assertEquals('2.7.6', $proxy3->version);
    }

    /**
     * Test factory creates server reverse proxy with correct attributes.
     */
    public function test_factory_creates_server_reverse_proxy_with_correct_attributes(): void
    {
        // Act
        $reverseProxy = ServerReverseProxy::factory()->create();

        // Assert
        $this->assertNotNull($reverseProxy->server_id);
        $this->assertInstanceOf(ReverseProxyType::class, $reverseProxy->type);
        $this->assertNotNull($reverseProxy->version);
        $this->assertNotNull($reverseProxy->worker_processes);
        $this->assertIsInt($reverseProxy->worker_connections);
        $this->assertInstanceOf(ReverseProxyStatus::class, $reverseProxy->status);
        $this->assertEquals(ReverseProxyStatus::Active, $reverseProxy->status);
    }

    /**
     * Test server can have only one reverse proxy due to unique constraint.
     */
    public function test_server_can_have_only_one_reverse_proxy(): void
    {
        // Arrange
        $server = Server::factory()->create();
        ServerReverseProxy::factory()->create([
            'server_id' => $server->id,
        ]);

        // Act & Assert
        $this->expectException(\Illuminate\Database\QueryException::class);

        ServerReverseProxy::factory()->create([
            'server_id' => $server->id,
        ]);
    }

    /**
     * Test fillable attributes include all expected fields.
     */
    public function test_fillable_attributes_include_all_expected_fields(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act
        $reverseProxy = ServerReverseProxy::create([
            'server_id' => $server->id,
            'type' => ReverseProxyType::Nginx,
            'version' => '1.25.0',
            'worker_processes' => '8',
            'worker_connections' => 2048,
            'status' => ReverseProxyStatus::Active,
        ]);

        // Assert
        $this->assertEquals($server->id, $reverseProxy->server_id);
        $this->assertEquals(ReverseProxyType::Nginx, $reverseProxy->type);
        $this->assertEquals('1.25.0', $reverseProxy->version);
        $this->assertEquals('8', $reverseProxy->worker_processes);
        $this->assertEquals(2048, $reverseProxy->worker_connections);
        $this->assertEquals(ReverseProxyStatus::Active, $reverseProxy->status);
    }

    /**
     * Test can update reverse proxy configuration.
     */
    public function test_can_update_reverse_proxy_configuration(): void
    {
        // Arrange
        $reverseProxy = ServerReverseProxy::factory()->create([
            'worker_processes' => 'auto',
            'worker_connections' => 1024,
        ]);

        // Act
        $reverseProxy->update([
            'worker_processes' => '4',
            'worker_connections' => 2048,
        ]);

        // Assert
        $this->assertEquals('4', $reverseProxy->fresh()->worker_processes);
        $this->assertEquals(2048, $reverseProxy->fresh()->worker_connections);
    }

    /**
     * Test can update version.
     */
    public function test_can_update_version(): void
    {
        // Arrange
        $reverseProxy = ServerReverseProxy::factory()->create([
            'version' => '1.24.0',
        ]);

        // Act
        $reverseProxy->update(['version' => '1.25.1']);

        // Assert
        $this->assertEquals('1.25.1', $reverseProxy->fresh()->version);
    }

    /**
     * Test reverse proxy is deleted when server is deleted.
     */
    public function test_reverse_proxy_is_deleted_when_server_is_deleted(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $reverseProxy = ServerReverseProxy::factory()->create([
            'server_id' => $server->id,
        ]);

        // Act
        $server->delete();

        // Assert
        $this->assertDatabaseMissing('server_reverse_proxies', [
            'id' => $reverseProxy->id,
        ]);
    }
}
