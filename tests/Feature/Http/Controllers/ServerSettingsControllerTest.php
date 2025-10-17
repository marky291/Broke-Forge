<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\MonitoringStatus;
use App\Models\Server;
use App\Models\ServerMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test guest cannot access server settings page.
     */
    public function test_guest_cannot_access_server_settings_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->get("/servers/{$server->id}/settings");

        // Assert - guests should be redirected to login
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test authenticated user can access their server settings page.
     */
    public function test_user_can_access_their_server_settings_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/settings");

        // Assert
        $response->assertStatus(200);
    }

    /**
     * Test user cannot access other users server settings page.
     */
    public function test_user_cannot_access_other_users_server_settings_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/settings");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test settings page renders correct Inertia component.
     */
    public function test_settings_page_renders_correct_inertia_component(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/settings");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/settings')
            ->has('server')
        );
    }

    /**
     * Test settings page includes server data.
     */
    public function test_settings_page_includes_server_data(): void
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
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/settings");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/settings')
            ->where('server.id', $server->id)
            ->where('server.vanity_name', 'Production Server')
            ->where('server.public_ip', '192.168.1.100')
            ->where('server.private_ip', '10.0.0.5')
            ->where('server.ssh_port', 22)
        );
    }

    /**
     * Test settings page includes timestamps.
     */
    public function test_settings_page_includes_timestamps(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/settings");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/settings')
            ->has('server.created_at')
            ->has('server.updated_at')
        );
    }

    /**
     * Test user can update server settings successfully.
     */
    public function test_user_can_update_server_settings_successfully(): void
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

        // Act
        $response = $this->actingAs($user)
            ->put("/servers/{$server->id}/settings", [
                'vanity_name' => 'Updated Server Name',
                'public_ip' => '192.168.1.100',
                'private_ip' => '10.0.0.5',
                'ssh_port' => 2222,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/settings");
        $response->assertSessionHas('success', 'Server settings updated successfully.');

        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'vanity_name' => 'Updated Server Name',
            'public_ip' => '192.168.1.100',
            'private_ip' => '10.0.0.5',
            'ssh_port' => 2222,
        ]);
    }

    /**
     * Test update allows null private IP.
     */
    public function test_update_allows_null_private_ip(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'private_ip' => '10.0.0.5',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->put("/servers/{$server->id}/settings", [
                'vanity_name' => 'Test Server',
                'public_ip' => '192.168.1.100',
                'private_ip' => null,
                'ssh_port' => 22,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'private_ip' => null,
        ]);
    }

    /**
     * Test update validates required vanity_name field.
     */
    public function test_update_validates_required_vanity_name(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->put("/servers/{$server->id}/settings", [
                'vanity_name' => '',
                'public_ip' => '192.168.1.100',
                'ssh_port' => 22,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['vanity_name']);
    }

    /**
     * Test update validates vanity_name max length.
     */
    public function test_update_validates_vanity_name_max_length(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->put("/servers/{$server->id}/settings", [
                'vanity_name' => str_repeat('a', 256),
                'public_ip' => '192.168.1.100',
                'ssh_port' => 22,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['vanity_name']);
    }

    /**
     * Test update validates required public_ip field.
     */
    public function test_update_validates_required_public_ip(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->put("/servers/{$server->id}/settings", [
                'vanity_name' => 'Test Server',
                'public_ip' => '',
                'ssh_port' => 22,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['public_ip']);
    }

    /**
     * Test update validates public_ip format.
     */
    public function test_update_validates_public_ip_format(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->put("/servers/{$server->id}/settings", [
                'vanity_name' => 'Test Server',
                'public_ip' => 'not-an-ip-address',
                'ssh_port' => 22,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['public_ip']);
    }

    /**
     * Test update validates private_ip format when provided.
     */
    public function test_update_validates_private_ip_format(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->put("/servers/{$server->id}/settings", [
                'vanity_name' => 'Test Server',
                'public_ip' => '192.168.1.100',
                'private_ip' => 'invalid-ip',
                'ssh_port' => 22,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['private_ip']);
    }

    /**
     * Test update validates required ssh_port field.
     */
    public function test_update_validates_required_ssh_port(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->put("/servers/{$server->id}/settings", [
                'vanity_name' => 'Test Server',
                'public_ip' => '192.168.1.100',
                'ssh_port' => '',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['ssh_port']);
    }

    /**
     * Test update validates ssh_port is integer.
     */
    public function test_update_validates_ssh_port_is_integer(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->put("/servers/{$server->id}/settings", [
                'vanity_name' => 'Test Server',
                'public_ip' => '192.168.1.100',
                'ssh_port' => 'not-a-number',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['ssh_port']);
    }

    /**
     * Test update validates ssh_port minimum value.
     */
    public function test_update_validates_ssh_port_minimum_value(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->put("/servers/{$server->id}/settings", [
                'vanity_name' => 'Test Server',
                'public_ip' => '192.168.1.100',
                'ssh_port' => 0,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['ssh_port']);
    }

    /**
     * Test update validates ssh_port maximum value.
     */
    public function test_update_validates_ssh_port_maximum_value(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->put("/servers/{$server->id}/settings", [
                'vanity_name' => 'Test Server',
                'public_ip' => '192.168.1.100',
                'ssh_port' => 65536,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['ssh_port']);
    }

    /**
     * Test update accepts valid ssh_port within range.
     */
    public function test_update_accepts_valid_ssh_port_within_range(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->put("/servers/{$server->id}/settings", [
                'vanity_name' => 'Test Server',
                'public_ip' => '192.168.1.100',
                'ssh_port' => 65535,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'ssh_port' => 65535,
        ]);
    }

    /**
     * Test user cannot update other users server settings.
     */
    public function test_user_cannot_update_other_users_server_settings(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $otherUser->id,
            'vanity_name' => 'Original Name',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->put("/servers/{$server->id}/settings", [
                'vanity_name' => 'Hacked Name',
                'public_ip' => '192.168.1.100',
                'ssh_port' => 22,
            ]);

        // Assert
        $response->assertStatus(403);

        // Verify database was not updated
        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'vanity_name' => 'Original Name',
        ]);
    }

    /**
     * Test update validates all fields simultaneously.
     */
    public function test_update_validates_all_fields_simultaneously(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act - submit all invalid data
        $response = $this->actingAs($user)
            ->put("/servers/{$server->id}/settings", [
                'vanity_name' => '',
                'public_ip' => 'invalid',
                'private_ip' => 'invalid',
                'ssh_port' => 'invalid',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'vanity_name',
            'public_ip',
            'private_ip',
            'ssh_port',
        ]);
    }

    /**
     * Test settings page includes latest metrics when monitoring is active.
     */
    public function test_settings_page_includes_latest_metrics_when_monitoring_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'monitoring_status' => MonitoringStatus::Active,
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
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/settings");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/settings')
            ->has('latestMetrics')
            ->where('latestMetrics.cpu_usage', '45.50')
            ->where('latestMetrics.memory_usage_percentage', '50.00')
            ->where('latestMetrics.storage_usage_percentage', '25.00')
        );
    }

    /**
     * Test settings page latestMetrics is null when monitoring is not active.
     */
    public function test_settings_page_latest_metrics_null_when_monitoring_not_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'monitoring_status' => MonitoringStatus::Uninstalled,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/settings");

        // Assert
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
            'monitoring_status' => MonitoringStatus::Active,
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
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/settings");

        // Assert - should return the newest metric
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/settings')
            ->has('latestMetrics')
            ->where('latestMetrics.id', $newestMetric->id)
            ->where('latestMetrics.cpu_usage', '75.50')
        );
    }
}
