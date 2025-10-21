<?php

namespace Tests\Feature\Feature\Http\Controllers;

use App\Enums\MonitoringStatus;
use App\Models\Server;
use App\Models\User;
use App\Packages\Services\Monitoring\ServerMonitoringInstallerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerMonitoringControllerRetryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can retry a failed monitoring installation.
     */
    public function test_user_can_retry_failed_monitoring_installation(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'monitoring_status' => MonitoringStatus::Failed,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/monitoring/retry");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect();

        // Verify monitoring status reset to installing
        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'monitoring_status' => MonitoringStatus::Installing->value,
        ]);

        // Verify job was dispatched
        Queue::assertPushed(ServerMonitoringInstallerJob::class, function ($job) use ($server) {
            return $job->server->id === $server->id;
        });
    }

    /**
     * Test cannot retry monitoring that is not failed.
     */
    public function test_cannot_retry_monitoring_that_is_not_failed(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'monitoring_status' => MonitoringStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/monitoring/retry");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('error', 'Only failed monitoring installations can be retried');

        // Verify status was not changed
        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'monitoring_status' => MonitoringStatus::Active->value,
        ]);

        // Verify no job was dispatched
        Queue::assertNothingPushed();
    }

    /**
     * Test user cannot retry monitoring on another user's server.
     */
    public function test_user_cannot_retry_monitoring_on_another_users_server(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $otherUser->id,
            'monitoring_status' => MonitoringStatus::Failed,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/monitoring/retry");

        // Assert
        $response->assertStatus(403);

        // Verify status was not changed
        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'monitoring_status' => MonitoringStatus::Failed->value,
        ]);

        Queue::assertNothingPushed();
    }

    /**
     * Test guest cannot retry monitoring installation.
     */
    public function test_guest_cannot_retry_monitoring_installation(): void
    {
        // Arrange
        Queue::fake();
        $server = Server::factory()->create([
            'monitoring_status' => MonitoringStatus::Failed,
        ]);

        // Act
        $response = $this->post("/servers/{$server->id}/monitoring/retry");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
        Queue::assertNothingPushed();
    }

    /**
     * Test retry logs audit information.
     */
    public function test_retry_logs_audit_information(): void
    {
        // Arrange
        Log::spy();
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'monitoring_status' => MonitoringStatus::Failed,
        ]);

        // Act
        $this->actingAs($user)
            ->post("/servers/{$server->id}/monitoring/retry");

        // Assert
        Log::shouldHaveReceived('info')
            ->once()
            ->with('Monitoring installation retry initiated', \Mockery::on(function ($context) use ($user, $server) {
                return $context['user_id'] === $user->id
                    && $context['server_id'] === $server->id;
            }));

        // PHPUnit assertion to avoid risky test warning
        $this->assertTrue(true);
    }

    /**
     * Test retry resets status to installing.
     */
    public function test_retry_resets_status_to_installing(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'monitoring_status' => MonitoringStatus::Failed,
        ]);

        // Act
        $this->actingAs($user)
            ->post("/servers/{$server->id}/monitoring/retry");

        // Assert
        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'monitoring_status' => MonitoringStatus::Installing->value,
        ]);
    }

    /**
     * Test retry dispatches installer job with correct parameters.
     */
    public function test_retry_dispatches_installer_job_with_correct_parameters(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'monitoring_status' => MonitoringStatus::Failed,
        ]);

        // Act
        $this->actingAs($user)
            ->post("/servers/{$server->id}/monitoring/retry");

        // Assert
        Queue::assertPushed(ServerMonitoringInstallerJob::class, function ($job) use ($server) {
            return $job->server->id === $server->id;
        });
    }
}
