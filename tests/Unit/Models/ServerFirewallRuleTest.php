<?php

namespace Tests\Unit\Models;

use App\Events\ServerUpdated;
use App\Models\Server;
use App\Models\ServerFirewall;
use App\Models\ServerFirewallRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ServerFirewallRuleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that rule belongs to a firewall.
     */
    public function test_rule_belongs_to_firewall(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);
        $rule = ServerFirewallRule::factory()->create(['server_firewall_id' => $firewall->id]);

        // Act
        $relatedFirewall = $rule->firewall;

        // Assert
        $this->assertInstanceOf(ServerFirewall::class, $relatedFirewall);
        $this->assertEquals($firewall->id, $relatedFirewall->id);
    }

    /**
     * Test that ServerUpdated event is dispatched when rule is created.
     */
    public function test_server_updated_event_dispatched_when_rule_is_created(): void
    {
        // Arrange
        Event::fake([ServerUpdated::class]);
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        ServerFirewallRule::factory()->create(['server_firewall_id' => $firewall->id]);

        // Assert
        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    /**
     * Test that ServerUpdated event is dispatched when rule is updated.
     */
    public function test_server_updated_event_dispatched_when_rule_is_updated(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'status' => 'pending', // Ensure it's not 'active'
        ]);

        Event::fake([ServerUpdated::class]);

        // Act
        $rule->update(['status' => 'active']);

        // Assert
        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    /**
     * Test that ServerUpdated event is dispatched when rule is deleted.
     */
    public function test_server_updated_event_dispatched_when_rule_is_deleted(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);
        $rule = ServerFirewallRule::factory()->create(['server_firewall_id' => $firewall->id]);

        Event::fake([ServerUpdated::class]);

        // Act
        $rule->delete();

        // Assert
        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    /**
     * Test that factory creates valid rule with all required fields.
     */
    public function test_factory_creates_valid_rule(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $rule = ServerFirewallRule::factory()->create(['server_firewall_id' => $firewall->id]);

        // Assert
        $this->assertInstanceOf(ServerFirewallRule::class, $rule);
        $this->assertEquals($firewall->id, $rule->server_firewall_id);
        $this->assertNotNull($rule->name);
        $this->assertNotNull($rule->port);
        $this->assertNotNull($rule->from_ip_address);
        $this->assertNotNull($rule->rule_type);
        $this->assertNotNull($rule->status);
    }

    /**
     * Test that factory generates proper data types for port.
     */
    public function test_factory_generates_proper_port_number(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $rule = ServerFirewallRule::factory()->create(['server_firewall_id' => $firewall->id]);

        // Assert
        $this->assertIsInt($rule->port);
        $this->assertGreaterThanOrEqual(1, $rule->port);
        $this->assertLessThanOrEqual(65535, $rule->port);
    }

    /**
     * Test that factory generates valid IP address.
     */
    public function test_factory_generates_valid_ip_address(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $rule = ServerFirewallRule::factory()->create(['server_firewall_id' => $firewall->id]);

        // Assert
        $this->assertNotNull($rule->from_ip_address);
        $this->assertMatchesRegularExpression('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $rule->from_ip_address);
    }

    /**
     * Test that factory generates valid rule type.
     */
    public function test_factory_generates_valid_rule_type(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $rule = ServerFirewallRule::factory()->create(['server_firewall_id' => $firewall->id]);

        // Assert
        $this->assertContains($rule->rule_type, ['allow', 'deny']);
    }

    /**
     * Test that factory generates valid status.
     */
    public function test_factory_generates_valid_status(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $rule = ServerFirewallRule::factory()->create(['server_firewall_id' => $firewall->id]);

        // Assert
        $this->assertInstanceOf(\App\Enums\TaskStatus::class, $rule->status);
        $this->assertContains($rule->status->value, ['pending', 'installing', 'active', 'failed']);
    }

    /**
     * Test that all fillable fields can be mass assigned.
     */
    public function test_all_fillable_fields_can_be_mass_assigned(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $rule = ServerFirewallRule::create([
            'server_firewall_id' => $firewall->id,
            'name' => 'Test Rule',
            'port' => 8080,
            'from_ip_address' => '192.168.1.1',
            'rule_type' => 'allow',
            'status' => 'active',
        ]);

        // Assert
        $this->assertEquals('Test Rule', $rule->name);
        $this->assertEquals(8080, $rule->port);
        $this->assertEquals('192.168.1.1', $rule->from_ip_address);
        $this->assertEquals('allow', $rule->rule_type);
        $this->assertEquals(\App\Enums\TaskStatus::Active, $rule->status);
    }

    /**
     * Test that rule can have pending status.
     */
    public function test_rule_can_have_pending_status(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'status' => 'pending',
        ]);

        // Assert
        $this->assertEquals(\App\Enums\TaskStatus::Pending, $rule->status);
    }

    /**
     * Test that rule can have installing status.
     */
    public function test_rule_can_have_installing_status(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'status' => 'installing',
        ]);

        // Assert
        $this->assertEquals(\App\Enums\TaskStatus::Installing, $rule->status);
    }

    /**
     * Test that rule can have active status.
     */
    public function test_rule_can_have_active_status(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'status' => 'active',
        ]);

        // Assert
        $this->assertEquals(\App\Enums\TaskStatus::Active, $rule->status);
    }

    /**
     * Test that rule can have failed status.
     */
    public function test_rule_can_have_failed_status(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'status' => 'failed',
        ]);

        // Assert
        $this->assertEquals(\App\Enums\TaskStatus::Failed, $rule->status);
    }

    /**
     * Test that rule can have deny rule type.
     */
    public function test_rule_can_have_deny_rule_type(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'rule_type' => 'deny',
        ]);

        // Assert
        $this->assertEquals('deny', $rule->rule_type);
    }

    /**
     * Test that rule can have allow rule type.
     */
    public function test_rule_can_have_allow_rule_type(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'rule_type' => 'allow',
        ]);

        // Assert
        $this->assertEquals('allow', $rule->rule_type);
    }

    /**
     * Test that rule can store port range.
     */
    public function test_rule_can_store_port_range(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'port' => '3000:3005',
        ]);

        // Assert
        $this->assertEquals('3000:3005', $rule->port);
    }

    /**
     * Test that rule can store single port.
     */
    public function test_rule_can_store_single_port(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'port' => 80,
        ]);

        // Assert
        $this->assertEquals(80, $rule->port);
    }
}
