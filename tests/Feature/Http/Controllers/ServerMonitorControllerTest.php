<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Server;
use App\Models\ServerMonitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerMonitorControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test guest cannot access monitor index.
     */
    public function test_guest_cannot_access_monitor_index(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->getJson("/servers/{$server->id}/monitoring/monitors");

        // Assert
        $response->assertStatus(401);
    }

    /**
     * Test user can list their server's monitors.
     */
    public function test_user_can_list_their_server_monitors(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $monitor1 = ServerMonitor::factory()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'name' => 'High CPU Alert',
        ]);
        $monitor2 = ServerMonitor::factory()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'name' => 'Memory Warning',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->getJson("/servers/{$server->id}/monitoring/monitors");

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'monitors' => [
                '*' => [
                    'id',
                    'name',
                    'metric_type',
                    'operator',
                    'threshold',
                    'duration_minutes',
                    'notification_emails',
                    'enabled',
                    'cooldown_minutes',
                    'status',
                ],
            ],
        ]);
        $response->assertJsonCount(2, 'monitors');
    }

    /**
     * Test monitors are ordered by created_at desc.
     */
    public function test_monitors_ordered_by_created_at_desc(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $oldMonitor = ServerMonitor::factory()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'name' => 'Old Monitor',
            'created_at' => now()->subHour(),
        ]);
        $newMonitor = ServerMonitor::factory()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'name' => 'New Monitor',
            'created_at' => now(),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->getJson("/servers/{$server->id}/monitoring/monitors");

        // Assert
        $response->assertStatus(200);
        $response->assertJsonPath('monitors.0.name', 'New Monitor');
        $response->assertJsonPath('monitors.1.name', 'Old Monitor');
    }

    /**
     * Test user cannot list other users server monitors.
     */
    public function test_user_cannot_list_other_users_server_monitors(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        // Act
        $response = $this->actingAs($user)
            ->getJson("/servers/{$server->id}/monitoring/monitors");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user can create monitor successfully.
     */
    public function test_user_can_create_monitor_successfully(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $email = fake()->email();

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/monitoring/monitors", [
                'name' => 'High CPU Alert',
                'metric_type' => 'cpu',
                'operator' => '>',
                'threshold' => 90,
                'duration_minutes' => 15,
                'notification_emails' => [$email],
                'enabled' => true,
                'cooldown_minutes' => 60,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/monitoring");
        $response->assertSessionHas('success', 'Monitor created successfully');

        $this->assertDatabaseHas('server_monitors', [
            'user_id' => $user->id,
            'server_id' => $server->id,
            'name' => 'High CPU Alert',
            'metric_type' => 'cpu',
            'operator' => '>',
            'threshold' => 90.00,
            'duration_minutes' => 15,
            'enabled' => true,
            'cooldown_minutes' => 60,
            'status' => 'normal',
        ]);
    }

    /**
     * Test monitor creation uses default values.
     */
    public function test_monitor_creation_uses_default_values(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/monitoring/monitors", [
                'name' => 'Memory Alert',
                'metric_type' => 'memory',
                'operator' => '>=',
                'threshold' => 85,
                'duration_minutes' => 10,
                'notification_emails' => [fake()->email()],
            ]);

        // Assert
        $response->assertStatus(302);

        $this->assertDatabaseHas('server_monitors', [
            'server_id' => $server->id,
            'name' => 'Memory Alert',
            'enabled' => true,
            'cooldown_minutes' => 60,
            'status' => 'normal',
        ]);
    }

    /**
     * Test user cannot create monitor for other users server.
     */
    public function test_user_cannot_create_monitor_for_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/monitoring/monitors", [
                'name' => 'Unauthorized Monitor',
                'metric_type' => 'cpu',
                'operator' => '>',
                'threshold' => 90,
                'duration_minutes' => 15,
                'notification_emails' => [fake()->email()],
            ]);

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test monitor creation validates required fields.
     */
    public function test_monitor_creation_validates_required_fields(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/monitoring/monitors", []);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'name',
            'metric_type',
            'operator',
            'threshold',
            'duration_minutes',
            'notification_emails',
        ]);
    }

    /**
     * Test monitor creation validates metric type.
     */
    public function test_monitor_creation_validates_metric_type(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/monitoring/monitors", [
                'name' => 'Invalid Monitor',
                'metric_type' => 'invalid',
                'operator' => '>',
                'threshold' => 90,
                'duration_minutes' => 15,
                'notification_emails' => [fake()->email()],
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['metric_type']);
    }

    /**
     * Test monitor creation validates operator.
     */
    public function test_monitor_creation_validates_operator(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/monitoring/monitors", [
                'name' => 'Invalid Monitor',
                'metric_type' => 'cpu',
                'operator' => '!=',
                'threshold' => 90,
                'duration_minutes' => 15,
                'notification_emails' => [fake()->email()],
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['operator']);
    }

    /**
     * Test monitor creation validates threshold range.
     */
    public function test_monitor_creation_validates_threshold_range(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act - threshold too high
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/monitoring/monitors", [
                'name' => 'Invalid Monitor',
                'metric_type' => 'cpu',
                'operator' => '>',
                'threshold' => 150,
                'duration_minutes' => 15,
                'notification_emails' => [fake()->email()],
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['threshold']);

        // Act - threshold negative
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/monitoring/monitors", [
                'name' => 'Invalid Monitor',
                'metric_type' => 'cpu',
                'operator' => '>',
                'threshold' => -10,
                'duration_minutes' => 15,
                'notification_emails' => [fake()->email()],
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['threshold']);
    }

    /**
     * Test monitor creation validates duration minutes range.
     */
    public function test_monitor_creation_validates_duration_minutes_range(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act - duration too high
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/monitoring/monitors", [
                'name' => 'Invalid Monitor',
                'metric_type' => 'cpu',
                'operator' => '>',
                'threshold' => 90,
                'duration_minutes' => 2000,
                'notification_emails' => [fake()->email()],
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['duration_minutes']);

        // Act - duration too low
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/monitoring/monitors", [
                'name' => 'Invalid Monitor',
                'metric_type' => 'cpu',
                'operator' => '>',
                'threshold' => 90,
                'duration_minutes' => 0,
                'notification_emails' => [fake()->email()],
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['duration_minutes']);
    }

    /**
     * Test monitor creation validates notification emails.
     */
    public function test_monitor_creation_validates_notification_emails(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act - empty array
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/monitoring/monitors", [
                'name' => 'Invalid Monitor',
                'metric_type' => 'cpu',
                'operator' => '>',
                'threshold' => 90,
                'duration_minutes' => 15,
                'notification_emails' => [],
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['notification_emails']);

        // Act - invalid email format
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/monitoring/monitors", [
                'name' => 'Invalid Monitor',
                'metric_type' => 'cpu',
                'operator' => '>',
                'threshold' => 90,
                'duration_minutes' => 15,
                'notification_emails' => ['not-an-email'],
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['notification_emails.0']);

        // Act - too many emails
        $emails = [];
        for ($i = 0; $i < 11; $i++) {
            $emails[] = fake()->email();
        }

        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/monitoring/monitors", [
                'name' => 'Invalid Monitor',
                'metric_type' => 'cpu',
                'operator' => '>',
                'threshold' => 90,
                'duration_minutes' => 15,
                'notification_emails' => $emails,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['notification_emails']);
    }

    /**
     * Test user can update monitor.
     */
    public function test_user_can_update_monitor(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $monitor = ServerMonitor::factory()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'name' => 'Old Name',
            'threshold' => 80,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->put("/servers/{$server->id}/monitoring/monitors/{$monitor->id}", [
                'name' => 'Updated Name',
                'threshold' => 95,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/monitoring");
        $response->assertSessionHas('success', 'Monitor updated successfully');

        $this->assertDatabaseHas('server_monitors', [
            'id' => $monitor->id,
            'name' => 'Updated Name',
            'threshold' => 95.00,
        ]);
    }

    /**
     * Test user cannot update other users monitor.
     */
    public function test_user_cannot_update_other_users_monitor(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $monitor = ServerMonitor::factory()->create([
            'user_id' => $otherUser->id,
            'server_id' => $server->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->put("/servers/{$server->id}/monitoring/monitors/{$monitor->id}", [
                'name' => 'Hacked Name',
            ]);

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test update validates updated fields.
     */
    public function test_update_validates_updated_fields(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $monitor = ServerMonitor::factory()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
        ]);

        // Act - invalid threshold
        $response = $this->actingAs($user)
            ->put("/servers/{$server->id}/monitoring/monitors/{$monitor->id}", [
                'threshold' => 150,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['threshold']);
    }

    /**
     * Test user can delete their monitor.
     */
    public function test_user_can_delete_their_monitor(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $monitor = ServerMonitor::factory()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/monitoring/monitors/{$monitor->id}");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/monitoring");
        $response->assertSessionHas('success', 'Monitor deleted successfully');

        $this->assertDatabaseMissing('server_monitors', [
            'id' => $monitor->id,
        ]);
    }

    /**
     * Test user cannot delete other users monitor.
     */
    public function test_user_cannot_delete_other_users_monitor(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $monitor = ServerMonitor::factory()->create([
            'user_id' => $otherUser->id,
            'server_id' => $server->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/monitoring/monitors/{$monitor->id}");

        // Assert
        $response->assertStatus(403);

        $this->assertDatabaseHas('server_monitors', [
            'id' => $monitor->id,
        ]);
    }

    /**
     * Test guest cannot delete monitor.
     */
    public function test_guest_cannot_delete_monitor(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $monitor = ServerMonitor::factory()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
        ]);

        // Act
        $response = $this->delete("/servers/{$server->id}/monitoring/monitors/{$monitor->id}");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test user can toggle monitor enabled status.
     */
    public function test_user_can_toggle_monitor_enabled_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $monitor = ServerMonitor::factory()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'enabled' => true,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->postJson("/servers/{$server->id}/monitoring/monitors/{$monitor->id}/toggle");

        // Assert
        $response->assertStatus(200);
        $response->assertJsonPath('monitor.enabled', false);

        $this->assertDatabaseHas('server_monitors', [
            'id' => $monitor->id,
            'enabled' => false,
        ]);
    }

    /**
     * Test toggle switches from disabled to enabled.
     */
    public function test_toggle_switches_from_disabled_to_enabled(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $monitor = ServerMonitor::factory()->disabled()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->postJson("/servers/{$server->id}/monitoring/monitors/{$monitor->id}/toggle");

        // Assert
        $response->assertStatus(200);
        $response->assertJsonPath('monitor.enabled', true);

        $this->assertDatabaseHas('server_monitors', [
            'id' => $monitor->id,
            'enabled' => true,
        ]);
    }

    /**
     * Test user cannot toggle other users monitor.
     */
    public function test_user_cannot_toggle_other_users_monitor(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $monitor = ServerMonitor::factory()->create([
            'user_id' => $otherUser->id,
            'server_id' => $server->id,
            'enabled' => true,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->postJson("/servers/{$server->id}/monitoring/monitors/{$monitor->id}/toggle");

        // Assert
        $response->assertStatus(403);

        $this->assertDatabaseHas('server_monitors', [
            'id' => $monitor->id,
            'enabled' => true,
        ]);
    }

    /**
     * Test guest cannot toggle monitor.
     */
    public function test_guest_cannot_toggle_monitor(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $monitor = ServerMonitor::factory()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
        ]);

        // Act
        $response = $this->postJson("/servers/{$server->id}/monitoring/monitors/{$monitor->id}/toggle");

        // Assert
        $response->assertStatus(401);
    }
}
