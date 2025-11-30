<?php

namespace Tests\Feature\Inertia\Servers;

use App\Models\Server;
use App\Models\ServerDatabase;
use App\Models\ServerDatabaseSchema;
use App\Models\ServerDatabaseUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseDetailsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test database details page renders correct Inertia component.
     */
    public function test_database_details_page_renders_correct_component(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => 'mysql',
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/database-details')
        );
    }

    /**
     * Test database details page provides server data in Inertia props.
     */
    public function test_database_details_page_provides_server_data_in_props(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Production Server',
        ]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => 'mysql',
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/database-details')
            ->has('server')
            ->where('server.vanity_name', 'Production Server')
        );
    }

    /**
     * Test database details page provides database data in Inertia props.
     */
    public function test_database_details_page_provides_database_data_in_props(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'name' => 'Production MySQL',
            'engine' => 'mysql',
            'version' => '8.0',
            'port' => 3306,
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/database-details')
            ->has('database')
            ->where('database.name', 'Production MySQL')
            ->where('database.engine', 'mysql')
            ->where('database.version', '8.0')
            ->where('database.port', 3306)
            ->where('database.status', 'active')
        );
    }

    /**
     * Test database details page shows database schemas.
     */
    public function test_database_details_page_shows_database_schemas(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => 'mysql',
            'status' => 'active',
        ]);
        ServerDatabaseSchema::factory()->create([
            'server_database_id' => $database->id,
            'name' => 'production_schema',
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/database-details')
            ->has('schemas', 1)
            ->where('schemas.0.name', 'production_schema')
            ->where('schemas.0.status', 'active')
        );
    }

    /**
     * Test database details page shows database users.
     */
    public function test_database_details_page_shows_database_users(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => 'mysql',
            'status' => 'active',
        ]);
        ServerDatabaseUser::factory()->create([
            'server_database_id' => $database->id,
            'username' => 'app_user',
            'host' => '%',
            'privileges' => 'all',
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/database-details')
            ->has('managedUsers', 1)
            ->where('managedUsers.0.username', 'app_user')
            ->where('managedUsers.0.host', '%')
            ->where('managedUsers.0.privileges', 'all')
            ->where('managedUsers.0.status', 'active')
        );
    }

    /**
     * Test database details page shows empty state when no schemas exist.
     */
    public function test_database_details_page_shows_empty_schemas_state(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => 'mysql',
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/database-details')
            ->has('schemas', 0)
        );
    }

    /**
     * Test database details page shows empty state when no users exist.
     */
    public function test_database_details_page_shows_empty_users_state(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => 'mysql',
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/database-details')
            ->has('managedUsers', 0)
        );
    }

    /**
     * Test database details page shows multiple schemas and users.
     */
    public function test_database_details_page_shows_multiple_schemas_and_users(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => 'mysql',
            'status' => 'active',
        ]);

        ServerDatabaseSchema::factory()->create([
            'server_database_id' => $database->id,
            'name' => 'schema_one',
            'status' => 'active',
        ]);

        ServerDatabaseSchema::factory()->create([
            'server_database_id' => $database->id,
            'name' => 'schema_two',
            'status' => 'active',
        ]);

        ServerDatabaseUser::factory()->create([
            'server_database_id' => $database->id,
            'username' => 'user_one',
            'status' => 'active',
        ]);

        ServerDatabaseUser::factory()->create([
            'server_database_id' => $database->id,
            'username' => 'user_two',
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/database-details')
            ->has('schemas', 2)
            ->has('managedUsers', 2)
        );
    }

    /**
     * Test database details page shows pending status.
     */
    public function test_database_details_page_shows_pending_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => 'mysql',
            'status' => 'pending',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/database-details')
            ->where('database.status', 'pending')
        );
    }

    /**
     * Test database details page shows failed status with error log.
     */
    public function test_database_details_page_shows_failed_status_with_error_log(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => 'mysql',
            'status' => 'failed',
            'error_log' => 'Installation failed: connection timeout',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/database-details')
            ->where('database.status', 'failed')
            ->has('database.error_log')
        );
    }

    /**
     * Test user cannot access other users database details.
     */
    public function test_user_cannot_access_other_users_database_details(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => 'mysql',
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test guest is redirected to login.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => 'mysql',
            'status' => 'active',
        ]);

        // Act
        $response = $this->get("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test database details page authentication state.
     */
    public function test_database_details_page_authentication_state(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => 'mysql',
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('auth.user')
            ->where('auth.user.email', 'test@example.com')
        );
    }

    /**
     * Test database details page shows user with schema access.
     */
    public function test_database_details_page_shows_user_with_schema_access(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => 'mysql',
            'status' => 'active',
        ]);

        $schema = ServerDatabaseSchema::factory()->create([
            'server_database_id' => $database->id,
            'name' => 'app_schema',
            'status' => 'active',
        ]);

        $dbUser = ServerDatabaseUser::factory()->create([
            'server_database_id' => $database->id,
            'username' => 'app_user',
            'status' => 'active',
        ]);

        $dbUser->schemas()->attach($schema->id);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/database-details')
            ->has('managedUsers.0.schemas', 1)
        );
    }

    /**
     * Test database details page shows root user with is_root flag.
     */
    public function test_database_details_page_shows_root_user_with_is_root_flag(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => 'mysql',
            'status' => 'active',
        ]);

        ServerDatabaseUser::factory()->create([
            'server_database_id' => $database->id,
            'is_root' => true,
            'username' => 'root',
            'host' => 'localhost',
            'privileges' => 'all',
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/database-details')
            ->has('managedUsers', 1)
            ->where('managedUsers.0.username', 'root')
            ->where('managedUsers.0.is_root', true)
            ->where('managedUsers.0.host', 'localhost')
        );
    }

    /**
     * Test database details page shows both root and non-root users.
     */
    public function test_database_details_page_shows_both_root_and_non_root_users(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => 'mysql',
            'status' => 'active',
        ]);

        // Create root user
        ServerDatabaseUser::factory()->create([
            'server_database_id' => $database->id,
            'is_root' => true,
            'username' => 'root',
            'host' => 'localhost',
            'status' => 'active',
        ]);

        // Create regular user
        ServerDatabaseUser::factory()->create([
            'server_database_id' => $database->id,
            'is_root' => false,
            'username' => 'app_user',
            'host' => '%',
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/database-details')
            ->has('managedUsers', 2)
            ->where('managedUsers.0.is_root', false) // Latest first (app_user)
            ->where('managedUsers.0.username', 'app_user')
            ->where('managedUsers.1.is_root', true) // root user
            ->where('managedUsers.1.username', 'root')
        );
    }

    /**
     * Test database details page includes is_root flag for all users.
     */
    public function test_database_details_page_includes_is_root_flag_for_all_users(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => 'mysql',
            'status' => 'active',
        ]);

        // Create non-root user
        ServerDatabaseUser::factory()->create([
            'server_database_id' => $database->id,
            'is_root' => false,
            'username' => 'regular_user',
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/databases/{$database->id}");

        // Assert - verify non-root user has is_root: false
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/database-details')
            ->has('managedUsers', 1)
            ->where('managedUsers.0.is_root', false)
        );
    }

    /**
     * Test database details page shows PostgreSQL postgres user as root.
     */
    public function test_database_details_page_shows_postgres_user_as_root(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => 'postgresql',
            'status' => 'active',
        ]);

        ServerDatabaseUser::factory()->create([
            'server_database_id' => $database->id,
            'is_root' => true,
            'username' => 'postgres',
            'host' => 'localhost',
            'privileges' => 'all',
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/databases/{$database->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/database-details')
            ->has('managedUsers', 1)
            ->where('managedUsers.0.username', 'postgres')
            ->where('managedUsers.0.is_root', true)
        );
    }
}
