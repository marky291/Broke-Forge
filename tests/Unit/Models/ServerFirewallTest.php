<?php

namespace Tests\Unit\Models;

use App\Events\ServerUpdated;
use App\Models\Server;
use App\Models\ServerFirewall;
use App\Models\ServerFirewallRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ServerFirewallTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that firewall belongs to a server.
     */
    public function test_firewall_belongs_to_server(): void
    {
        // Arrange
        $server = Server::factory()->create(['vanity_name' => 'test-server']);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $relatedServer = $firewall->server;

        // Assert
        $this->assertInstanceOf(Server::class, $relatedServer);
        $this->assertEquals($server->id, $relatedServer->id);
        $this->assertEquals('test-server', $relatedServer->vanity_name);
    }

    /**
     * Test that firewall has many rules.
     */
    public function test_firewall_has_many_rules(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);
        ServerFirewallRule::factory()->count(3)->create(['server_firewall_id' => $firewall->id]);

        // Act
        $rules = $firewall->rules;

        // Assert
        $this->assertCount(3, $rules);
        $this->assertInstanceOf(ServerFirewallRule::class, $rules->first());
    }

    /**
     * Test that ServerUpdated event is dispatched when firewall is created.
     */
    public function test_server_updated_event_dispatched_when_firewall_is_created(): void
    {
        // Arrange
        Event::fake([ServerUpdated::class]);
        $server = Server::factory()->create();

        // Act
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Assert
        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    /**
     * Test that ServerUpdated event is dispatched when firewall is updated.
     */
    public function test_server_updated_event_dispatched_when_firewall_is_updated(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        Event::fake([ServerUpdated::class]);

        // Act
        $firewall->update(['is_enabled' => false]);

        // Assert
        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    /**
     * Test that is_enabled is cast to boolean.
     */
    public function test_is_enabled_is_cast_to_boolean(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create([
            'server_id' => $server->id,
            'is_enabled' => '1',
        ]);

        // Act & Assert
        $this->assertIsBool($firewall->is_enabled);
        $this->assertTrue($firewall->is_enabled);
    }

    /**
     * Test that is_enabled false is cast to boolean.
     */
    public function test_is_enabled_false_is_cast_to_boolean(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create([
            'server_id' => $server->id,
            'is_enabled' => '0',
        ]);

        // Act & Assert
        $this->assertIsBool($firewall->is_enabled);
        $this->assertFalse($firewall->is_enabled);
    }

    /**
     * Test that all fillable fields can be mass assigned.
     */
    public function test_all_fillable_fields_can_be_mass_assigned(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act
        $firewall = ServerFirewall::create([
            'server_id' => $server->id,
            'is_enabled' => true,
        ]);

        // Assert
        $this->assertEquals($server->id, $firewall->server_id);
        $this->assertTrue($firewall->is_enabled);
    }

    /**
     * Test that factory creates valid firewall.
     */
    public function test_factory_creates_valid_firewall(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Assert
        $this->assertInstanceOf(ServerFirewall::class, $firewall);
        $this->assertNotNull($firewall->server_id);
        $this->assertIsBool($firewall->is_enabled);
    }

    /**
     * Test that factory defaults to is_enabled true.
     */
    public function test_factory_defaults_to_is_enabled_true(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Assert
        $this->assertTrue($firewall->is_enabled);
    }

    /**
     * Test that firewall can be enabled.
     */
    public function test_firewall_can_be_enabled(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create([
            'server_id' => $server->id,
            'is_enabled' => false,
        ]);

        // Act
        $firewall->update(['is_enabled' => true]);

        // Assert
        $this->assertTrue($firewall->is_enabled);
    }

    /**
     * Test that firewall can be disabled.
     */
    public function test_firewall_can_be_disabled(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create([
            'server_id' => $server->id,
            'is_enabled' => true,
        ]);

        // Act
        $firewall->update(['is_enabled' => false]);

        // Assert
        $this->assertFalse($firewall->is_enabled);
    }

    /**
     * Test that firewall is created with default enabled state.
     */
    public function test_firewall_created_with_default_enabled_state(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Assert
        $this->assertTrue($firewall->is_enabled);
        $this->assertEquals($server->id, $firewall->server_id);
    }

    /**
     * Test that firewall can have multiple rules.
     */
    public function test_firewall_can_have_multiple_rules(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $rule1 = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'port' => 80,
        ]);
        $rule2 = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'port' => 443,
        ]);

        // Assert
        $this->assertCount(2, $firewall->rules);
        $this->assertTrue($firewall->rules->contains($rule1));
        $this->assertTrue($firewall->rules->contains($rule2));
    }

    /**
     * Test that firewall can have no rules.
     */
    public function test_firewall_can_have_no_rules(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $rules = $firewall->rules;

        // Assert
        $this->assertCount(0, $rules);
        $this->assertTrue($rules->isEmpty());
    }
}
