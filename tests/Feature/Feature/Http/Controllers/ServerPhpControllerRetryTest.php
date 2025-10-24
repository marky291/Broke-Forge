<?php

namespace Tests\Feature\Feature\Http\Controllers;

use App\Enums\PhpStatus;
use App\Models\Server;
use App\Models\ServerPhp;
use App\Models\User;
use App\Packages\Services\PHP\PhpInstallerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerPhpControllerRetryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can retry a failed PHP installation.
     */
    public function test_user_can_retry_failed_php_installation(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'status' => PhpStatus::Failed,
            'error_log' => 'Installation failed',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php/{$php->id}/retry");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect();

        // Verify PHP status reset to pending
        $this->assertDatabaseHas('server_phps', [
            'id' => $php->id,
            'status' => PhpStatus::Pending->value,
            'error_log' => null,
        ]);

        // Verify job was dispatched
        Queue::assertPushed(PhpInstallerJob::class, function ($job) use ($server, $php) {
            return $job->server->id === $server->id
                && $job->serverPhp->id === $php->id;
        });
    }

    /**
     * Test cannot retry PHP that is not failed.
     */
    public function test_cannot_retry_php_that_is_not_failed(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'status' => PhpStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php/{$php->id}/retry");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('error', 'Only failed PHP installations can be retried');

        // Verify status was not changed
        $this->assertDatabaseHas('server_phps', [
            'id' => $php->id,
            'status' => PhpStatus::Active->value,
        ]);

        // Verify no job was dispatched
        Queue::assertNothingPushed();
    }

    /**
     * Test user cannot retry PHP on another user's server.
     */
    public function test_user_cannot_retry_php_on_another_users_server(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);
        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'status' => PhpStatus::Failed,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php/{$php->id}/retry");

        // Assert
        $response->assertStatus(403);

        // Verify status was not changed
        $this->assertDatabaseHas('server_phps', [
            'id' => $php->id,
            'status' => PhpStatus::Failed->value,
        ]);

        Queue::assertNothingPushed();
    }

    /**
     * Test guest cannot retry PHP installation.
     */
    public function test_guest_cannot_retry_php_installation(): void
    {
        // Arrange
        Queue::fake();
        $server = Server::factory()->create();
        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'status' => PhpStatus::Failed,
        ]);

        // Act
        $response = $this->post("/servers/{$server->id}/php/{$php->id}/retry");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
        Queue::assertNothingPushed();
    }

    /**
     * Test retry returns 404 when PHP does not belong to server.
     */
    public function test_retry_returns_404_when_php_does_not_belong_to_server(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server1 = Server::factory()->create(['user_id' => $user->id]);
        $server2 = Server::factory()->create(['user_id' => $user->id]);
        $php = ServerPhp::factory()->create([
            'server_id' => $server2->id,
            'status' => PhpStatus::Failed,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server1->id}/php/{$php->id}/retry");

        // Assert
        $response->assertStatus(404);
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
        $server = Server::factory()->create(['user_id' => $user->id]);
        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'status' => PhpStatus::Failed,
        ]);

        // Act
        $this->actingAs($user)
            ->post("/servers/{$server->id}/php/{$php->id}/retry");

        // Assert
        Log::shouldHaveReceived('info')
            ->once()
            ->with('PHP installation retry initiated', \Mockery::on(function ($context) use ($user, $server, $php) {
                return $context['user_id'] === $user->id
                    && $context['server_id'] === $server->id
                    && $context['php_id'] === $php->id
                    && $context['php_version'] === '8.3';
            }));

        // PHPUnit assertion to avoid risky test warning
        $this->assertTrue(true);
    }

    /**
     * Test retry clears error log when resetting status.
     */
    public function test_retry_clears_error_log_when_resetting_status(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'status' => PhpStatus::Failed,
            'error_log' => 'Previous error message',
        ]);

        // Act
        $this->actingAs($user)
            ->post("/servers/{$server->id}/php/{$php->id}/retry");

        // Assert
        $this->assertDatabaseHas('server_phps', [
            'id' => $php->id,
            'status' => PhpStatus::Pending->value,
            'error_log' => null,
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
        $server = Server::factory()->create(['user_id' => $user->id]);
        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.4',
            'status' => PhpStatus::Failed,
        ]);

        // Act
        $this->actingAs($user)
            ->post("/servers/{$server->id}/php/{$php->id}/retry");

        // Assert
        Queue::assertPushed(PhpInstallerJob::class, function ($job) use ($server, $php) {
            return $job->server->id === $server->id
                && $job->serverPhp->id === $php->id;
        });
    }
}
