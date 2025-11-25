<?php

namespace Tests\Feature;

use App\Enums\DatabaseType;
use App\Enums\TaskStatus;
use App\Events\ServerUpdated;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Models\ServerDatabaseSchema;
use App\Models\User;
use App\Packages\Services\Database\Schema\DatabaseSchemaInstallerJob;
use App\Packages\Services\Database\Schema\DatabaseSchemaRemoverJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerDatabaseSchemaLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test controller creates schema record with pending status before dispatching job.
     */
    public function test_controller_creates_schema_with_pending_status(): void
    {
        // Arrange - inline setup
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/schemas", [
                'name' => 'test_schema',
                'character_set' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]);

        // Assert
        $response->assertRedirect();
        $this->assertDatabaseHas('server_database_schemas', [
            'server_database_id' => $database->id,
            'name' => 'test_schema',
            'status' => TaskStatus::Pending->value,
        ]);
    }

    /**
     * Test controller dispatches job with schema model.
     */
    public function test_controller_dispatches_job_with_schema_model(): void
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

        // Act
        $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/schemas", [
                'name' => 'test_schema',
                'character_set' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]);

        // Assert
        Queue::assertPushed(DatabaseSchemaInstallerJob::class, function ($job) {
            return $job->schema instanceof ServerDatabaseSchema
                && $job->schema->name === 'test_schema';
        });
    }

    /**
     * Test schema model broadcasts ServerUpdated event when created.
     */
    public function test_schema_model_broadcasts_on_create(): void
    {
        // Arrange
        Event::fake([ServerUpdated::class]);
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);

        // Act
        ServerDatabaseSchema::create([
            'server_database_id' => $database->id,
            'name' => 'test_schema',
            'character_set' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'status' => TaskStatus::Pending,
        ]);

        // Assert
        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    /**
     * Test schema model broadcasts ServerUpdated event when updated.
     */
    public function test_schema_model_broadcasts_on_update(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);
        $schema = ServerDatabaseSchema::create([
            'server_database_id' => $database->id,
            'name' => 'test_schema',
            'status' => TaskStatus::Pending,
        ]);

        Event::fake([ServerUpdated::class]);

        // Act
        $schema->update(['status' => TaskStatus::Installing]);

        // Assert
        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    /**
     * Test schema model broadcasts ServerUpdated event when deleted.
     */
    public function test_schema_model_broadcasts_on_delete(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);
        $schema = ServerDatabaseSchema::create([
            'server_database_id' => $database->id,
            'name' => 'test_schema',
            'status' => TaskStatus::Active,
        ]);

        Event::fake([ServerUpdated::class]);

        // Act
        $schema->delete();

        // Assert
        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    /**
     * Test schema can transition from pending to installing.
     */
    public function test_schema_can_transition_to_installing(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);
        $schema = ServerDatabaseSchema::create([
            'server_database_id' => $database->id,
            'name' => 'test_schema',
            'status' => TaskStatus::Pending,
        ]);

        // Act
        $schema->update(['status' => TaskStatus::Installing]);

        // Assert
        $this->assertEquals(TaskStatus::Installing, $schema->fresh()->status);
    }

    /**
     * Test schema can transition from installing to active.
     */
    public function test_schema_can_transition_to_active(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);
        $schema = ServerDatabaseSchema::create([
            'server_database_id' => $database->id,
            'name' => 'test_schema',
            'status' => TaskStatus::Installing,
        ]);

        // Act
        $schema->update(['status' => TaskStatus::Active]);

        // Assert
        $this->assertEquals(TaskStatus::Active, $schema->fresh()->status);
    }

    /**
     * Test schema can transition from installing to failed with error log.
     */
    public function test_schema_can_transition_to_failed_with_error_log(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);
        $schema = ServerDatabaseSchema::create([
            'server_database_id' => $database->id,
            'name' => 'test_schema',
            'status' => TaskStatus::Installing,
        ]);

        // Act
        $schema->update([
            'status' => TaskStatus::Failed,
            'error_log' => 'Database creation failed',
        ]);

        // Assert
        $fresh = $schema->fresh();
        $this->assertEquals(TaskStatus::Failed, $fresh->status);
        $this->assertEquals('Database creation failed', $fresh->error_log);
    }

    /**
     * Test error log is accessible in schema record after failure.
     */
    public function test_error_log_is_accessible_in_schema_record(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);
        $schema = ServerDatabaseSchema::create([
            'server_database_id' => $database->id,
            'name' => 'test_schema',
            'status' => TaskStatus::Failed,
            'error_log' => 'Test error message',
        ]);

        // Assert
        $this->assertEquals('Test error message', $schema->error_log);
        $this->assertDatabaseHas('server_database_schemas', [
            'id' => $schema->id,
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
        $schema = ServerDatabaseSchema::create([
            'server_database_id' => $database->id,
            'name' => 'test_schema',
            'status' => TaskStatus::Active,
        ]);

        // Act
        $this->actingAs($user)
            ->delete("/servers/{$server->id}/databases/{$database->id}/schemas/{$schema->id}");

        // Assert
        $this->assertEquals(TaskStatus::Removing, $schema->fresh()->status);
    }

    /**
     * Test removal job is dispatched with schema model.
     */
    public function test_removal_dispatches_job_with_schema_model(): void
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
            ->delete("/servers/{$server->id}/databases/{$database->id}/schemas/{$schema->id}");

        // Assert
        Queue::assertPushed(DatabaseSchemaRemoverJob::class, function ($job) use ($schema) {
            return $job->schema instanceof ServerDatabaseSchema
                && $job->schema->id === $schema->id;
        });
    }

    /**
     * Test user cannot create schema on other user's server.
     */
    public function test_user_cannot_create_schema_on_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/schemas", [
                'name' => 'test_schema',
            ]);

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user cannot delete schema on other user's server.
     */
    public function test_user_cannot_delete_schema_on_other_users_server(): void
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
            ->delete("/servers/{$server->id}/databases/{$database->id}/schemas/{$schema->id}");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test guest cannot create schema.
     */
    public function test_guest_cannot_create_schema(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);

        // Act
        $response = $this->post("/servers/{$server->id}/databases/{$database->id}/schemas", [
            'name' => 'test_schema',
        ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test guest cannot delete schema.
     */
    public function test_guest_cannot_delete_schema(): void
    {
        // Arrange
        $server = Server::factory()->create();
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
        $response = $this->delete("/servers/{$server->id}/databases/{$database->id}/schemas/{$schema->id}");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }
}
