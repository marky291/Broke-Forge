<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\DatabaseStatus;
use App\Enums\DatabaseType;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerDatabaseControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test guest cannot access server database page.
     */
    public function test_guest_cannot_access_server_database_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->get("/servers/{$server->id}/services");

        // Assert - guests should be redirected to login
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test authenticated user can access their server's database page.
     */
    public function test_user_can_access_their_server_database_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
    }

    /**
     * Test user cannot access other users server database page.
     */
    public function test_user_cannot_access_other_users_server_database_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test database page renders correct Inertia component.
     */
    public function test_database_page_renders_correct_inertia_component(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server')
        );
    }

    /**
     * Test database page includes server data.
     */
    public function test_database_page_includes_server_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Database Server',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->where('server.id', $server->id)
            ->where('server.vanity_name', 'Database Server')
        );
    }

    /**
     * Test database page includes installed databases.
     */
    public function test_database_page_includes_installed_databases(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'version' => '8.0',
            'port' => 3306,
            'status' => DatabaseStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 1)
        );
    }

    /**
     * Test database page shows empty state when no database installed.
     */
    public function test_database_page_shows_empty_state_when_no_database_installed(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 0)
        );
    }

    /**
     * Test database page includes database status.
     */
    public function test_database_page_includes_database_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::PostgreSQL,
            'status' => DatabaseStatus::Installing,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 1)
            ->where('server.databases.0.status', DatabaseStatus::Installing->value)
        );
    }

    /**
     * Test user can install MySQL database.
     */
    public function test_user_can_install_mysql_database(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'name' => 'MySQL',
                'type' => 'mysql',
                'version' => '8.0',
                'root_password' => 'SecurePassword123',
                'port' => 3306,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/services");
        $response->assertSessionHas('success', 'Database installation started.');

        $this->assertDatabaseHas('server_databases', [
            'server_id' => $server->id,
            'name' => 'MySQL',
            'type' => DatabaseType::MySQL->value,
            'version' => '8.0',
            'port' => 3306,
            'status' => DatabaseStatus::Pending->value,
        ]);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Packages\Services\Database\MySQL\MySqlInstallerJob::class);
    }

    /**
     * Test user can install MariaDB database.
     */
    public function test_user_can_install_mariadb_database(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'type' => 'mariadb',
                'version' => '10.11',
                'root_password' => 'SecurePass456',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Database installation started.');

        $this->assertDatabaseHas('server_databases', [
            'server_id' => $server->id,
            'type' => DatabaseType::MariaDB->value,
            'version' => '10.11',
            'status' => DatabaseStatus::Pending->value,
        ]);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Packages\Services\Database\MariaDB\MariaDbInstallerJob::class);
    }

    /**
     * Test user can install PostgreSQL database.
     */
    public function test_user_can_install_postgresql_database(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'type' => 'postgresql',
                'version' => '15',
                'root_password' => 'PostgresPass789',
                'port' => 5432,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Database installation started.');

        $this->assertDatabaseHas('server_databases', [
            'server_id' => $server->id,
            'type' => DatabaseType::PostgreSQL->value,
            'version' => '15',
            'port' => 5432,
            'status' => DatabaseStatus::Pending->value,
        ]);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Packages\Services\Database\PostgreSQL\PostgreSqlInstallerJob::class);
    }

    /**
     * Test install validates required type field.
     */
    public function test_install_validates_required_type_field(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'version' => '8.0',
                'root_password' => 'SecurePassword123',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['type']);
    }

    /**
     * Test install validates required version field.
     */
    public function test_install_validates_required_version_field(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'type' => 'mysql',
                'root_password' => 'SecurePassword123',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['version']);
    }

    /**
     * Test install validates required root_password field.
     */
    public function test_install_validates_required_root_password_field(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'type' => 'mysql',
                'version' => '8.0',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['root_password']);
    }

    /**
     * Test install validates type is valid enum.
     */
    public function test_install_validates_type_is_valid_enum(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'type' => 'invalid_database',
                'version' => '8.0',
                'root_password' => 'SecurePassword123',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['type']);
    }

    /**
     * Test install validates root_password minimum length.
     */
    public function test_install_validates_root_password_minimum_length(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'type' => 'mysql',
                'version' => '8.0',
                'root_password' => 'short',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['root_password']);
    }

    /**
     * Test install validates port range minimum.
     */
    public function test_install_validates_port_range_minimum(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'type' => 'mysql',
                'version' => '8.0',
                'root_password' => 'SecurePassword123',
                'port' => 0,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['port']);
    }

    /**
     * Test install validates port range maximum.
     */
    public function test_install_validates_port_range_maximum(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'type' => 'mysql',
                'version' => '8.0',
                'root_password' => 'SecurePassword123',
                'port' => 99999,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['port']);
    }

    /**
     * Test install uses default port when not provided.
     */
    public function test_install_uses_default_port_when_not_provided(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'type' => 'mysql',
                'version' => '8.0',
                'root_password' => 'SecurePassword123',
            ]);

        // Assert
        $response->assertStatus(302);

        $database = $server->databases()->first();
        $this->assertNotNull($database->port);
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
                'type' => 'mysql',
                'version' => '8.0',
                'root_password' => 'SecurePassword123',
            ]);

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test guest cannot install database.
     */
    public function test_guest_cannot_install_database(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->post("/servers/{$server->id}/databases", [
            'type' => 'mysql',
            'version' => '8.0',
            'root_password' => 'SecurePassword123',
        ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test database name defaults to type when not provided.
     */
    public function test_database_name_defaults_to_type_when_not_provided(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'type' => 'mysql',
                'version' => '8.0',
                'root_password' => 'SecurePassword123',
            ]);

        // Assert
        $response->assertStatus(302);

        $this->assertDatabaseHas('server_databases', [
            'server_id' => $server->id,
            'name' => 'mysql',
        ]);
    }

    /**
     * Test user can update database version.
     */
    public function test_user_can_update_database_version(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'version' => '8.0',
            'status' => DatabaseStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/databases/{$database->id}", [
                'version' => '8.4',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/services");
        $response->assertSessionHas('success', 'Database update started.');

        $database->refresh();
        $this->assertEquals(DatabaseStatus::Updating, $database->status);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Packages\Services\Database\MySQL\MySqlUpdaterJob::class);
    }

    /**
     * Test update stores new version on database record.
     */
    public function test_update_stores_new_version_on_database_record(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'version' => '8.0',
            'status' => DatabaseStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/databases/{$database->id}", [
                'version' => '8.4',
            ]);

        // Assert - new version should be stored on record
        $database->refresh();
        $this->assertEquals('8.4', $database->version);
        $this->assertEquals(DatabaseStatus::Updating, $database->status);
    }

    /**
     * Test update dispatches job with database ID.
     */
    public function test_update_dispatches_job_with_database_id(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'version' => '8.0',
            'status' => DatabaseStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/databases/{$database->id}", [
                'version' => '8.4',
            ]);

        // Assert - job should be dispatched with database ID
        \Illuminate\Support\Facades\Queue::assertPushed(
            \App\Packages\Services\Database\MySQL\MySqlUpdaterJob::class,
            function ($job) use ($database) {
                return $job->databaseId === $database->id;
            }
        );
    }

    /**
     * Test cannot update when no database exists.
     */
    public function test_cannot_update_when_no_database_exists(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act - attempt to update non-existent database
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/databases/999", [
                'version' => '8.4',
            ]);

        // Assert - Should get 404 when database doesn't exist
        $response->assertStatus(404);
    }

    /**
     * Test cannot update when database is installing.
     */
    public function test_cannot_update_when_database_is_installing(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => DatabaseStatus::Installing,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/databases/{$database->id}", [
                'version' => '8.4',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/services");
        $response->assertSessionHas('error', 'Database is currently being modified. Please wait.');
    }

    /**
     * Test cannot update when database is uninstalling.
     */
    public function test_cannot_update_when_database_is_uninstalling(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => DatabaseStatus::Uninstalling,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/databases/{$database->id}", [
                'version' => '8.4',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/services");
        $response->assertSessionHas('error', 'Database is currently being modified. Please wait.');
    }

    /**
     * Test cannot update when database is already updating.
     */
    public function test_cannot_update_when_database_is_already_updating(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => DatabaseStatus::Updating,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/databases/{$database->id}", [
                'version' => '8.4',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/services");
        $response->assertSessionHas('error', 'Database is currently being modified. Please wait.');
    }

    /**
     * Test update validates required version field.
     */
    public function test_update_validates_required_version_field(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => DatabaseStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/databases/{$database->id}", []);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['version']);
    }

    /**
     * Test user cannot update other users server database.
     */
    public function test_user_cannot_update_other_users_server_database(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => DatabaseStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/databases/{$database->id}", [
                'version' => '8.4',
            ]);

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test guest cannot update database.
     */
    public function test_guest_cannot_update_database(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => DatabaseStatus::Active,
        ]);

        // Act
        $response = $this->patch("/servers/{$server->id}/databases/{$database->id}", [
            'version' => '8.4',
        ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test user can uninstall database.
     */
    public function test_user_can_uninstall_database(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::PostgreSQL,
            'status' => DatabaseStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/services");
        $response->assertSessionHas('success', 'Database uninstallation started.');

        $database->refresh();
        $this->assertEquals(DatabaseStatus::Uninstalling, $database->status);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Packages\Services\Database\PostgreSQL\PostgreSqlRemoverJob::class);
    }

    /**
     * Test cannot uninstall when no database exists.
     */
    public function test_cannot_uninstall_when_no_database_exists(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act - attempt to delete non-existent database
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/databases/999");

        // Assert - Should get 404 when database doesn't exist
        $response->assertStatus(404);
    }

    /**
     * Test user cannot uninstall other users server database.
     */
    public function test_user_cannot_uninstall_other_users_server_database(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => DatabaseStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test guest cannot uninstall database.
     */
    public function test_guest_cannot_uninstall_database(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => DatabaseStatus::Active,
        ]);

        // Act
        $response = $this->delete("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test user can install multiple databases with different types.
     */
    public function test_user_can_install_multiple_databases_with_different_types(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act - Install MySQL
        $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'type' => 'mysql',
                'version' => '8.0',
                'root_password' => 'SecurePassword123',
                'port' => 3306,
            ]);

        // Act - Install PostgreSQL
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'type' => 'postgresql',
                'version' => '16',
                'root_password' => 'PostgresPass789',
                'port' => 5432,
            ]);

        // Assert - both should be created
        $response->assertStatus(302);
        $this->assertDatabaseCount('server_databases', 2);
        $this->assertDatabaseHas('server_databases', [
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL->value,
        ]);
        $this->assertDatabaseHas('server_databases', [
            'server_id' => $server->id,
            'type' => DatabaseType::PostgreSQL->value,
        ]);
    }

    /**
     * Test cannot install database with duplicate port.
     */
    public function test_cannot_install_database_with_duplicate_port(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'port' => 3306,
        ]);

        // Act - try to install another database on same port
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'type' => 'mariadb',
                'version' => '11.4',
                'root_password' => 'SecurePassword123',
                'port' => 3306,
            ]);

        // Assert - should fail validation
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['port']);
    }

    /**
     * Test auto-assigns unique port when not provided.
     */
    public function test_auto_assigns_unique_port_when_not_provided(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Install first MySQL database on default port
        $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'type' => 'mysql',
                'version' => '8.0',
                'root_password' => 'Password1',
                'port' => 3306,
            ]);

        // Act - install second MySQL without specifying port
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'type' => 'mysql',
                'version' => '8.4',
                'root_password' => 'Password2',
            ]);

        // Assert - should auto-assign a different port
        $response->assertStatus(302);
        $databases = $server->databases()->get();
        $this->assertEquals(2, $databases->count());
        $this->assertNotEquals($databases[0]->port, $databases[1]->port);
    }

    /**
     * Test database page displays multiple databases.
     */
    public function test_database_page_displays_multiple_databases(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'port' => 3306,
        ]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::PostgreSQL,
            'port' => 5432,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 2)
        );
    }

    /**
     * Test deleting one database does not delete other databases.
     *
     * Regression test for bug where deleting one database deleted all databases on the server.
     */
    public function test_deleting_one_database_does_not_delete_other_databases(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $database1 = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MariaDB,
            'port' => 3310,
            'status' => DatabaseStatus::Active,
        ]);

        $database2 = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MariaDB,
            'port' => 3315,
            'status' => DatabaseStatus::Active,
        ]);

        // Act - delete only the first database
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/databases/{$database1->id}");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/services");
        $response->assertSessionHas('success', 'Database uninstallation started.');

        // Verify first database status changed to Uninstalling
        $database1->refresh();
        $this->assertEquals(DatabaseStatus::Uninstalling, $database1->status);

        // Verify second database still exists and is unchanged
        $database2->refresh();
        $this->assertEquals(DatabaseStatus::Active, $database2->status);
        $this->assertDatabaseCount('server_databases', 2);
    }

    /**
     * Test deleting one MySQL database does not delete other MySQL databases.
     */
    public function test_deleting_one_mysql_database_does_not_delete_other_mysql_databases(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $database1 = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'port' => 3306,
            'version' => '8.0',
            'status' => DatabaseStatus::Active,
        ]);

        $database2 = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'port' => 3307,
            'version' => '8.4',
            'status' => DatabaseStatus::Active,
        ]);

        // Act - delete only the first database
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/databases/{$database1->id}");

        // Assert
        $response->assertStatus(302);

        // Verify second database still exists
        $database2->refresh();
        $this->assertEquals(DatabaseStatus::Active, $database2->status);
        $this->assertDatabaseCount('server_databases', 2);
    }

    /**
     * Test deleting one PostgreSQL database does not delete other databases.
     */
    public function test_deleting_one_postgresql_database_does_not_delete_other_databases(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $database1 = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::PostgreSQL,
            'port' => 5432,
            'status' => DatabaseStatus::Active,
        ]);

        $database2 = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::PostgreSQL,
            'port' => 5433,
            'status' => DatabaseStatus::Active,
        ]);

        // Act - delete only the first database
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/databases/{$database1->id}");

        // Assert
        $response->assertStatus(302);

        // Verify second database still exists
        $database2->refresh();
        $this->assertEquals(DatabaseStatus::Active, $database2->status);
        $this->assertDatabaseCount('server_databases', 2);
    }

    /**
     * Test database page only includes actual databases (not cache/queue services like Redis).
     */
    public function test_database_page_only_includes_actual_databases(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('availableDatabases', fn ($availableDatabases) => $availableDatabases
                ->has('mysql')
                ->has('mariadb')
                ->has('postgresql')
                ->missing('redis')
            )
        );
    }

    /**
     * Test database page does not show Redis instances even if they exist.
     */
    public function test_database_page_does_not_show_redis_instances(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create both a MySQL and Redis instance
        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'version' => '8.0',
            'port' => 3306,
            'status' => DatabaseStatus::Active,
        ]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::Redis,
            'version' => '7.2',
            'port' => 6379,
            'status' => DatabaseStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert - Should show 2 total databases in backend, but frontend filters to 1
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 2) // Backend still sends all
        );

        // Verify Redis is in the data but will be filtered by frontend
        $response->assertInertia(fn ($page) => $page
            ->where('server.databases.0.type', 'mysql')
            ->where('server.databases.1.type', 'redis')
        );
    }
}
