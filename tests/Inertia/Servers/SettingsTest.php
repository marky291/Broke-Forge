<?php

namespace Tests\Inertia\Servers;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test settings page renders correct Inertia component.
     */
    public function test_settings_page_renders_correct_component(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/settings");

        // Assert - verify Inertia component renders
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/settings')
        );
    }

    /**
     * Test settings page provides server data in Inertia props.
     */
    public function test_settings_page_provides_server_data_in_props(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Production Server',
            'public_ip' => '192.168.1.100',
            'private_ip' => '10.0.0.5',
            'ssh_port' => 22,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/settings");

        // Assert - server data available for form fields
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/settings')
            ->has('server')
            ->where('server.id', $server->id)
            ->where('server.vanity_name', 'Production Server')
            ->where('server.public_ip', '192.168.1.100')
            ->where('server.private_ip', '10.0.0.5')
            ->where('server.ssh_port', 22)
        );
    }

    /**
     * Test settings page includes server timestamps for display.
     */
    public function test_settings_page_includes_server_timestamps(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/settings");

        // Assert - timestamps for connection information card
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/settings')
            ->has('server.created_at')
            ->has('server.updated_at')
        );
    }

    /**
     * Test settings page includes server ID for display.
     */
    public function test_settings_page_includes_server_id(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/settings");

        // Assert - server ID for connection information card
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/settings')
            ->has('server.id')
            ->where('server.id', $server->id)
        );
    }

    /**
     * Test settings page includes connection status.
     */
    public function test_settings_page_includes_connection_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/settings");

        // Assert - connection status for connection information card
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/settings')
            ->has('server.connection_status')
        );
    }

    /**
     * Test settings page includes latestMetrics when monitoring is active.
     */
    public function test_settings_page_includes_latest_metrics_when_monitoring_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'monitoring_status' => TaskStatus::Active,
        ]);

        $metric = ServerMetric::factory()->create([
            'server_id' => $server->id,
            'cpu_usage' => 45.5,
            'memory_total_mb' => 8192,
            'memory_used_mb' => 4096,
            'memory_usage_percentage' => 50.0,
            'storage_total_gb' => 100,
            'storage_used_gb' => 25,
            'storage_usage_percentage' => 25.0,
            'collected_at' => now(),
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/settings");

        // Assert - metrics for server header display
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/settings')
            ->has('latestMetrics')
            ->has('latestMetrics', fn ($metrics) => $metrics
                ->where('id', $metric->id)
                ->where('cpu_usage', '45.50')
                ->where('memory_total_mb', 8192)
                ->where('memory_used_mb', 4096)
                ->where('memory_usage_percentage', '50.00')
                ->where('storage_total_gb', 100)
                ->where('storage_used_gb', 25)
                ->where('storage_usage_percentage', '25.00')
                ->has('collected_at')
                ->etc()
            )
        );
    }

    /**
     * Test settings page latestMetrics is null when monitoring not active.
     */
    public function test_settings_page_latest_metrics_null_when_monitoring_not_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'monitoring_status' => null,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/settings");

        // Assert - no metrics when monitoring not active
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/settings')
            ->where('latestMetrics', null)
        );
    }

    /**
     * Test settings page returns most recent metric.
     */
    public function test_settings_page_returns_most_recent_metric(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'monitoring_status' => TaskStatus::Active,
        ]);

        // Create older metric
        ServerMetric::factory()->create([
            'server_id' => $server->id,
            'cpu_usage' => 10.0,
            'collected_at' => now()->subHours(2),
        ]);

        // Create newest metric
        $newestMetric = ServerMetric::factory()->create([
            'server_id' => $server->id,
            'cpu_usage' => 75.5,
            'collected_at' => now(),
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/settings");

        // Assert - should return the most recent metric
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/settings')
            ->has('latestMetrics')
            ->where('latestMetrics.id', $newestMetric->id)
            ->where('latestMetrics.cpu_usage', '75.50')
        );
    }

    /**
     * Test Inertia form submission updates server settings.
     */
    public function test_inertia_form_submission_updates_server_settings(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Old Name',
            'public_ip' => '192.168.1.1',
            'private_ip' => null,
            'ssh_port' => 22,
        ]);

        // Act - simulate Inertia form PUT
        $response = $this->actingAs($user)
            ->put("/servers/{$server->id}/settings", [
                'vanity_name' => 'Updated Server Name',
                'public_ip' => '192.168.1.100',
                'private_ip' => '10.0.0.5',
                'ssh_port' => 2222,
            ]);

        // Assert - redirects with success message
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/settings");
        $response->assertSessionHas('success', 'Server settings updated successfully.');

        // Verify database updated
        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'vanity_name' => 'Updated Server Name',
            'public_ip' => '192.168.1.100',
            'private_ip' => '10.0.0.5',
            'ssh_port' => 2222,
        ]);
    }

    /**
     * Test Inertia form validation errors are returned.
     */
    public function test_inertia_form_validation_errors_returned(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act - submit invalid data via form
        $response = $this->actingAs($user)
            ->put("/servers/{$server->id}/settings", [
                'vanity_name' => '',
                'public_ip' => 'not-an-ip',
                'private_ip' => 'invalid-ip',
                'ssh_port' => 'not-a-number',
            ]);

        // Assert - validation errors in session for form display
        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'vanity_name',
            'public_ip',
            'private_ip',
            'ssh_port',
        ]);
    }

    /**
     * Test successful form submission provides flash message.
     */
    public function test_successful_form_submission_provides_flash_message(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act - submit valid form data
        $response = $this->actingAs($user)
            ->put("/servers/{$server->id}/settings", [
                'vanity_name' => 'Updated Server',
                'public_ip' => '192.168.1.100',
                'ssh_port' => 22,
            ]);

        // Assert - success flash message for toast notification
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Server settings updated successfully.');
    }

    /**
     * Test Inertia receives user authentication state.
     */
    public function test_inertia_receives_user_authentication_state(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'John Smith',
            'email' => 'john@example.com',
        ]);
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/settings");

        // Assert - user data shared with Inertia
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('auth.user')
            ->where('auth.user.name', 'John Smith')
            ->where('auth.user.email', 'john@example.com')
        );
    }

    /**
     * Test settings page handles server with null private IP.
     */
    public function test_settings_page_handles_server_with_null_private_ip(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'private_ip' => null,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/settings");

        // Assert - null private_ip for optional form field
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/settings')
            ->where('server.private_ip', null)
        );
    }

    /**
     * Test settings page includes server for breadcrumbs and navigation.
     */
    public function test_settings_page_includes_server_for_breadcrumbs(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'My Production Server',
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/settings");

        // Assert - server data for breadcrumbs and navigation
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/settings')
            ->has('server.id')
            ->has('server.vanity_name')
            ->where('server.vanity_name', 'My Production Server')
        );
    }

    /**
     * Test form update handles partial data correctly.
     */
    public function test_form_update_handles_partial_data_correctly(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Test Server',
            'public_ip' => '192.168.1.1',
            'private_ip' => '10.0.0.5',
            'ssh_port' => 22,
        ]);

        // Act - update only some fields
        $response = $this->actingAs($user)
            ->put("/servers/{$server->id}/settings", [
                'vanity_name' => 'Updated Name',
                'public_ip' => '192.168.1.1',
                'private_ip' => null,
                'ssh_port' => 22,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success');

        // Verify database updated correctly
        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'vanity_name' => 'Updated Name',
            'public_ip' => '192.168.1.1',
            'private_ip' => null,
            'ssh_port' => 22,
        ]);
    }

    /**
     * Test form displays validation error for invalid SSH port.
     */
    public function test_form_displays_validation_error_for_invalid_ssh_port(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act - submit out of range SSH port
        $response = $this->actingAs($user)
            ->put("/servers/{$server->id}/settings", [
                'vanity_name' => 'Test Server',
                'public_ip' => '192.168.1.100',
                'ssh_port' => 99999,
            ]);

        // Assert - validation error for SSH port
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['ssh_port']);
    }

    /**
     * Test form accepts custom SSH port.
     */
    public function test_form_accepts_custom_ssh_port(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'ssh_port' => 22,
        ]);

        // Act - update with custom SSH port
        $response = $this->actingAs($user)
            ->put("/servers/{$server->id}/settings", [
                'vanity_name' => 'Test Server',
                'public_ip' => '192.168.1.100',
                'ssh_port' => 2222,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success');

        // Verify custom port saved
        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'ssh_port' => 2222,
        ]);
    }

    /**
     * Test destroy button deletes server via Inertia.
     */
    public function test_destroy_button_deletes_server_via_inertia(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Server to Delete',
        ]);

        // Act - simulate Inertia delete action from danger zone button
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}");

        // Assert - redirects after deletion
        $response->assertStatus(302);

        // Verify server deleted from database
        $this->assertDatabaseMissing('servers', [
            'id' => $server->id,
        ]);
    }

    /**
     * Test user cannot delete other users server via destroy button.
     */
    public function test_user_cannot_delete_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        // Act - try to delete via danger zone button
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}");

        // Assert - forbidden
        $response->assertStatus(403);

        // Verify server still exists
        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
        ]);
    }

    /**
     * Test settings page displays all editable fields.
     */
    public function test_settings_page_displays_all_editable_fields(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'My Server',
            'public_ip' => '192.168.1.100',
            'private_ip' => '10.0.0.5',
            'ssh_port' => 22,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/settings");

        // Assert - all editable fields available for form
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/settings')
            ->has('server.vanity_name')
            ->has('server.public_ip')
            ->has('server.private_ip')
            ->has('server.ssh_port')
            ->where('server.vanity_name', 'My Server')
            ->where('server.public_ip', '192.168.1.100')
            ->where('server.private_ip', '10.0.0.5')
            ->where('server.ssh_port', 22)
        );
    }
}
