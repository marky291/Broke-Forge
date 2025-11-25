<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\DatabaseType;
use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Models\ServerDatabaseSchema;
use App\Models\ServerDatabaseUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerDatabaseSchemaControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can create schema without user successfully.
     */
    public function test_creates_schema_without_user_successfully(): void
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
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/schemas", [
                'name' => 'my_app_db',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('server_database_schemas', [
            'server_database_id' => $database->id,
            'name' => 'my_app_db',
            'character_set' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'status' => TaskStatus::Pending->value,
        ]);

        // Should not create a user
        $this->assertEquals(0, ServerDatabaseUser::count());
    }

    /**
     * Test user can create schema with user and password successfully.
     */
    public function test_creates_schema_with_user_and_password_successfully(): void
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
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/schemas", [
                'name' => 'my_app_db',
                'user' => 'app_user',
                'password' => 'SecurePassword123',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('server_database_schemas', [
            'server_database_id' => $database->id,
            'name' => 'my_app_db',
            'character_set' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'status' => TaskStatus::Pending->value,
        ]);

        $this->assertDatabaseHas('server_database_users', [
            'server_database_id' => $database->id,
            'username' => 'app_user',
            'status' => TaskStatus::Pending->value,
        ]);
    }

    /**
     * Test validates name is required.
     */
    public function test_validates_name_is_required(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/schemas", []);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['name']);
    }

    /**
     * Test validates user required when password provided.
     */
    public function test_validates_user_required_when_password_provided(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/schemas", [
                'name' => 'test_db',
                'password' => 'SecurePassword123',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['user']);
    }

    /**
     * Test validates password required when user provided.
     */
    public function test_validates_password_required_when_user_provided(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/schemas", [
                'name' => 'test_db',
                'user' => 'db_user',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['password']);
    }

    /**
     * Test validates user must not already exist.
     */
    public function test_validates_user_must_not_already_exist(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);

        // Create existing database user
        ServerDatabaseUser::factory()->create([
            'server_database_id' => $database->id,
            'username' => 'existing_user',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/schemas", [
                'name' => 'test_db',
                'user' => 'existing_user',
                'password' => 'NewPassword123',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['user']);
    }

    /**
     * Test sets utf8mb4 character set by default.
     */
    public function test_sets_utf8mb4_character_set_by_default(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/schemas", [
                'name' => 'my_db',
            ]);

        // Assert
        $response->assertStatus(302);

        $schema = ServerDatabaseSchema::where('name', 'my_db')->first();
        $this->assertNotNull($schema);
        $this->assertEquals('utf8mb4', $schema->character_set);
        $this->assertEquals('utf8mb4_unicode_ci', $schema->collation);
    }

    /**
     * Test user cannot create schema for other users database.
     */
    public function test_user_cannot_create_schema_for_other_users_database(): void
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
                'name' => 'test_db',
            ]);

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test creates schema with pending status.
     */
    public function test_creates_schema_with_pending_status(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/schemas", [
                'name' => 'pending_db',
            ]);

        // Assert
        $response->assertStatus(302);

        $schema = ServerDatabaseSchema::where('name', 'pending_db')->first();
        $this->assertNotNull($schema);
        $this->assertEquals(TaskStatus::Pending, $schema->status);
    }

    /**
     * Test creates user with pending status when provided.
     */
    public function test_creates_user_with_pending_status_when_provided(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/schemas", [
                'name' => 'app_db',
                'user' => 'app_user',
                'password' => 'SecurePassword123',
            ]);

        // Assert
        $response->assertStatus(302);

        $dbUser = ServerDatabaseUser::where('username', 'app_user')->first();
        $this->assertNotNull($dbUser);
        $this->assertEquals(TaskStatus::Pending, $dbUser->status);
    }

    /**
     * Test links user to schema via pivot table.
     */
    public function test_links_user_to_schema_via_pivot_table(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases/{$database->id}/schemas", [
                'name' => 'linked_db',
                'user' => 'linked_user',
                'password' => 'SecurePassword123',
            ]);

        // Assert
        $response->assertStatus(302);

        $schema = ServerDatabaseSchema::where('name', 'linked_db')->first();
        $dbUser = ServerDatabaseUser::where('username', 'linked_user')->first();

        $this->assertNotNull($schema);
        $this->assertNotNull($dbUser);

        // Verify pivot relationship
        $this->assertTrue($schema->users()->where('server_database_user_id', $dbUser->id)->exists());
        $this->assertTrue($dbUser->schemas()->where('server_database_schema_id', $schema->id)->exists());
    }

    /**
     * Test guest cannot create schema.
     */
    public function test_guest_cannot_create_schema(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);

        // Act
        $response = $this->post("/servers/{$server->id}/databases/{$database->id}/schemas", [
            'name' => 'test_db',
        ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }
}
