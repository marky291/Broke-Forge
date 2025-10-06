<?php

namespace Tests\Feature;

use App\Enums\MonitoringStatus;
use App\Models\Server;
use App\Models\ServerMetric;
use App\Models\ServerMonitoring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_monitoring_page(): void
    {
        $server = Server::factory()->create();

        $this->get(route('servers.monitoring', $server))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_monitoring_page(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create();

        $this->actingAs($user)
            ->get(route('servers.monitoring', $server))
            ->assertOk();
    }

    public function test_monitoring_page_shows_not_installed_state(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('servers.monitoring', $server));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('servers/monitoring')
            ->where('monitoring', null)
        );
    }

    public function test_monitoring_page_shows_active_monitoring(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create();
        $monitoring = ServerMonitoring::factory()->create([
            'server_id' => $server->id,
            'status' => MonitoringStatus::Active,
        ]);

        $response = $this->actingAs($user)
            ->get(route('servers.monitoring', $server));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('servers/monitoring')
            ->where('monitoring.status', 'active')
        );
    }

    public function test_install_monitoring_dispatches_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create();

        $this->actingAs($user)
            ->post(route('servers.monitoring.install', $server))
            ->assertRedirect(route('servers.monitoring', $server))
            ->assertSessionHas('success');

        Queue::assertPushed(\App\Packages\Services\Monitoring\ServerMonitoringInstallerJob::class);
    }

    public function test_cannot_install_monitoring_when_already_active(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create();
        ServerMonitoring::factory()->create([
            'server_id' => $server->id,
            'status' => MonitoringStatus::Active,
        ]);

        $this->actingAs($user)
            ->post(route('servers.monitoring.install', $server))
            ->assertRedirect(route('servers.monitoring', $server))
            ->assertSessionHas('error');

        Queue::assertNothingPushed();
    }

    public function test_uninstall_monitoring_dispatches_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create();
        ServerMonitoring::factory()->create([
            'server_id' => $server->id,
            'status' => MonitoringStatus::Active,
        ]);

        $this->actingAs($user)
            ->post(route('servers.monitoring.uninstall', $server))
            ->assertRedirect(route('servers.monitoring', $server))
            ->assertSessionHas('success');

        Queue::assertPushed(\App\Packages\Services\Monitoring\ServerMonitoringRemoverJob::class);
    }

    public function test_cannot_uninstall_monitoring_when_not_active(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create();

        $this->actingAs($user)
            ->post(route('servers.monitoring.uninstall', $server))
            ->assertRedirect(route('servers.monitoring', $server))
            ->assertSessionHas('error');

        Queue::assertNothingPushed();
    }

    public function test_metrics_api_requires_authentication_token(): void
    {
        $server = Server::factory()->create();

        $this->postJson(route('api.servers.metrics.store', $server), [
            'cpu_usage' => 50.0,
            'memory_total_mb' => 8000,
            'memory_used_mb' => 4000,
            'memory_usage_percentage' => 50.0,
            'storage_total_gb' => 100,
            'storage_used_gb' => 50,
            'storage_usage_percentage' => 50.0,
            'collected_at' => now()->toISOString(),
        ])->assertUnauthorized();
    }

    public function test_metrics_api_rejects_invalid_token(): void
    {
        $server = Server::factory()->create([
            'monitoring_token' => 'valid-token',
        ]);

        $this->postJson(route('api.servers.metrics.store', $server), [
            'cpu_usage' => 50.0,
            'memory_total_mb' => 8000,
            'memory_used_mb' => 4000,
            'memory_usage_percentage' => 50.0,
            'storage_total_gb' => 100,
            'storage_used_gb' => 50,
            'storage_usage_percentage' => 50.0,
            'collected_at' => now()->toISOString(),
        ], [
            'X-Monitoring-Token' => 'invalid-token',
        ])->assertUnauthorized();
    }

    public function test_metrics_api_stores_valid_metrics(): void
    {
        $server = Server::factory()->create([
            'monitoring_token' => 'valid-token',
        ]);

        $this->postJson(route('api.servers.metrics.store', $server), [
            'cpu_usage' => 50.5,
            'memory_total_mb' => 8000,
            'memory_used_mb' => 4000,
            'memory_usage_percentage' => 50.0,
            'storage_total_gb' => 100,
            'storage_used_gb' => 50,
            'storage_usage_percentage' => 50.0,
            'collected_at' => now()->toISOString(),
        ], [
            'X-Monitoring-Token' => 'valid-token',
        ])->assertCreated();

        $this->assertDatabaseHas('server_metrics', [
            'server_id' => $server->id,
            'cpu_usage' => 50.5,
        ]);
    }

    public function test_metrics_api_validates_input(): void
    {
        $server = Server::factory()->create([
            'monitoring_token' => 'valid-token',
        ]);

        $this->postJson(route('api.servers.metrics.store', $server), [
            'cpu_usage' => 150.0, // Invalid: > 100
            'memory_total_mb' => 'invalid', // Invalid: not integer
        ], [
            'X-Monitoring-Token' => 'valid-token',
        ])->assertUnprocessable();
    }

    public function test_get_metrics_returns_filtered_data(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create();

        // Create metrics at different times
        ServerMetric::factory()->create([
            'server_id' => $server->id,
            'collected_at' => now()->subHours(48),
        ]);
        ServerMetric::factory()->create([
            'server_id' => $server->id,
            'collected_at' => now()->subHours(12),
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('servers.monitoring.metrics', ['server' => $server, 'hours' => 24]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data'); // Only 1 within 24 hours
    }

    public function test_metrics_are_returned_with_api_resource_format(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create();
        $metric = ServerMetric::factory()->create([
            'server_id' => $server->id,
            'cpu_usage' => 75.5,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('servers.monitoring.metrics', ['server' => $server]));

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'server_id',
                    'cpu_usage',
                    'memory_total_mb',
                    'memory_used_mb',
                    'memory_usage_percentage',
                    'storage_total_gb',
                    'storage_used_gb',
                    'storage_usage_percentage',
                    'collected_at',
                    'created_at',
                ],
            ],
        ]);
    }

    public function test_get_metrics_validates_timeframe_parameter(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create();

        // Test invalid timeframe (not in allowed values)
        $response = $this->actingAs($user)
            ->getJson(route('servers.monitoring.metrics', ['server' => $server, 'hours' => 48]));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['hours']);

        // Test valid timeframes
        $validTimeframes = [1, 24, 72, 168];
        foreach ($validTimeframes as $hours) {
            $response = $this->actingAs($user)
                ->getJson(route('servers.monitoring.metrics', ['server' => $server, 'hours' => $hours]));

            $response->assertOk();
        }
    }

    public function test_monitoring_index_validates_timeframe_parameter(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create();

        // Test invalid timeframe (not in allowed values)
        $response = $this->actingAs($user)
            ->get(route('servers.monitoring', ['server' => $server, 'hours' => 999]));

        $response->assertSessionHasErrors(['hours']);

        // Test valid timeframes
        $validTimeframes = [24, 72, 168];
        foreach ($validTimeframes as $hours) {
            $response = $this->actingAs($user)
                ->get(route('servers.monitoring', ['server' => $server, 'hours' => $hours]));

            $response->assertOk();
        }
    }
}
