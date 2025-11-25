<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\ServerDatabase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerDatabaseInstallationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test cannot install second database in same category.
     */
    public function test_cannot_install_second_database_in_same_category(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create existing MySQL database
        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => 'mysql',
            'version' => '8.0',
            'port' => 3306,
            'status' => 'active',
            'root_password' => 'password123',
        ]);

        // Act - attempt to install MariaDB (same category)
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'name' => 'second_database',
                'type' => 'mariadb',
                'version' => '11.4',
                'port' => 3307,
                'root_password' => 'password456',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['type']);
        $this->assertDatabaseMissing('server_databases', [
            'server_id' => $server->id,
            'type' => 'mariadb',
        ]);
    }

    /**
     * Test can install database when category empty.
     */
    public function test_can_install_database_when_category_empty(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act - install MySQL on empty server
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'name' => 'first_database',
                'type' => 'mysql',
                'version' => '8.0',
                'port' => 3306,
                'root_password' => 'securePassword123',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('server_databases', [
            'server_id' => $server->id,
            'type' => 'mysql',
            'name' => 'first_database',
            'status' => 'pending',
        ]);
    }

    /**
     * Test can install cache service when database exists.
     */
    public function test_can_install_cache_service_when_database_exists(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create existing MySQL database
        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => 'mysql',
            'version' => '8.0',
            'port' => 3306,
            'status' => 'active',
            'root_password' => 'password123',
        ]);

        // Act - install Redis (different category)
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'name' => 'cache_service',
                'type' => 'redis',
                'version' => '7.2',
                'port' => 6379,
                'root_password' => 'redisPassword123',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('server_databases', [
            'server_id' => $server->id,
            'type' => 'redis',
            'status' => 'pending',
        ]);
    }

    /**
     * Test can install database when only cache exists.
     */
    public function test_can_install_database_when_only_cache_exists(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create existing Redis cache
        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => 'redis',
            'version' => '7.2',
            'port' => 6379,
            'status' => 'active',
            'root_password' => 'password123',
        ]);

        // Act - install MySQL (different category)
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'name' => 'Database',
                'type' => 'mysql',
                'version' => '8.0',
                'port' => 3306,
                'root_password' => 'mysqlPassword123',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('server_databases', [
            'server_id' => $server->id,
            'type' => 'mysql',
            'status' => 'pending',
        ]);
    }

    /**
     * Test validation error message is clear.
     */
    public function test_validation_error_message_is_clear(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create existing PostgreSQL database
        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => 'postgresql',
            'version' => '16',
            'port' => 5432,
            'status' => 'active',
            'root_password' => 'password123',
        ]);

        // Act - attempt to install MySQL (same category)
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'name' => 'second_database',
                'type' => 'mysql',
                'version' => '8.0',
                'port' => 3306,
                'root_password' => 'password456',
            ]);

        // Assert - verify clear error message
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['type']);
        $errors = session('errors');
        $this->assertStringContainsString('database', $errors->get('type')[0]);
        $this->assertStringContainsString('uninstall', $errors->get('type')[0]);
    }

    /**
     * Test can install after uninstalling previous database.
     */
    public function test_can_install_after_uninstalling_previous_database(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create failed MySQL installation (can retry)
        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => 'mysql',
            'version' => '8.0',
            'port' => 3306,
            'status' => 'failed',
            'root_password' => 'password123',
        ]);

        // Act - install MariaDB (same category but previous is failed)
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'name' => 'new_database',
                'type' => 'mariadb',
                'version' => '11.4',
                'port' => 3307,
                'root_password' => 'newPassword123',
            ]);

        // Assert - should succeed because failed installations are ignored
        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('server_databases', [
            'server_id' => $server->id,
            'type' => 'mariadb',
            'status' => 'pending',
        ]);
    }

    /**
     * Test cannot install second cache service.
     */
    public function test_cannot_install_second_cache_service(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create existing Redis
        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => 'redis',
            'version' => '7.2',
            'port' => 6379,
            'status' => 'active',
            'root_password' => 'password123',
        ]);

        // Act - attempt to install second Redis
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'name' => 'second_redis',
                'type' => 'redis',
                'version' => '7.0',
                'port' => 6380,
                'root_password' => 'password456',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['type']);
        $errors = session('errors');
        $this->assertStringContainsString('cache/queue', $errors->get('type')[0]);
    }

    /**
     * Test user cannot install database on other users server.
     */
    public function test_user_cannot_install_database_on_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'name' => 'unauthorized_database',
                'type' => 'mysql',
                'version' => '8.0',
                'port' => 3306,
                'root_password' => 'password123',
            ]);

        // Assert
        $response->assertStatus(403);
        $this->assertDatabaseMissing('server_databases', [
            'server_id' => $server->id,
            'name' => 'unauthorized_database',
        ]);
    }

    /**
     * Test validates required fields.
     */
    public function test_validates_required_fields(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act - submit without required fields
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", []);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['type', 'version', 'root_password']);
    }

    /**
     * Test validates root password minimum length.
     */
    public function test_validates_root_password_minimum_length(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act - submit with short password
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'name' => 'test_database',
                'type' => 'mysql',
                'version' => '8.0',
                'port' => 3306,
                'root_password' => 'short',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['root_password']);
    }

    /**
     * Test validates port uniqueness per server.
     */
    public function test_validates_port_uniqueness_per_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create existing database on port 3306
        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => 'mysql',
            'version' => '8.0',
            'port' => 3306,
            'status' => 'failed',
            'root_password' => 'password123',
        ]);

        // Act - attempt to use same port (even though first one failed, port is still taken)
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'name' => 'test_database',
                'type' => 'mariadb',
                'version' => '11.4',
                'port' => 3306,
                'root_password' => 'password456',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['port']);
    }

    /**
     * Test pending installation prevents new installation in same category.
     */
    public function test_pending_installation_prevents_new_installation_in_same_category(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create pending MySQL installation
        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => 'mysql',
            'version' => '8.0',
            'port' => 3306,
            'status' => 'pending',
            'root_password' => 'password123',
        ]);

        // Act - attempt to install MariaDB
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'name' => 'second_database',
                'type' => 'mariadb',
                'version' => '11.4',
                'port' => 3307,
                'root_password' => 'password456',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['type']);
    }

    /**
     * Test installing installation prevents new installation in same category.
     */
    public function test_installing_status_prevents_new_installation_in_same_category(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create installing MySQL
        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => 'mysql',
            'version' => '8.0',
            'port' => 3306,
            'status' => 'installing',
            'root_password' => 'password123',
        ]);

        // Act - attempt to install PostgreSQL
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'name' => 'second_database',
                'type' => 'postgresql',
                'version' => '16',
                'port' => 5432,
                'root_password' => 'password456',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['type']);
    }

    /**
     * Test guest cannot install database.
     */
    public function test_guest_cannot_install_database(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act
        $response = $this->post("/servers/{$server->id}/databases", [
            'name' => 'test_database',
            'type' => 'mysql',
            'version' => '8.0',
            'port' => 3306,
            'root_password' => 'password123',
        ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test database record created with correct initial status.
     */
    public function test_database_record_created_with_correct_initial_status(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'name' => 'test_database',
                'type' => 'mysql',
                'version' => '8.0',
                'port' => 3306,
                'root_password' => 'securePassword123',
            ]);

        // Assert
        $response->assertStatus(302);
        $this->assertDatabaseHas('server_databases', [
            'server_id' => $server->id,
            'type' => 'mysql',
            'status' => 'pending',
        ]);
    }
}
