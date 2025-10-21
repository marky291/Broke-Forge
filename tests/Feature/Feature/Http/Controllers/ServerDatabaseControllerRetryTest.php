<?php

namespace Tests\Feature\Feature\Http\Controllers;

use App\Enums\DatabaseStatus;
use App\Enums\DatabaseType;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Models\User;
use App\Packages\Services\Database\MariaDB\MariaDbInstallerJob;
use App\Packages\Services\Database\MySQL\MySqlInstallerJob;
use App\Packages\Services\Database\PostgreSQL\PostgreSqlInstallerJob;
use App\Packages\Services\Database\Redis\RedisInstallerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerDatabaseControllerRetryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can retry a failed database installation.
     */
    public function test_user_can_retry_failed_database_installation(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => DatabaseStatus::Failed,
            'error_log' => 'Installation failed',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/retry");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect();

        // Verify database status reset to pending
        $this->assertDatabaseHas('server_databases', [
            'id' => $database->id,
            'status' => DatabaseStatus::Pending->value,
            'error_log' => null,
        ]);

        // Verify job was dispatched
        Queue::assertPushed(MySqlInstallerJob::class, function ($job) use ($server, $database) {
            return $job->server->id === $server->id
                && $job->databaseId === $database->id;
        });
    }

    /**
     * Test retry dispatches correct job for MariaDB.
     */
    public function test_retry_dispatches_mariadb_installer_job(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MariaDB,
            'status' => DatabaseStatus::Failed,
        ]);

        // Act
        $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/retry");

        // Assert
        Queue::assertPushed(MariaDbInstallerJob::class);
        Queue::assertNotPushed(MySqlInstallerJob::class);
    }

    /**
     * Test retry dispatches correct job for PostgreSQL.
     */
    public function test_retry_dispatches_postgresql_installer_job(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::PostgreSQL,
            'status' => DatabaseStatus::Failed,
        ]);

        // Act
        $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/retry");

        // Assert
        Queue::assertPushed(PostgreSqlInstallerJob::class);
    }

    /**
     * Test retry dispatches correct job for Redis.
     */
    public function test_retry_dispatches_redis_installer_job(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::Redis,
            'status' => DatabaseStatus::Failed,
        ]);

        // Act
        $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/retry");

        // Assert
        Queue::assertPushed(RedisInstallerJob::class);
    }

    /**
     * Test cannot retry database that is not failed.
     */
    public function test_cannot_retry_database_that_is_not_failed(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'status' => DatabaseStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/retry");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('error', 'Only failed databases can be retried');

        // Verify status was not changed
        $this->assertDatabaseHas('server_databases', [
            'id' => $database->id,
            'status' => DatabaseStatus::Active->value,
        ]);

        // Verify no job was dispatched
        Queue::assertNothingPushed();
    }

    /**
     * Test user cannot retry database on another user's server.
     */
    public function test_user_cannot_retry_database_on_another_users_server(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'status' => DatabaseStatus::Failed,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/retry");

        // Assert
        $response->assertStatus(403);

        // Verify status was not changed
        $this->assertDatabaseHas('server_databases', [
            'id' => $database->id,
            'status' => DatabaseStatus::Failed->value,
        ]);

        Queue::assertNothingPushed();
    }

    /**
     * Test guest cannot retry database installation.
     */
    public function test_guest_cannot_retry_database_installation(): void
    {
        // Arrange
        Queue::fake();
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'status' => DatabaseStatus::Failed,
        ]);

        // Act
        $response = $this->post("/servers/{$server->id}/databases/{$database->id}/retry");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
        Queue::assertNothingPushed();
    }

    /**
     * Test retry returns 404 when database does not belong to server.
     */
    public function test_retry_returns_404_when_database_does_not_belong_to_server(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server1 = Server::factory()->create(['user_id' => $user->id]);
        $server2 = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server2->id,
            'status' => DatabaseStatus::Failed,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server1->id}/databases/{$database->id}/retry");

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
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'version' => '8.0',
            'status' => DatabaseStatus::Failed,
        ]);

        // Act
        $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/retry");

        // Assert
        Log::shouldHaveReceived('info')
            ->once()
            ->with('Database installation retry initiated', \Mockery::on(function ($context) use ($user, $server, $database) {
                return $context['user_id'] === $user->id
                    && $context['server_id'] === $server->id
                    && $context['database_id'] === $database->id
                    && $context['database_type'] instanceof DatabaseType
                    && $context['database_version'] === '8.0';
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
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => DatabaseStatus::Failed,
            'error_log' => 'Previous error message',
        ]);

        // Act
        $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/retry");

        // Assert
        $this->assertDatabaseHas('server_databases', [
            'id' => $database->id,
            'status' => DatabaseStatus::Pending->value,
            'error_log' => null,
        ]);
    }
}
