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
        $response = $this->get("/servers/{$server->id}/database");

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
            ->get("/servers/{$server->id}/database");

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
            ->get("/servers/{$server->id}/database");

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
            ->get("/servers/{$server->id}/database");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/database')
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
            ->get("/servers/{$server->id}/database");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/database')
            ->where('server.id', $server->id)
            ->where('server.vanity_name', 'Database Server')
        );
    }

    /**
     * Test database page includes installed database.
     */
    public function test_database_page_includes_installed_database(): void
    {
        // Arrange
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
            ->get("/servers/{$server->id}/database");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/database')
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
            ->get("/servers/{$server->id}/database");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/database')
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
            ->get("/servers/{$server->id}/database");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/database')
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
            ->post("/servers/{$server->id}/database", [
                'name' => 'MySQL',
                'type' => 'mysql',
                'version' => '8.0',
                'root_password' => 'SecurePassword123',
                'port' => 3306,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/database");
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
            ->post("/servers/{$server->id}/database", [
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
            ->post("/servers/{$server->id}/database", [
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
     * Test cannot install duplicate database.
     */
    public function test_cannot_install_duplicate_database(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/database", [
                'type' => 'mysql',
                'version' => '8.0',
                'root_password' => 'SecurePassword123',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/database");
        $response->assertSessionHas('error', 'A database is already installed on this server.');
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
            ->post("/servers/{$server->id}/database", [
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
            ->post("/servers/{$server->id}/database", [
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
            ->post("/servers/{$server->id}/database", [
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
            ->post("/servers/{$server->id}/database", [
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
            ->post("/servers/{$server->id}/database", [
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
            ->post("/servers/{$server->id}/database", [
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
            ->post("/servers/{$server->id}/database", [
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
            ->post("/servers/{$server->id}/database", [
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
            ->post("/servers/{$server->id}/database", [
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
        $response = $this->post("/servers/{$server->id}/database", [
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
            ->post("/servers/{$server->id}/database", [
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
            ->patch("/servers/{$server->id}/database", [
                'version' => '8.4',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/database");
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
            ->patch("/servers/{$server->id}/database", [
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
            ->patch("/servers/{$server->id}/database", [
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

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/database", [
                'version' => '8.4',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/database");
        $response->assertSessionHas('error', 'No database found to update.');
    }

    /**
     * Test cannot update when database is installing.
     */
    public function test_cannot_update_when_database_is_installing(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => DatabaseStatus::Installing,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/database", [
                'version' => '8.4',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/database");
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

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => DatabaseStatus::Uninstalling,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/database", [
                'version' => '8.4',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/database");
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

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => DatabaseStatus::Updating,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/database", [
                'version' => '8.4',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/database");
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

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => DatabaseStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/database", []);

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

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => DatabaseStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/database", [
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

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => DatabaseStatus::Active,
        ]);

        // Act
        $response = $this->patch("/servers/{$server->id}/database", [
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
            ->delete("/servers/{$server->id}/database");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/database");
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

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/database");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/database");
        $response->assertSessionHas('error', 'No database found to uninstall.');
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

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => DatabaseStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/database");

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

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => DatabaseStatus::Active,
        ]);

        // Act
        $response = $this->delete("/servers/{$server->id}/database");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test status endpoint returns database status JSON.
     */
    public function test_status_endpoint_returns_database_status_json(): void
    {
        // Arrange
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
            ->get("/servers/{$server->id}/database/status");

        // Assert
        $response->assertStatus(200);
        $response->assertJson([
            'status' => DatabaseStatus::Active->value,
            'database' => [
                'id' => $database->id,
                'type' => DatabaseType::MySQL->value,
                'version' => '8.0',
                'status' => DatabaseStatus::Active->value,
            ],
        ]);
    }

    /**
     * Test status endpoint returns uninstalled when no database exists.
     */
    public function test_status_endpoint_returns_uninstalled_when_no_database_exists(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/database/status");

        // Assert
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'uninstalled',
            'database' => null,
        ]);
    }

    /**
     * Test user can check status of their server database.
     */
    public function test_user_can_check_status_of_their_server_database(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'status' => DatabaseStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/database/status");

        // Assert
        $response->assertStatus(200);
    }

    /**
     * Test user cannot check status of other users server database.
     */
    public function test_user_cannot_check_status_of_other_users_server_database(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'status' => DatabaseStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/database/status");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test guest cannot check database status.
     */
    public function test_guest_cannot_check_database_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'status' => DatabaseStatus::Active,
        ]);

        // Act
        $response = $this->get("/servers/{$server->id}/database/status");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }
}
