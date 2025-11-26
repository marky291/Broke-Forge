<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\DatabaseType;
use App\Enums\TaskStatus;
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
            'status' => TaskStatus::Active,
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
            'status' => TaskStatus::Installing,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 1)
            ->where('server.databases.0.status', TaskStatus::Installing->value)
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
            'status' => TaskStatus::Pending->value,
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
                'name' => 'mariadb_test',
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
            'status' => TaskStatus::Pending->value,
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
                'name' => 'postgres_db',
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
            'status' => TaskStatus::Pending->value,
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
                'name' => 'test_db',
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
                'name' => 'test_db',
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
                'name' => 'test_db',
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
                'name' => 'test_db',
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
                'name' => 'test_db',
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
                'name' => 'test_db',
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
                'name' => 'test_db',
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
                'name' => 'test_db',
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
                'name' => 'test_db',
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
            'name' => 'test_db',
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
                'name' => 'mysql',
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
     * Test creating database returns record with pending status.
     */
    public function test_creating_database_returns_record_with_pending_status(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'name' => 'postgres_test',
                'type' => 'postgresql',
                'version' => '16',
                'root_password' => 'SecurePassword123',
                'port' => 5432,
            ]);

        // Assert
        $response->assertStatus(302);

        $database = $server->databases()->first();
        $this->assertNotNull($database);
        $this->assertEquals(TaskStatus::Pending, $database->status);

        $this->assertDatabaseHas('server_databases', [
            'server_id' => $server->id,
            'type' => DatabaseType::PostgreSQL->value,
            'status' => TaskStatus::Pending->value,
        ]);
    }

    /**
     * Test error message is accessible in database record after failure.
     */
    public function test_error_log_is_accessible_in_database_record_after_failure(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create a database with failed status and error message
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'version' => '8.0',
            'port' => 3306,
            'status' => TaskStatus::Failed,
            'error_log' => 'Installation failed: Connection timeout',
        ]);

        // Act - Refresh the model to ensure we can read the error_log
        $database->refresh();

        // Assert - error_log should be accessible
        $this->assertEquals('Installation failed: Connection timeout', $database->error_log);
        $this->assertEquals(TaskStatus::Failed, $database->status);

        $this->assertDatabaseHas('server_databases', [
            'id' => $database->id,
            'status' => TaskStatus::Failed->value,
            'error_log' => 'Installation failed: Connection timeout',
        ]);
    }

    /**
     * Test database can transition from pending to failed with error message.
     */
    public function test_database_can_transition_from_pending_to_failed_with_error_log(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create database in pending status
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MariaDB,
            'version' => '11.4',
            'port' => 3310,
            'status' => TaskStatus::Pending,
            'error_log' => null,
        ]);

        // Act - Simulate job failure by updating to failed status with error message
        $database->update([
            'status' => TaskStatus::Failed,
            'error_log' => 'Package installation failed: mariadb-server not found',
        ]);

        // Assert
        $database->refresh();
        $this->assertEquals(TaskStatus::Failed, $database->status);
        $this->assertEquals('Package installation failed: mariadb-server not found', $database->error_log);

        $this->assertDatabaseHas('server_databases', [
            'id' => $database->id,
            'status' => TaskStatus::Failed->value,
            'error_log' => 'Package installation failed: mariadb-server not found',
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
            'status' => TaskStatus::Active,
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
        $this->assertEquals(TaskStatus::Updating, $database->status);

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
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/databases/{$database->id}", [
                'version' => '8.4',
            ]);

        // Assert - new version should be stored on record
        $database->refresh();
        $this->assertEquals('8.4', $database->version);
        $this->assertEquals(TaskStatus::Updating, $database->status);
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
            'status' => TaskStatus::Active,
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
                return $job->serverDatabase->id === $database->id;
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
            'status' => TaskStatus::Installing,
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
            'status' => TaskStatus::Removing,
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
            'status' => TaskStatus::Updating,
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
            'status' => TaskStatus::Active,
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
            'status' => TaskStatus::Active,
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
            'status' => TaskStatus::Active,
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
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/services");
        $response->assertSessionHas('success', 'Database uninstallation started.');

        $database->refresh();
        $this->assertEquals(TaskStatus::Pending, $database->status);

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
            'status' => TaskStatus::Active,
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
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->delete("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test user cannot install multiple databases (same category).
     */
    public function test_user_cannot_install_multiple_databases_same_category(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act - Install MySQL
        $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'name' => 'mysql_test',
                'type' => 'mysql',
                'version' => '8.0',
                'root_password' => 'SecurePassword123',
                'port' => 3306,
            ]);

        // Act - Attempt to install PostgreSQL (same database category)
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'name' => 'postgres_test',
                'type' => 'postgresql',
                'version' => '16',
                'root_password' => 'PostgresPass789',
                'port' => 5432,
            ]);

        // Assert - second database installation should fail
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['type']);
        $this->assertEquals(1, $server->databases()->count());
        $this->assertDatabaseHas('server_databases', [
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL->value,
        ]);
        $this->assertDatabaseMissing('server_databases', [
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
                'name' => 'mariadb_test',
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
     * Test can install database and cache service (different categories).
     */
    public function test_can_install_database_and_cache_service_different_categories(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Install MySQL database
        $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'name' => 'mysql_prod',
                'type' => 'mysql',
                'version' => '8.0',
                'root_password' => 'Password1',
                'port' => 3306,
            ]);

        // Act - install Redis (different category - cache/queue)
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'name' => 'redis_cache',
                'type' => 'redis',
                'version' => '7.2',
                'root_password' => 'Password2',
                'port' => 6379,
            ]);

        // Assert - both should be installed (different categories)
        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();
        $databases = $server->databases()->get();
        $this->assertEquals(2, $databases->count());
        $this->assertDatabaseHas('server_databases', [
            'server_id' => $server->id,
            'type' => 'mysql',
        ]);
        $this->assertDatabaseHas('server_databases', [
            'server_id' => $server->id,
            'type' => 'redis',
        ]);
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
     * Test deleting database does not delete cache service (different category).
     *
     * Regression test for bug where deleting one service deleted all services on the server.
     */
    public function test_deleting_database_does_not_delete_cache_service(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'port' => 3306,
            'status' => TaskStatus::Active,
        ]);

        $cacheService = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::Redis,
            'port' => 6379,
            'status' => TaskStatus::Active,
        ]);

        // Act - delete the database
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/services");
        $response->assertSessionHas('success', 'Database uninstallation started.');

        // Verify database status changed to Pending
        $database->refresh();
        $this->assertEquals(TaskStatus::Pending, $database->status);

        // Verify cache service still exists and is unchanged
        $cacheService->refresh();
        $this->assertEquals(TaskStatus::Active, $cacheService->status);
        $this->assertEquals(2, $server->databases()->count());
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
            'status' => TaskStatus::Active,
        ]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::Redis,
            'version' => '7.2',
            'port' => 6379,
            'status' => TaskStatus::Active,
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

    /**
     * Test guest cannot access database detail page.
     */
    public function test_guest_cannot_access_database_detail_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
        ]);

        // Act
        $response = $this->get("/servers/{$server->id}/databases/{$database->id}");

        // Assert - guests should be redirected to login
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test authenticated user can access their MySQL database detail page.
     */
    public function test_user_can_access_their_mysql_database_detail_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(200);
    }

    /**
     * Test authenticated user can access their MariaDB database detail page.
     */
    public function test_user_can_access_their_mariadb_database_detail_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MariaDB,
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(200);
    }

    /**
     * Test authenticated user can access their PostgreSQL database detail page.
     */
    public function test_user_can_access_their_postgresql_database_detail_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::PostgreSQL,
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(200);
    }

    /**
     * Test user cannot access other users database detail page.
     */
    public function test_user_cannot_access_other_users_database_detail_page(): void
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
            ->get("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test database detail page renders correct Inertia component.
     */
    public function test_database_detail_page_renders_correct_inertia_component(): void
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
            ->get("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/database-details')
            ->has('server')
            ->has('database')
        );
    }

    /**
     * Test database detail page includes database data.
     */
    public function test_database_detail_page_includes_database_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Database Server',
        ]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'name' => 'Production MySQL',
            'type' => DatabaseType::MySQL,
            'version' => '8.0',
            'port' => 3306,
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/database-details')
            ->where('database.id', $database->id)
            ->where('database.name', 'Production MySQL')
            ->where('database.type', 'mysql')
            ->where('database.version', '8.0')
            ->where('database.port', 3306)
            ->where('database.status', 'active')
        );
    }

    /**
     * Test database detail page only allows MySQL, MariaDB, and PostgreSQL (404 for Redis).
     */
    public function test_database_detail_page_only_allows_supported_database_types(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $redisDatabase = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::Redis,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/databases/{$redisDatabase->id}");

        // Assert - Redis should return 404
        $response->assertStatus(404);
    }

    /**
     * Test database must belong to the server (404 for wrong database).
     */
    public function test_database_must_belong_to_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server1 = Server::factory()->create(['user_id' => $user->id]);
        $server2 = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server2->id,
            'type' => DatabaseType::MySQL,
        ]);

        // Act - Try to access database from server2 using server1's URL
        $response = $this->actingAs($user)
            ->get("/servers/{$server1->id}/databases/{$database->id}");

        // Assert - Should return 404 because database doesn't belong to server1
        $response->assertStatus(404);
    }

    /**
     * Test cannot uninstall database when sites are using it.
     */
    public function test_cannot_uninstall_database_when_sites_are_using_it(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => TaskStatus::Active,
        ]);

        // Create sites using this database
        \App\Models\ServerSite::factory()->create([
            'server_id' => $server->id,
            'database_id' => $database->id,
            'domain' => 'example.com',
        ]);

        \App\Models\ServerSite::factory()->create([
            'server_id' => $server->id,
            'database_id' => $database->id,
            'domain' => 'test.com',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/services");
        $response->assertSessionHas('error');

        // Database should still be active (not deleted)
        $database->refresh();
        $this->assertEquals(TaskStatus::Active, $database->status);
    }

    /**
     * Test error message includes site names when database cannot be uninstalled.
     */
    public function test_error_message_includes_site_names_when_database_cannot_be_uninstalled(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => TaskStatus::Active,
        ]);

        // Create sites using this database
        \App\Models\ServerSite::factory()->create([
            'server_id' => $server->id,
            'database_id' => $database->id,
            'domain' => 'wordpress.com',
        ]);

        \App\Models\ServerSite::factory()->create([
            'server_id' => $server->id,
            'database_id' => $database->id,
            'domain' => 'laravel.com',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('error');

        $errorMessage = session('error');
        $this->assertStringContainsString('2 sites', $errorMessage);
        $this->assertStringContainsString('wordpress.com', $errorMessage);
        $this->assertStringContainsString('laravel.com', $errorMessage);
        $this->assertStringContainsString('delete these sites', $errorMessage);
    }

    /**
     * Test error message uses singular form when one site is using database.
     */
    public function test_error_message_uses_singular_form_for_one_site(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::PostgreSQL,
            'status' => TaskStatus::Active,
        ]);

        // Create one site using this database
        \App\Models\ServerSite::factory()->create([
            'server_id' => $server->id,
            'database_id' => $database->id,
            'domain' => 'example.com',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('error');

        $errorMessage = session('error');
        $this->assertStringContainsString('1 site currently depend', $errorMessage);
        $this->assertStringNotContainsString('2 sites', $errorMessage);
        $this->assertStringContainsString('example.com', $errorMessage);
    }

    /**
     * Test database services page includes sites_count for each database.
     */
    public function test_database_services_page_includes_sites_count(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => TaskStatus::Active,
        ]);

        // Create sites using this database
        \App\Models\ServerSite::factory()->count(3)->create([
            'server_id' => $server->id,
            'database_id' => $database->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 1)
            ->where('server.databases.0.sites_count', 3)
        );
    }

    /**
     * Test database services page shows zero sites_count for database with no sites.
     */
    public function test_database_services_page_shows_zero_sites_count_for_database_with_no_sites(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 1)
            ->where('server.databases.0.sites_count', 0)
        );
    }

    /**
     * Test database services page includes error_log for failed databases.
     */
    public function test_database_services_page_includes_error_log_for_failed_databases(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'status' => TaskStatus::Failed,
            'error_log' => 'Installation failed: Connection timeout to package server',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 1)
            ->where('server.databases.0.status', TaskStatus::Failed->value)
            ->where('server.databases.0.error_log', 'Installation failed: Connection timeout to package server')
        );
    }
}
