<?php

namespace Tests\Unit\Packages\Services\Monitoring\Commands;

use App\Models\Server;
use App\Models\ServerMetric;
use App\Models\ServerMonitor;
use App\Models\User;
use App\Packages\Services\Monitoring\Commands\EvaluateServerMonitorsCommand;
use App\Packages\Services\Monitoring\Events\MonitorRecoveredEvent;
use App\Packages\Services\Monitoring\Events\MonitorTriggeredEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EvaluateServerMonitorsCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test command runs successfully with no monitors.
     */
    public function test_command_runs_successfully_with_no_monitors(): void
    {
        // Act
        $this->artisan(EvaluateServerMonitorsCommand::class)
            ->expectsOutput('Evaluating 0 enabled monitor(s)...')
            ->expectsOutput('Evaluation complete. Triggered: 0, Recovered: 0')
            ->assertSuccessful();
    }

    /**
     * Test command evaluates enabled monitors only.
     */
    public function test_command_evaluates_enabled_monitors_only(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerMonitor::factory()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'enabled' => true,
        ]);
        ServerMonitor::factory()->disabled()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
        ]);

        // Act
        $this->artisan(EvaluateServerMonitorsCommand::class)
            ->expectsOutput('Evaluating 1 enabled monitor(s)...')
            ->assertSuccessful();
    }

    /**
     * Test command triggers monitor when condition is met.
     */
    public function test_command_triggers_monitor_when_condition_is_met(): void
    {
        // Arrange
        Event::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $monitor = ServerMonitor::factory()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'metric_type' => 'cpu',
            'operator' => '>',
            'threshold' => 80,
            'duration_minutes' => 10,
            'status' => 'normal',
        ]);

        // Create metrics that exceed threshold
        ServerMetric::factory()->count(3)->create([
            'server_id' => $server->id,
            'cpu_usage' => 85.0,
            'collected_at' => now()->subMinutes(5),
        ]);

        // Act
        $this->artisan(EvaluateServerMonitorsCommand::class)
            ->assertSuccessful();

        // Assert
        $monitor->refresh();
        $this->assertEquals('triggered', $monitor->status);
        $this->assertNotNull($monitor->last_triggered_at);

        Event::assertDispatched(MonitorTriggeredEvent::class, function ($event) use ($monitor) {
            return $event->monitor->id === $monitor->id && $event->currentValue > 80;
        });
    }

    /**
     * Test command does not trigger when condition is not met for all metrics.
     */
    public function test_command_does_not_trigger_when_condition_not_met_for_all_metrics(): void
    {
        // Arrange
        Event::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $monitor = ServerMonitor::factory()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'metric_type' => 'cpu',
            'operator' => '>',
            'threshold' => 80,
            'duration_minutes' => 10,
            'status' => 'normal',
        ]);

        // Create metrics where only some exceed threshold
        ServerMetric::factory()->create([
            'server_id' => $server->id,
            'cpu_usage' => 85.0,
            'collected_at' => now()->subMinutes(5),
        ]);
        ServerMetric::factory()->create([
            'server_id' => $server->id,
            'cpu_usage' => 70.0,
            'collected_at' => now()->subMinutes(3),
        ]);

        // Act
        $this->artisan(EvaluateServerMonitorsCommand::class)
            ->assertSuccessful();

        // Assert
        $monitor->refresh();
        $this->assertEquals('normal', $monitor->status);
        $this->assertNull($monitor->last_triggered_at);

        Event::assertNotDispatched(MonitorTriggeredEvent::class);
    }

    /**
     * Test command recovers monitor when condition is no longer met.
     */
    public function test_command_recovers_monitor_when_condition_no_longer_met(): void
    {
        // Arrange
        Event::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $monitor = ServerMonitor::factory()->triggered()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'metric_type' => 'cpu',
            'operator' => '>',
            'threshold' => 80,
            'duration_minutes' => 10,
            'cooldown_minutes' => 60,
            'last_triggered_at' => now()->subMinutes(61), // Cooldown has ended
        ]);

        // Create metrics that are below threshold
        ServerMetric::factory()->count(3)->create([
            'server_id' => $server->id,
            'cpu_usage' => 70.0,
            'collected_at' => now()->subMinutes(5),
        ]);

        // Act
        $this->artisan(EvaluateServerMonitorsCommand::class)
            ->assertSuccessful();

        // Assert
        $monitor->refresh();
        $this->assertEquals('normal', $monitor->status);
        $this->assertNotNull($monitor->last_recovered_at);

        Event::assertDispatched(MonitorRecoveredEvent::class, function ($event) use ($monitor) {
            return $event->monitor->id === $monitor->id && $event->currentValue < 80;
        });
    }

    /**
     * Test command respects cooldown period.
     */
    public function test_command_respects_cooldown_period(): void
    {
        // Arrange
        Event::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $monitor = ServerMonitor::factory()->triggered()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'metric_type' => 'cpu',
            'operator' => '>',
            'threshold' => 80,
            'duration_minutes' => 10,
            'cooldown_minutes' => 60,
            'last_triggered_at' => now()->subMinutes(30), // Still in cooldown
        ]);

        // Create metrics that exceed threshold
        ServerMetric::factory()->count(3)->create([
            'server_id' => $server->id,
            'cpu_usage' => 85.0,
            'collected_at' => now()->subMinutes(5),
        ]);

        // Act
        $this->artisan(EvaluateServerMonitorsCommand::class)
            ->assertSuccessful();

        // Assert - monitor should not be re-triggered
        $monitor->refresh();
        $this->assertEquals('triggered', $monitor->status);

        Event::assertNotDispatched(MonitorTriggeredEvent::class);
    }

    /**
     * Test command evaluates after cooldown period ends.
     */
    public function test_command_evaluates_after_cooldown_period_ends(): void
    {
        // Arrange
        Event::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $monitor = ServerMonitor::factory()->triggered()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'metric_type' => 'cpu',
            'operator' => '>',
            'threshold' => 80,
            'duration_minutes' => 10,
            'cooldown_minutes' => 60,
            'last_triggered_at' => now()->subMinutes(61), // Cooldown ended
        ]);

        // Create metrics that are below threshold
        ServerMetric::factory()->count(3)->create([
            'server_id' => $server->id,
            'cpu_usage' => 70.0,
            'collected_at' => now()->subMinutes(5),
        ]);

        // Act
        $this->artisan(EvaluateServerMonitorsCommand::class)
            ->assertSuccessful();

        // Assert - monitor should recover
        $monitor->refresh();
        $this->assertEquals('normal', $monitor->status);

        Event::assertDispatched(MonitorRecoveredEvent::class);
    }

    /**
     * Test command handles memory metric type.
     */
    public function test_command_handles_memory_metric_type(): void
    {
        // Arrange
        Event::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $monitor = ServerMonitor::factory()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'metric_type' => 'memory',
            'operator' => '>=',
            'threshold' => 90,
            'duration_minutes' => 10,
            'status' => 'normal',
        ]);

        // Create metrics that exceed threshold
        ServerMetric::factory()->count(3)->create([
            'server_id' => $server->id,
            'memory_usage_percentage' => 92.0,
            'collected_at' => now()->subMinutes(5),
        ]);

        // Act
        $this->artisan(EvaluateServerMonitorsCommand::class)
            ->assertSuccessful();

        // Assert
        $monitor->refresh();
        $this->assertEquals('triggered', $monitor->status);

        Event::assertDispatched(MonitorTriggeredEvent::class);
    }

    /**
     * Test command handles storage metric type.
     */
    public function test_command_handles_storage_metric_type(): void
    {
        // Arrange
        Event::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $monitor = ServerMonitor::factory()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'metric_type' => 'storage',
            'operator' => '>',
            'threshold' => 85,
            'duration_minutes' => 10,
            'status' => 'normal',
        ]);

        // Create metrics that exceed threshold
        ServerMetric::factory()->count(3)->create([
            'server_id' => $server->id,
            'storage_usage_percentage' => 88.0,
            'collected_at' => now()->subMinutes(5),
        ]);

        // Act
        $this->artisan(EvaluateServerMonitorsCommand::class)
            ->assertSuccessful();

        // Assert
        $monitor->refresh();
        $this->assertEquals('triggered', $monitor->status);

        Event::assertDispatched(MonitorTriggeredEvent::class);
    }

    /**
     * Test command handles less than operator.
     */
    public function test_command_handles_less_than_operator(): void
    {
        // Arrange
        Event::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $monitor = ServerMonitor::factory()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'metric_type' => 'cpu',
            'operator' => '<',
            'threshold' => 20,
            'duration_minutes' => 10,
            'status' => 'normal',
        ]);

        // Create metrics below threshold
        ServerMetric::factory()->count(3)->create([
            'server_id' => $server->id,
            'cpu_usage' => 15.0,
            'collected_at' => now()->subMinutes(5),
        ]);

        // Act
        $this->artisan(EvaluateServerMonitorsCommand::class)
            ->assertSuccessful();

        // Assert
        $monitor->refresh();
        $this->assertEquals('triggered', $monitor->status);

        Event::assertDispatched(MonitorTriggeredEvent::class);
    }

    /**
     * Test command skips evaluation when no metrics available.
     */
    public function test_command_skips_evaluation_when_no_metrics_available(): void
    {
        // Arrange
        Event::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $monitor = ServerMonitor::factory()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'metric_type' => 'cpu',
            'operator' => '>',
            'threshold' => 80,
            'duration_minutes' => 10,
            'status' => 'normal',
        ]);

        // No metrics created

        // Act
        $this->artisan(EvaluateServerMonitorsCommand::class)
            ->assertSuccessful();

        // Assert
        $monitor->refresh();
        $this->assertEquals('normal', $monitor->status);

        Event::assertNotDispatched(MonitorTriggeredEvent::class);
    }

    /**
     * Test command output shows triggered and recovered counts.
     */
    public function test_command_output_shows_triggered_and_recovered_counts(): void
    {
        // Arrange
        Event::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Monitor to trigger
        $normalMonitor = ServerMonitor::factory()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'metric_type' => 'cpu',
            'operator' => '>',
            'threshold' => 80,
            'duration_minutes' => 10,
            'status' => 'normal',
        ]);

        // Monitor to recover (cooldown has ended)
        $triggeredMonitor = ServerMonitor::factory()->triggered()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'metric_type' => 'memory',
            'operator' => '>',
            'threshold' => 80,
            'duration_minutes' => 10,
            'cooldown_minutes' => 60,
            'last_triggered_at' => now()->subMinutes(61),
        ]);

        // High CPU metrics for first monitor
        ServerMetric::factory()->count(3)->create([
            'server_id' => $server->id,
            'cpu_usage' => 85.0,
            'memory_usage_percentage' => 70.0,
            'collected_at' => now()->subMinutes(5),
        ]);

        // Act
        $this->artisan(EvaluateServerMonitorsCommand::class)
            ->expectsOutput('Evaluating 2 enabled monitor(s)...')
            ->expectsOutput('Evaluation complete. Triggered: 1, Recovered: 1')
            ->assertSuccessful();
    }
}
