<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Models\ServerDatabaseSchema;
use App\Models\ServerDatabaseUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerDatabaseUserControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test root user cannot be deleted.
     */
    public function test_root_user_cannot_be_deleted(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Active,
        ]);
        $rootUser = ServerDatabaseUser::factory()->create([
            'server_database_id' => $database->id,
            'is_root' => true,
            'username' => 'root',
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/databases/{$database->id}/users/{$rootUser->id}");

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('error', 'Root user cannot be deleted.');
        $this->assertDatabaseHas('server_database_users', [
            'id' => $rootUser->id,
        ]);
    }

    /**
     * Test non-root user can be deleted.
     */
    public function test_non_root_user_can_be_deleted(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Active,
        ]);
        $databaseUser = ServerDatabaseUser::factory()->create([
            'server_database_id' => $database->id,
            'is_root' => false,
            'username' => 'appuser',
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/databases/{$database->id}/users/{$databaseUser->id}");

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('success', 'Database user deletion started.');
        $this->assertDatabaseHas('server_database_users', [
            'id' => $databaseUser->id,
            'status' => TaskStatus::Removing->value,
        ]);
    }

    /**
     * Test root user cannot be updated.
     */
    public function test_root_user_cannot_be_updated(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Active,
        ]);
        $schema = ServerDatabaseSchema::factory()->create([
            'server_database_id' => $database->id,
        ]);
        $rootUser = ServerDatabaseUser::factory()->create([
            'server_database_id' => $database->id,
            'is_root' => true,
            'username' => 'root',
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/databases/{$database->id}/users/{$rootUser->id}", [
                'password' => 'newpassword',
                'privileges' => 'read_only',
                'schema_ids' => [$schema->id],
            ]);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('error', 'Root user cannot be modified.');
    }

    /**
     * Test non-root user can be updated.
     */
    public function test_non_root_user_can_be_updated(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Active,
        ]);
        $schema = ServerDatabaseSchema::factory()->create([
            'server_database_id' => $database->id,
        ]);
        $databaseUser = ServerDatabaseUser::factory()->create([
            'server_database_id' => $database->id,
            'is_root' => false,
            'username' => 'appuser',
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/databases/{$database->id}/users/{$databaseUser->id}", [
                'password' => 'newpassword',
                'privileges' => 'read_only',
                'schema_ids' => [$schema->id],
            ]);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('success', 'Database user update started.');
        $this->assertDatabaseHas('server_database_users', [
            'id' => $databaseUser->id,
            'status' => TaskStatus::Active->value,  // Main status stays active
            'update_status' => TaskStatus::Pending->value,  // Update status set to pending
        ]);
    }

    /**
     * Test user can retry failed database user update.
     */
    public function test_user_can_retry_failed_database_user_update(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Active,
        ]);
        $databaseUser = ServerDatabaseUser::factory()->create([
            'server_database_id' => $database->id,
            'is_root' => false,
            'username' => 'appuser',
            'status' => TaskStatus::Active,
            'update_status' => TaskStatus::Failed,
            'update_error_log' => 'Some error occurred',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/users/{$databaseUser->id}/retry");

        // Assert
        $response->assertRedirect();
        $this->assertDatabaseHas('server_database_users', [
            'id' => $databaseUser->id,
            'update_status' => TaskStatus::Pending->value,
            'update_error_log' => null,
        ]);
    }

    /**
     * Test user can cancel database user update.
     */
    public function test_user_can_cancel_database_user_update(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Active,
        ]);
        $databaseUser = ServerDatabaseUser::factory()->create([
            'server_database_id' => $database->id,
            'is_root' => false,
            'username' => 'appuser',
            'status' => TaskStatus::Active,
            'update_status' => TaskStatus::Failed,
            'update_error_log' => 'Some error occurred',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/users/{$databaseUser->id}/cancel-update");

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('success', 'Update cancelled.');
        $this->assertDatabaseHas('server_database_users', [
            'id' => $databaseUser->id,
            'update_status' => null,
            'update_error_log' => null,
        ]);
    }

    /**
     * Test root user cannot be retried.
     */
    public function test_root_user_cannot_be_retried(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Active,
        ]);
        $rootUser = ServerDatabaseUser::factory()->create([
            'server_database_id' => $database->id,
            'is_root' => true,
            'username' => 'root',
            'status' => TaskStatus::Active,
            'update_status' => TaskStatus::Failed,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/users/{$rootUser->id}/retry");

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('error', 'Root user cannot be modified.');
    }

    /**
     * Test user cannot delete database user from other users server.
     */
    public function test_user_cannot_delete_database_user_from_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Active,
        ]);
        $databaseUser = ServerDatabaseUser::factory()->create([
            'server_database_id' => $database->id,
            'is_root' => false,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/databases/{$database->id}/users/{$databaseUser->id}");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test retry only works for failed updates.
     */
    public function test_retry_only_works_for_failed_updates(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Active,
        ]);
        $databaseUser = ServerDatabaseUser::factory()->create([
            'server_database_id' => $database->id,
            'is_root' => false,
            'status' => TaskStatus::Active,
            'update_status' => TaskStatus::Pending,  // Not failed
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/users/{$databaseUser->id}/retry");

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('error', 'Only failed user updates can be retried.');
        $this->assertDatabaseHas('server_database_users', [
            'id' => $databaseUser->id,
            'update_status' => TaskStatus::Pending->value,  // Unchanged
        ]);
    }

    /**
     * Test cancel works for pending updates.
     */
    public function test_cancel_works_for_pending_updates(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Active,
        ]);
        $databaseUser = ServerDatabaseUser::factory()->create([
            'server_database_id' => $database->id,
            'is_root' => false,
            'status' => TaskStatus::Active,
            'update_status' => TaskStatus::Pending,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/users/{$databaseUser->id}/cancel-update");

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('success', 'Update cancelled.');
        $this->assertDatabaseHas('server_database_users', [
            'id' => $databaseUser->id,
            'update_status' => null,
        ]);
    }

    /**
     * Test cancel works for updating status.
     */
    public function test_cancel_works_for_updating_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Active,
        ]);
        $databaseUser = ServerDatabaseUser::factory()->create([
            'server_database_id' => $database->id,
            'is_root' => false,
            'status' => TaskStatus::Active,
            'update_status' => TaskStatus::Updating,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/users/{$databaseUser->id}/cancel-update");

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('success', 'Update cancelled.');
        $this->assertDatabaseHas('server_database_users', [
            'id' => $databaseUser->id,
            'update_status' => null,
        ]);
    }

    /**
     * Test user cannot retry other users database user.
     */
    public function test_user_cannot_retry_other_users_database_user(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Active,
        ]);
        $databaseUser = ServerDatabaseUser::factory()->create([
            'server_database_id' => $database->id,
            'is_root' => false,
            'update_status' => TaskStatus::Failed,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/users/{$databaseUser->id}/retry");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user cannot cancel other users database user update.
     */
    public function test_user_cannot_cancel_other_users_database_user_update(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Active,
        ]);
        $databaseUser = ServerDatabaseUser::factory()->create([
            'server_database_id' => $database->id,
            'is_root' => false,
            'update_status' => TaskStatus::Failed,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/users/{$databaseUser->id}/cancel-update");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user can create database user.
     */
    public function test_user_can_create_database_user(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Active,
        ]);
        $schema = ServerDatabaseSchema::factory()->create([
            'server_database_id' => $database->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/users", [
                'username' => 'newuser',
                'password' => 'password123',
                'host' => '%',
                'privileges' => 'read_write',
                'schema_ids' => [$schema->id],
            ]);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('success', 'Database user creation started.');
        $this->assertDatabaseHas('server_database_users', [
            'username' => 'newuser',
            'server_database_id' => $database->id,
            'status' => TaskStatus::Pending->value,
        ]);
    }
}
