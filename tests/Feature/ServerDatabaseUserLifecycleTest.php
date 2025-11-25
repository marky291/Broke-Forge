<?php

namespace Tests\Feature;

use App\Enums\DatabaseType;
use App\Enums\TaskStatus;
use App\Events\ServerUpdated;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Models\ServerDatabaseSchema;
use App\Models\ServerDatabaseUser;
use App\Models\User;
use App\Packages\Services\Database\User\DatabaseUserInstallerJob;
use App\Packages\Services\Database\User\DatabaseUserRemoverJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerDatabaseUserLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test controller creates user record with pending status before dispatching job.
     */
    public function test_controller_creates_user_with_pending_status(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => TaskStatus::Active,
        ]);
        $schema = ServerDatabaseSchema::create([
            'server_database_id' => $database->id,
            'name' => 'test_schema',
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/users", [
                'username' => 'test_user',
                'password' => 'SecurePassword123!',
                'host' => '%',
                'privileges' => 'read_write',
                'schema_ids' => [$schema->id],
            ]);

        // Assert
        $response->assertRedirect();
        $this->assertDatabaseHas('server_database_users', [
            'server_database_id' => $database->id,
            'username' => 'test_user',
            'status' => TaskStatus::Pending->value,
        ]);
    }

    /**
     * Test controller dispatches job with user model.
     */
    public function test_controller_dispatches_job_with_user_model(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => TaskStatus::Active,
        ]);
        $schema = ServerDatabaseSchema::create([
            'server_database_id' => $database->id,
            'name' => 'test_schema',
            'status' => TaskStatus::Active,
        ]);

        // Act
        $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/users", [
                'username' => 'test_user',
                'password' => 'SecurePassword123!',
                'host' => '%',
                'privileges' => 'read_write',
                'schema_ids' => [$schema->id],
            ]);

        // Assert
        Queue::assertPushed(DatabaseUserInstallerJob::class, function ($job) {
            return $job->user instanceof ServerDatabaseUser
                && $job->user->username === 'test_user';
        });
    }

    /**
     * Test user model broadcasts ServerUpdated event when created.
     */
    public function test_user_model_broadcasts_on_create(): void
    {
        // Arrange
        Event::fake([ServerUpdated::class]);
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);

        // Act
        ServerDatabaseUser::create([
            'server_database_id' => $database->id,
            'username' => 'test_user',
            'password' => 'SecurePassword123!',
            'host' => '%',
            'privileges' => 'read_write',
            'status' => TaskStatus::Pending,
        ]);

        // Assert
        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    /**
     * Test user model broadcasts ServerUpdated event when updated.
     */
    public function test_user_model_broadcasts_on_update(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);
        $dbUser = ServerDatabaseUser::create([
            'server_database_id' => $database->id,
            'username' => 'test_user',
            'password' => 'SecurePassword123!',
            'host' => '%',
            'privileges' => 'read_write',
            'status' => TaskStatus::Pending,
        ]);

        Event::fake([ServerUpdated::class]);

        // Act
        $dbUser->update(['status' => TaskStatus::Installing]);

        // Assert
        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    /**
     * Test user model broadcasts ServerUpdated event when deleted.
     */
    public function test_user_model_broadcasts_on_delete(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);
        $dbUser = ServerDatabaseUser::create([
            'server_database_id' => $database->id,
            'username' => 'test_user',
            'password' => 'SecurePassword123!',
            'host' => '%',
            'privileges' => 'read_write',
            'status' => TaskStatus::Active,
        ]);

        Event::fake([ServerUpdated::class]);

        // Act
        $dbUser->delete();

        // Assert
        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    /**
     * Test user can transition from pending to installing.
     */
    public function test_user_can_transition_to_installing(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);
        $dbUser = ServerDatabaseUser::create([
            'server_database_id' => $database->id,
            'username' => 'test_user',
            'password' => 'SecurePassword123!',
            'host' => '%',
            'privileges' => 'read_write',
            'status' => TaskStatus::Pending,
        ]);

        // Act
        $dbUser->update(['status' => TaskStatus::Installing]);

        // Assert
        $this->assertEquals(TaskStatus::Installing, $dbUser->fresh()->status);
    }

    /**
     * Test user can transition from installing to active.
     */
    public function test_user_can_transition_to_active(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);
        $dbUser = ServerDatabaseUser::create([
            'server_database_id' => $database->id,
            'username' => 'test_user',
            'password' => 'SecurePassword123!',
            'host' => '%',
            'privileges' => 'read_write',
            'status' => TaskStatus::Installing,
        ]);

        // Act
        $dbUser->update(['status' => TaskStatus::Active]);

        // Assert
        $this->assertEquals(TaskStatus::Active, $dbUser->fresh()->status);
    }

    /**
     * Test user can transition from installing to failed with error log.
     */
    public function test_user_can_transition_to_failed_with_error_log(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);
        $dbUser = ServerDatabaseUser::create([
            'server_database_id' => $database->id,
            'username' => 'test_user',
            'password' => 'SecurePassword123!',
            'host' => '%',
            'privileges' => 'read_write',
            'status' => TaskStatus::Installing,
        ]);

        // Act
        $dbUser->update([
            'status' => TaskStatus::Failed,
            'error_log' => 'User creation failed',
        ]);

        // Assert
        $fresh = $dbUser->fresh();
        $this->assertEquals(TaskStatus::Failed, $fresh->status);
        $this->assertEquals('User creation failed', $fresh->error_log);
    }

    /**
     * Test error log is accessible in user record after failure.
     */
    public function test_error_log_is_accessible_in_user_record(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);
        $dbUser = ServerDatabaseUser::create([
            'server_database_id' => $database->id,
            'username' => 'test_user',
            'password' => 'SecurePassword123!',
            'host' => '%',
            'privileges' => 'read_write',
            'status' => TaskStatus::Failed,
            'error_log' => 'Test error message',
        ]);

        // Assert
        $this->assertEquals('Test error message', $dbUser->error_log);
        $this->assertDatabaseHas('server_database_users', [
            'id' => $dbUser->id,
            'error_log' => 'Test error message',
        ]);
    }

    /**
     * Test removal sets status to removing before dispatching job.
     */
    public function test_removal_sets_status_to_removing(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => TaskStatus::Active,
        ]);
        $dbUser = ServerDatabaseUser::create([
            'server_database_id' => $database->id,
            'username' => 'test_user',
            'password' => 'SecurePassword123!',
            'host' => '%',
            'privileges' => 'read_write',
            'status' => TaskStatus::Active,
        ]);

        // Act
        $this->actingAs($user)
            ->delete("/servers/{$server->id}/databases/{$database->id}/users/{$dbUser->id}");

        // Assert
        $this->assertEquals(TaskStatus::Removing, $dbUser->fresh()->status);
    }

    /**
     * Test removal job is dispatched with user model.
     */
    public function test_removal_dispatches_job_with_user_model(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => TaskStatus::Active,
        ]);
        $dbUser = ServerDatabaseUser::create([
            'server_database_id' => $database->id,
            'username' => 'test_user',
            'password' => 'SecurePassword123!',
            'host' => '%',
            'privileges' => 'read_write',
            'status' => TaskStatus::Active,
        ]);

        // Act
        $this->actingAs($user)
            ->delete("/servers/{$server->id}/databases/{$database->id}/users/{$dbUser->id}");

        // Assert
        Queue::assertPushed(DatabaseUserRemoverJob::class, function ($job) use ($dbUser) {
            return $job->user instanceof ServerDatabaseUser
                && $job->user->id === $dbUser->id;
        });
    }

    /**
     * Test user cannot create database user on other user's server.
     */
    public function test_user_cannot_create_database_user_on_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);
        $schema = ServerDatabaseSchema::create([
            'server_database_id' => $database->id,
            'name' => 'test_schema',
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/users", [
                'username' => 'test_user',
                'password' => 'SecurePassword123!',
                'host' => '%',
                'privileges' => 'read_write',
                'schema_ids' => [$schema->id],
            ]);

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user cannot delete database user on other user's server.
     */
    public function test_user_cannot_delete_database_user_on_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);
        $dbUser = ServerDatabaseUser::create([
            'server_database_id' => $database->id,
            'username' => 'test_user',
            'password' => 'SecurePassword123!',
            'host' => '%',
            'privileges' => 'read_write',
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/databases/{$database->id}/users/{$dbUser->id}");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test guest cannot create database user.
     */
    public function test_guest_cannot_create_database_user(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);

        // Act
        $response = $this->post("/servers/{$server->id}/databases/{$database->id}/users", [
            'username' => 'test_user',
            'password' => 'SecurePassword123!',
        ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test guest cannot delete database user.
     */
    public function test_guest_cannot_delete_database_user(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);
        $dbUser = ServerDatabaseUser::create([
            'server_database_id' => $database->id,
            'username' => 'test_user',
            'password' => 'SecurePassword123!',
            'host' => '%',
            'privileges' => 'read_write',
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->delete("/servers/{$server->id}/databases/{$database->id}/users/{$dbUser->id}");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }
}
