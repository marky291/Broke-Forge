<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\MonitoringStatus;
use App\Models\Server;
use App\Models\ServerMetric;
use App\Models\User;
use App\Packages\Services\Monitoring\ServerMonitoringInstallerJob;
use App\Packages\Services\Monitoring\ServerMonitoringRemoverJob;
use App\Packages\Services\Monitoring\ServerMonitoringTimerUpdaterJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerMonitoringControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can view monitoring page for their server.
     */
    public function test_user_can_view_monitoring_page(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'monitoring_status' => MonitoringStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/monitoring");

        // Assert
        $response->assertStatus(200);
    }

    /**
     * Test user cannot view monitoring page for another users server.
     */
    public function test_user_cannot_view_monitoring_page_for_another_users_server(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $otherUser = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/monitoring");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test guest cannot view monitoring page.
     */
    public function test_guest_cannot_view_monitoring_page(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->get("/servers/{$server->id}/monitoring");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test monitoring page accepts valid timeframe parameter.
     */
    public function test_monitoring_page_accepts_valid_timeframe_parameter(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'monitoring_status' => MonitoringStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/monitoring?hours=72");

        // Assert
        $response->assertStatus(200);
    }

    /**
     * Test monitoring page rejects invalid timeframe with validation error.
     */
    public function test_monitoring_page_rejects_invalid_timeframe(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'monitoring_status' => MonitoringStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/monitoring?hours=999");

        // Assert - Validation fails and redirects back
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['hours']);
    }

    /**
     * Test user can install monitoring on their server.
     */
    public function test_user_can_install_monitoring(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'monitoring_status' => null,
        ]);

        // Act
        $response = $this->actingAs($user)->post("/servers/{$server->id}/monitoring/install");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/monitoring");
        $response->assertSessionHas('success', 'Monitoring installation started');

        // Verify status updated
        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'monitoring_status' => MonitoringStatus::Installing->value,
        ]);

        // Verify job dispatched
        Queue::assertPushed(ServerMonitoringInstallerJob::class);
    }

    /**
     * Test user cannot install monitoring if already installed.
     */
    public function test_user_cannot_install_monitoring_if_already_installed(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'monitoring_status' => MonitoringStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)->post("/servers/{$server->id}/monitoring/install");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/monitoring");
        $response->assertSessionHas('error', 'Monitoring is already installed on this server');

        // Verify job not dispatched
        Queue::assertNotPushed(ServerMonitoringInstallerJob::class);
    }

    /**
     * Test user cannot install monitoring on another users server.
     */
    public function test_user_cannot_install_monitoring_on_another_users_server(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $otherUser = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        // Act
        $response = $this->actingAs($user)->post("/servers/{$server->id}/monitoring/install");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user can uninstall monitoring from their server.
     */
    public function test_user_can_uninstall_monitoring(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'monitoring_status' => MonitoringStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)->post("/servers/{$server->id}/monitoring/uninstall");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/monitoring");
        $response->assertSessionHas('success', 'Monitoring uninstallation started');

        // Verify status updated
        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'monitoring_status' => MonitoringStatus::Uninstalling->value,
        ]);

        // Verify job dispatched
        Queue::assertPushed(ServerMonitoringRemoverJob::class);
    }

    /**
     * Test user cannot uninstall monitoring if not installed.
     */
    public function test_user_cannot_uninstall_monitoring_if_not_installed(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'monitoring_status' => null,
        ]);

        // Act
        $response = $this->actingAs($user)->post("/servers/{$server->id}/monitoring/uninstall");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/monitoring");
        $response->assertSessionHas('error', 'Monitoring is not installed on this server');

        // Verify job not dispatched
        Queue::assertNotPushed(ServerMonitoringRemoverJob::class);
    }

    /**
     * Test user can update monitoring collection interval.
     */
    public function test_user_can_update_monitoring_collection_interval(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'monitoring_status' => MonitoringStatus::Active,
            'monitoring_collection_interval' => 300,
        ]);

        // Act
        $response = $this->actingAs($user)->post("/servers/{$server->id}/monitoring/update-interval", [
            'interval' => 600,
            'hours' => 24,
        ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/monitoring?hours=24");
        $response->assertSessionHas('success', 'Monitoring collection interval update started');

        // Verify job dispatched with correct interval
        Queue::assertPushed(ServerMonitoringTimerUpdaterJob::class, function ($job) use ($server) {
            return $job->server->id === $server->id;
        });
    }

    /**
     * Test update interval validates allowed values.
     */
    public function test_update_interval_validates_allowed_values(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'monitoring_status' => MonitoringStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)->post("/servers/{$server->id}/monitoring/update-interval", [
            'interval' => 999,
            'hours' => 24,
        ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['interval']);
    }

    /**
     * Test update interval requires monitoring to be active.
     */
    public function test_update_interval_requires_monitoring_to_be_active(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'monitoring_status' => null,
        ]);

        // Act
        $response = $this->actingAs($user)->post("/servers/{$server->id}/monitoring/update-interval", [
            'interval' => 600,
        ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('error', 'Monitoring must be active to update collection interval');

        // Verify job not dispatched
        Queue::assertNotPushed(ServerMonitoringTimerUpdaterJob::class);
    }

    /**
     * Test update interval preserves timeframe parameter.
     */
    public function test_update_interval_preserves_timeframe_parameter(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'monitoring_status' => MonitoringStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)->post("/servers/{$server->id}/monitoring/update-interval", [
            'interval' => 600,
            'hours' => 72,
        ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/monitoring?hours=72");
    }

    /**
     * Test authenticated user can store metrics via API.
     */
    public function test_authenticated_server_can_store_metrics(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'monitoring_status' => MonitoringStatus::Active,
            'monitoring_token' => 'test-token-12345',
        ]);

        $metricsData = [
            'cpu_usage' => 45.5,
            'memory_total_mb' => 8192,
            'memory_used_mb' => 4096,
            'memory_usage_percentage' => 50.0,
            'storage_total_gb' => 100,
            'storage_used_gb' => 50,
            'storage_usage_percentage' => 50.0,
            'collected_at' => now()->toDateTimeString(),
        ];

        // Act
        $response = $this->postJson("/api/servers/{$server->id}/metrics", $metricsData, [
            'X-Monitoring-Token' => 'test-token-12345',
        ]);

        // Assert
        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Metrics stored successfully',
        ]);

        // Verify database
        $this->assertDatabaseHas('server_metrics', [
            'server_id' => $server->id,
            'cpu_usage' => 45.5,
            'memory_used_mb' => 4096,
        ]);
    }

    /**
     * Test get metrics returns data for specified timeframe.
     */
    public function test_get_metrics_returns_data_for_timeframe(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'monitoring_status' => MonitoringStatus::Active,
        ]);

        // Create metrics at different times
        ServerMetric::factory()->create([
            'server_id' => $server->id,
            'collected_at' => now()->subHours(2),
        ]);
        ServerMetric::factory()->create([
            'server_id' => $server->id,
            'collected_at' => now()->subHours(48),
        ]);

        // Act - request last 24 hours
        $response = $this->actingAs($user)->getJson("/servers/{$server->id}/monitoring/metrics?hours=24");

        // Assert
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonCount(1, 'data');
    }

    /**
     * Test get metrics validates timeframe parameter.
     */
    public function test_get_metrics_validates_timeframe_parameter(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'monitoring_status' => MonitoringStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)->getJson("/servers/{$server->id}/monitoring/metrics?hours=999");

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['hours']);
    }

    /**
     * Test get metrics defaults to 24 hours.
     */
    public function test_get_metrics_defaults_to_24_hours(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'monitoring_status' => MonitoringStatus::Active,
        ]);

        ServerMetric::factory()->create([
            'server_id' => $server->id,
            'collected_at' => now()->subHours(2),
        ]);

        // Act - no hours parameter
        $response = $this->actingAs($user)->getJson("/servers/{$server->id}/monitoring/metrics");

        // Assert
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }
}
