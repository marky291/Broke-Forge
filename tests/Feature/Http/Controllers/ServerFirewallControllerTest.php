<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Server;
use App\Models\ServerFirewall;
use App\Models\ServerFirewallRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerFirewallControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test guest cannot access firewall page.
     */
    public function test_guest_cannot_access_firewall_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->get("/servers/{$server->id}/firewall");

        // Assert - guests should be redirected to login
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test authenticated user can access their server's firewall page.
     */
    public function test_user_can_access_their_server_firewall_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/firewall");

        // Assert
        $response->assertStatus(200);
    }

    /**
     * Test user cannot access other users server firewall page.
     */
    public function test_user_cannot_access_other_users_server_firewall_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/firewall");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test firewall page renders correct Inertia component.
     */
    public function test_firewall_page_renders_correct_inertia_component(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/firewall");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/firewall')
            ->has('server')
        );
    }

    /**
     * Test firewall page includes server data.
     */
    public function test_firewall_page_includes_server_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Production Server',
            'public_ip' => '192.168.1.100',
        ]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/firewall");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/firewall')
            ->where('server.id', $server->id)
            ->where('server.vanity_name', 'Production Server')
            ->where('server.public_ip', '192.168.1.100')
        );
    }

    /**
     * Test firewall page includes firewall rules.
     */
    public function test_firewall_page_includes_firewall_rules(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'name' => 'HTTP Traffic',
            'port' => '80',
            'from_ip_address' => null,
            'rule_type' => 'allow',
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/firewall");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/firewall')
            ->has('server.firewall.rules', 1)
            ->has('server.firewall.rules.0', fn ($rule) => $rule
                ->where('name', 'HTTP Traffic')
                ->where('port', '80')
                ->where('rule_type', 'allow')
                ->where('status', 'active')
                ->etc()
            )
        );
    }

    /**
     * Test firewall page shows multiple rules.
     */
    public function test_firewall_page_shows_multiple_rules(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'name' => 'HTTP',
            'port' => '80',
        ]);

        ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'name' => 'HTTPS',
            'port' => '443',
        ]);

        ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'name' => 'SSH',
            'port' => '22',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/firewall");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/firewall')
            ->has('server.firewall.rules', 3)
        );
    }

    /**
     * Test firewall page shows empty state when no rules exist.
     */
    public function test_firewall_page_shows_empty_state_when_no_rules_exist(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/firewall");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/firewall')
            ->has('server.firewall.rules', 0)
        );
    }

    /**
     * Test user can create firewall rule successfully.
     */
    public function test_user_can_create_firewall_rule_successfully(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall", [
                'name' => 'Custom Application',
                'port' => '8080',
                'from_ip_address' => '192.168.1.1',
                'rule_type' => 'allow',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Firewall rule is being applied.');

        $this->assertDatabaseHas('server_firewall_rules', [
            'server_firewall_id' => $firewall->id,
            'name' => 'Custom Application',
            'port' => '8080',
            'from_ip_address' => '192.168.1.1',
            'rule_type' => 'allow',
            'status' => 'pending',
        ]);
    }

    /**
     * Test firewall rule creation validates required name field.
     */
    public function test_firewall_rule_creation_validates_required_name(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall", [
                'port' => '80',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['name']);
    }

    /**
     * Test firewall rule creation validates port format.
     */
    public function test_firewall_rule_creation_validates_port_format(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall", [
                'name' => 'Invalid Port',
                'port' => 'invalid',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['port']);
    }

    /**
     * Test firewall rule creation validates port range.
     */
    public function test_firewall_rule_creation_validates_port_range(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall", [
                'name' => 'Out of Range Port',
                'port' => '99999',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['port']);
    }

    /**
     * Test firewall rule creation accepts port range format.
     */
    public function test_firewall_rule_creation_accepts_port_range(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall", [
                'name' => 'Port Range',
                'port' => '3000-3005',
                'rule_type' => 'allow',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('server_firewall_rules', [
            'server_firewall_id' => $firewall->id,
            'name' => 'Port Range',
            'port' => '3000-3005',
        ]);
    }

    /**
     * Test firewall rule creation rejects invalid port range.
     */
    public function test_firewall_rule_creation_rejects_invalid_port_range(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act - start port greater than end port
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall", [
                'name' => 'Invalid Range',
                'port' => '3005-3000',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['port']);
    }

    /**
     * Test firewall rule creation prevents duplicate ports.
     */
    public function test_firewall_rule_creation_prevents_duplicate_ports(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'port' => '80',
        ]);

        // Act - try to create another rule for port 80
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall", [
                'name' => 'Duplicate Port',
                'port' => '80',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['port']);
    }

    /**
     * Test firewall rule creation validates IP address format.
     */
    public function test_firewall_rule_creation_validates_ip_address_format(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall", [
                'name' => 'Invalid IP',
                'port' => '80',
                'from_ip_address' => 'not-an-ip',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['from_ip_address']);
    }

    /**
     * Test firewall rule creation validates rule type.
     */
    public function test_firewall_rule_creation_validates_rule_type(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall", [
                'name' => 'Invalid Type',
                'port' => '80',
                'rule_type' => 'invalid',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['rule_type']);
    }

    /**
     * Test firewall rule creation fails when firewall not installed.
     */
    public function test_firewall_rule_creation_fails_when_firewall_not_installed(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        // No firewall created

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall", [
                'name' => 'Test Rule',
                'port' => '80',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('error', 'Firewall is not installed on this server.');
    }

    /**
     * Test user cannot create firewall rule for other users server.
     */
    public function test_user_cannot_create_firewall_rule_for_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall", [
                'name' => 'Unauthorized Rule',
                'port' => '80',
            ]);

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user can delete firewall rule successfully.
     */
    public function test_user_can_delete_firewall_rule_successfully(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/firewall/{$rule->id}");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Firewall rule is being removed.');

        // Verify status updated to 'removing'
        $this->assertDatabaseHas('server_firewall_rules', [
            'id' => $rule->id,
            'status' => 'removing',
        ]);
    }

    /**
     * Test user cannot delete firewall rule for other users server.
     */
    public function test_user_cannot_delete_firewall_rule_for_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/firewall/{$rule->id}");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test delete fails with invalid rule ID.
     */
    public function test_delete_fails_with_invalid_rule_id(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/firewall/99999");

        // Assert - should return error message
        $response->assertStatus(302);
        $response->assertSessionHas('error', 'Failed to remove firewall rule.');
    }

    /**
     * Test delete fails when rule belongs to different server.
     */
    public function test_delete_fails_when_rule_belongs_to_different_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server1 = Server::factory()->create(['user_id' => $user->id]);
        $server2 = Server::factory()->create(['user_id' => $user->id]);

        $firewall1 = ServerFirewall::factory()->create(['server_id' => $server1->id]);
        $firewall2 = ServerFirewall::factory()->create(['server_id' => $server2->id]);

        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall2->id,
        ]);

        // Act - try to delete server2's rule via server1's endpoint
        $response = $this->actingAs($user)
            ->delete("/servers/{$server1->id}/firewall/{$rule->id}");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('error', 'Invalid firewall rule.');
    }

    /**
     * Test firewall page includes rule status information.
     */
    public function test_firewall_page_includes_rule_status_information(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'status' => 'active',
        ]);

        ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'status' => 'pending',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/firewall");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/firewall')
            ->has('server.firewall.rules', 2)
            ->where('server.firewall.rules.0.status', 'pending')
            ->where('server.firewall.rules.1.status', 'active')
        );
    }

    /**
     * Test firewall page includes IP address restrictions.
     */
    public function test_firewall_page_includes_ip_address_restrictions(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'name' => 'Restricted SSH',
            'port' => '22',
            'from_ip_address' => '192.168.1.100',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/firewall");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/firewall')
            ->has('server.firewall.rules', 1)
            ->where('server.firewall.rules.0.from_ip_address', '192.168.1.100')
        );
    }

    /**
     * Test firewall rule accepts deny rule type.
     */
    public function test_firewall_rule_accepts_deny_rule_type(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall", [
                'name' => 'Block Traffic',
                'port' => '23',
                'rule_type' => 'deny',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('server_firewall_rules', [
            'server_firewall_id' => $firewall->id,
            'name' => 'Block Traffic',
            'port' => '23',
            'rule_type' => 'deny',
        ]);
    }
}
