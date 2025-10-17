<?php

namespace Tests\Inertia\Servers;

use App\Models\Server;
use App\Models\ServerFirewall;
use App\Models\ServerFirewallRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FirewallTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test firewall page renders correct Inertia component for modal.
     */
    public function test_firewall_page_renders_correct_component_for_modal(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/firewall");

        // Assert - verify Inertia component renders
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/firewall')
        );
    }

    /**
     * Test firewall page indicates when firewall is not installed.
     */
    public function test_firewall_page_indicates_when_firewall_not_installed(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        // No firewall created - testing not installed state

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/firewall");

        // Assert - verify Inertia props indicate firewall not installed
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/firewall')
            ->where('server.firewall.isInstalled', false)
            ->where('server.firewall.status', 'not_installed')
            ->has('server.firewall.rules', 0)
        );
    }

    /**
     * Test firewall page provides server data in Inertia props.
     */
    public function test_firewall_page_provides_server_data_in_props(): void
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
        $response = $this->actingAs($user)->get("/servers/{$server->id}/firewall");

        // Assert - server data available for page header/breadcrumbs
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/firewall')
            ->has('server')
            ->where('server.id', $server->id)
            ->where('server.vanity_name', 'Production Server')
            ->where('server.public_ip', '192.168.1.100')
        );
    }

    /**
     * Test firewall page includes firewall rules in Inertia props.
     */
    public function test_firewall_page_includes_firewall_rules_in_props(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        $rule1 = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'name' => 'HTTP Traffic',
            'port' => '80',
            'from_ip_address' => null,
            'rule_type' => 'allow',
            'status' => 'active',
        ]);

        $rule2 = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'name' => 'HTTPS Traffic',
            'port' => '443',
            'from_ip_address' => null,
            'rule_type' => 'allow',
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/firewall");

        // Assert - verify firewall rules data for display (latest ID first)
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/firewall')
            ->has('server.firewall.rules', 2)
            ->has('server.firewall.rules.0', fn ($rule) => $rule
                ->where('id', $rule2->id)
                ->where('name', 'HTTPS Traffic')
                ->where('port', '443')
                ->where('rule_type', 'allow')
                ->where('status', 'active')
                ->etc()
            )
            ->has('server.firewall.rules.1', fn ($rule) => $rule
                ->where('id', $rule1->id)
                ->where('name', 'HTTP Traffic')
                ->where('port', '80')
                ->etc()
            )
        );
    }

    /**
     * Test firewall page shows empty state when no rules exist.
     */
    public function test_firewall_page_shows_empty_state_in_inertia_props(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/firewall");

        // Assert - empty state props for showing "Add Firewall Rule" prompt
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/firewall')
            ->has('server.firewall.rules', 0)
        );
    }

    /**
     * Test firewall page includes rule status information in Inertia props.
     */
    public function test_firewall_page_includes_rule_status_information(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        $rule1 = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'name' => 'Active Rule',
            'status' => 'active',
        ]);

        $rule2 = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'name' => 'Pending Rule',
            'status' => 'pending',
        ]);

        $rule3 = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'name' => 'Failed Rule',
            'status' => 'failed',
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/firewall");

        // Assert - status information for UI display/badges (latest ID first)
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/firewall')
            ->has('server.firewall.rules', 3)
            ->where('server.firewall.rules.0.id', $rule3->id)
            ->where('server.firewall.rules.0.status', 'failed')
            ->where('server.firewall.rules.1.id', $rule2->id)
            ->where('server.firewall.rules.1.status', 'pending')
            ->where('server.firewall.rules.2.id', $rule1->id)
            ->where('server.firewall.rules.2.status', 'active')
        );
    }

    /**
     * Test firewall page includes port range rules.
     */
    public function test_firewall_page_includes_port_range_rules(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'name' => 'Port Range',
            'port' => '3000-3005',
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/firewall");

        // Assert - port range displayed correctly
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/firewall')
            ->has('server.firewall.rules', 1)
            ->where('server.firewall.rules.0.port', '3000-3005')
        );
    }

    /**
     * Test firewall page includes IP address restrictions in Inertia props.
     */
    public function test_firewall_page_includes_ip_address_restrictions(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        $rule1 = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'name' => 'Restricted SSH',
            'port' => '22',
            'from_ip_address' => '192.168.1.100',
        ]);

        $rule2 = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'name' => 'Open HTTP',
            'port' => '80',
            'from_ip_address' => null,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/firewall");

        // Assert - IP restrictions shown for appropriate rules (latest ID first)
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/firewall')
            ->has('server.firewall.rules', 2)
            ->where('server.firewall.rules.0.id', $rule2->id)
            ->where('server.firewall.rules.0.from_ip_address', null)
            ->where('server.firewall.rules.1.id', $rule1->id)
            ->where('server.firewall.rules.1.from_ip_address', '192.168.1.100')
        );
    }

    /**
     * Test firewall page includes rule types in Inertia props.
     */
    public function test_firewall_page_includes_rule_types(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        $rule1 = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'name' => 'Allow Rule',
            'rule_type' => 'allow',
        ]);

        $rule2 = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'name' => 'Deny Rule',
            'rule_type' => 'deny',
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/firewall");

        // Assert - rule types displayed for UI (latest ID first)
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/firewall')
            ->has('server.firewall.rules', 2)
            ->where('server.firewall.rules.0.id', $rule2->id)
            ->where('server.firewall.rules.0.rule_type', 'deny')
            ->where('server.firewall.rules.1.id', $rule1->id)
            ->where('server.firewall.rules.1.rule_type', 'allow')
        );
    }

    /**
     * Test Inertia form submission creates firewall rule via modal.
     */
    public function test_inertia_form_submission_creates_firewall_rule_via_modal(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act - simulate Inertia modal form POST
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall", [
                'name' => 'Custom Application',
                'port' => '8080',
                'from_ip_address' => '192.168.1.1',
                'rule_type' => 'allow',
            ]);

        // Assert - redirects with success message
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Firewall rule is being applied.');

        // Verify database
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
     * Test Inertia form validation errors are returned to modal.
     */
    public function test_inertia_form_validation_errors_returned_to_modal(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act - submit invalid data via modal form
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall", [
                'name' => '', // Missing required field
                'port' => 'invalid-port',
                'from_ip_address' => 'not-an-ip',
            ]);

        // Assert - validation errors in session for modal display
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['name', 'port', 'from_ip_address']);
    }

    /**
     * Test Inertia receives user authentication state.
     */
    public function test_inertia_receives_user_authentication_state(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/firewall");

        // Assert - user data shared with Inertia
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('auth.user')
            ->where('auth.user.name', 'Jane Doe')
            ->where('auth.user.email', 'jane@example.com')
        );
    }

    /**
     * Test firewall page returns proper Inertia structure for modal functionality.
     */
    public function test_firewall_page_returns_proper_inertia_structure_for_modal(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/firewall");

        // Assert - verify proper structure for modal to function
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/firewall')
            ->has('server')
            ->has('server.firewall')
            ->has('server.firewall.rules')
        );
    }

    /**
     * Test successful Inertia form submission provides flash message.
     */
    public function test_successful_inertia_form_submission_provides_flash_message(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act - submit modal form
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall", [
                'name' => 'New Rule',
                'port' => '9000',
            ]);

        // Assert - success flash message for toast notification
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Firewall rule is being applied.');
    }

    /**
     * Test firewall page includes firewall enabled status.
     */
    public function test_firewall_page_includes_firewall_enabled_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create([
            'server_id' => $server->id,
            'is_enabled' => true,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/firewall");

        // Assert - firewall enabled status for UI toggle/display
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/firewall')
            ->has('server.firewall')
            ->where('server.firewall.is_enabled', true)
        );
    }

    /**
     * Test Inertia delete action removes firewall rule.
     */
    public function test_inertia_delete_action_removes_firewall_rule(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'name' => 'Rule to Delete',
            'status' => 'active',
        ]);

        // Act - simulate Inertia delete action
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/firewall/{$rule->id}");

        // Assert - redirects with success message
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Firewall rule is being removed.');

        // Verify status updated to 'removing'
        $this->assertDatabaseHas('server_firewall_rules', [
            'id' => $rule->id,
            'status' => 'removing',
        ]);
    }

    /**
     * Test firewall page includes multiple rules with different statuses.
     */
    public function test_firewall_page_includes_multiple_rules_with_different_statuses(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        $rule1 = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'name' => 'Active HTTP',
            'port' => '80',
            'status' => 'active',
        ]);

        $rule2 = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'name' => 'Pending HTTPS',
            'port' => '443',
            'status' => 'pending',
        ]);

        $rule3 = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'name' => 'Installing SSH',
            'port' => '22',
            'status' => 'installing',
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/firewall");

        // Assert - all rules with statuses for real-time UI updates (latest ID first)
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/firewall')
            ->has('server.firewall.rules', 3)
            ->where('server.firewall.rules.0.id', $rule3->id)
            ->where('server.firewall.rules.0.name', 'Installing SSH')
            ->where('server.firewall.rules.0.status', 'installing')
            ->where('server.firewall.rules.1.id', $rule2->id)
            ->where('server.firewall.rules.1.name', 'Pending HTTPS')
            ->where('server.firewall.rules.1.status', 'pending')
            ->where('server.firewall.rules.2.id', $rule1->id)
            ->where('server.firewall.rules.2.name', 'Active HTTP')
            ->where('server.firewall.rules.2.status', 'active')
        );
    }

    /**
     * Test Inertia form handles port range submission.
     */
    public function test_inertia_form_handles_port_range_submission(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act - submit port range via modal
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall", [
                'name' => 'Development Ports',
                'port' => '3000-3005',
                'rule_type' => 'allow',
            ]);

        // Assert - success and database entry
        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('server_firewall_rules', [
            'server_firewall_id' => $firewall->id,
            'name' => 'Development Ports',
            'port' => '3000-3005',
        ]);
    }

    /**
     * Test firewall page includes rule IDs for delete actions.
     */
    public function test_firewall_page_includes_rule_ids_for_delete_actions(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        $rule1 = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'name' => 'Rule 1',
        ]);

        $rule2 = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'name' => 'Rule 2',
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/firewall");

        // Assert - rule IDs available for delete button routing (latest ID first)
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/firewall')
            ->has('server.firewall.rules', 2)
            ->where('server.firewall.rules.0.id', $rule2->id)
            ->where('server.firewall.rules.1.id', $rule1->id)
        );
    }

    /**
     * Test error flash message when firewall not installed.
     */
    public function test_error_flash_message_when_firewall_not_installed(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        // No firewall created

        // Act - try to create rule without firewall
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall", [
                'name' => 'Test Rule',
                'port' => '80',
            ]);

        // Assert - error flash message for modal/toast
        $response->assertStatus(302);
        $response->assertSessionHas('error', 'Firewall is not installed on this server.');
    }

    /**
     * Test firewall page includes server metadata for breadcrumbs.
     */
    public function test_firewall_page_includes_server_metadata_for_breadcrumbs(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Staging Server',
        ]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/firewall");

        // Assert - server metadata for navigation/breadcrumbs
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/firewall')
            ->has('server.id')
            ->has('server.vanity_name')
            ->where('server.vanity_name', 'Staging Server')
        );
    }
}
