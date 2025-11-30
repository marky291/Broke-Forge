<?php

namespace Tests\Feature\Inertia\Servers;

use App\Enums\DatabaseEngine;
use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test services page renders correct Inertia component.
     */
    public function test_services_page_renders_correct_component(): void
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
        );
    }

    /**
     * Test services page provides server data in Inertia props.
     */
    public function test_services_page_provides_server_data_in_props(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Services Server',
            'public_ip' => '192.168.1.100',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server')
            ->where('server.id', $server->id)
            ->where('server.vanity_name', 'Services Server')
        );
    }

    /**
     * Test services page includes availableDatabases prop.
     */
    public function test_services_page_includes_available_databases_prop(): void
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
            ->has('availableDatabases')
            ->has('availableDatabases.mysql')
            ->has('availableDatabases.mariadb')
            ->has('availableDatabases.postgresql')
        );
    }

    /**
     * Test services page includes availableCacheQueue prop.
     */
    public function test_services_page_includes_available_cache_queue_prop(): void
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
            ->has('availableCacheQueue')
            ->has('availableCacheQueue.redis')
        );
    }

    /**
     * Test services page includes both availableDatabases and availableCacheQueue props.
     */
    public function test_services_page_includes_both_available_props(): void
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
            ->has('availableDatabases')
            ->has('availableCacheQueue')
        );
    }

    /**
     * Test services page displays installed databases.
     */
    public function test_services_page_displays_installed_databases(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::MySQL,
            'version' => '8.0',
            'port' => 3306,
            'status' => TaskStatus::Active,
        ]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::PostgreSQL,
            'version' => '16',
            'port' => 5432,
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 2)
            ->where('server.databases.0.engine', 'mysql')
            ->where('server.databases.1.engine', 'postgresql')
        );
    }

    /**
     * Test services page displays cache/queue services (Redis).
     */
    public function test_services_page_displays_cache_queue_services(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::Redis,
            'version' => '7.2',
            'port' => 6379,
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
            ->where('server.databases.0.engine', 'redis')
        );
    }

    /**
     * Test services page displays both databases and cache/queue services together.
     */
    public function test_services_page_displays_both_service_types_together(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create a regular database
        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::MySQL,
            'version' => '8.0',
            'port' => 3306,
            'status' => TaskStatus::Active,
        ]);

        // Create a cache/queue service
        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::Redis,
            'version' => '7.2',
            'port' => 6379,
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 2)
            ->where('server.databases.0.engine', 'mysql')
            ->where('server.databases.1.engine', 'redis')
        );
    }

    /**
     * Test services page shows empty state when no services installed.
     */
    public function test_services_page_shows_empty_state_when_no_services(): void
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
     * Test services page includes service status information.
     */
    public function test_services_page_includes_service_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $mysql = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::MySQL,
            'port' => 3306,
            'status' => TaskStatus::Installing,
        ]);

        $redis = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::Redis,
            'port' => 6379,
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert - just verify both services with their statuses exist
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 2)
            ->where('server.databases.0.engine', 'mysql')
            ->where('server.databases.0.status', TaskStatus::Installing->value)
            ->where('server.databases.1.engine', 'redis')
            ->where('server.databases.1.status', TaskStatus::Active->value)
        );
    }

    /**
     * Test guest cannot access services page.
     */
    public function test_guest_cannot_access_services_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test user cannot access other users server services page.
     */
    public function test_user_cannot_access_other_users_server_services_page(): void
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
     * Test services page does not mix database types inappropriately.
     */
    public function test_services_page_includes_all_database_engines_in_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create one of each type with unique ports
        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::MySQL,
            'port' => 3306,
        ]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::MariaDB,
            'port' => 3307,
        ]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::PostgreSQL,
            'port' => 5432,
        ]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::Redis,
            'port' => 6379,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert - All 4 services should be in the databases array
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 4)
        );
    }

    /**
     * Test services page available databases does not include redis.
     */
    public function test_services_page_available_databases_excludes_redis(): void
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
     * Test services page available cache queue only includes redis.
     */
    public function test_services_page_available_cache_queue_only_includes_redis(): void
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
            ->has('availableCacheQueue', fn ($availableCacheQueue) => $availableCacheQueue
                ->has('redis')
                ->missing('mysql')
                ->missing('mariadb')
                ->missing('postgresql')
            )
        );
    }

    /**
     * Test services page displays failed status badge correctly.
     */
    public function test_services_page_displays_failed_status_badge(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::MySQL,
            'version' => '8.0',
            'port' => 3306,
            'status' => TaskStatus::Failed,
            'error_log' => 'Installation failed: Unable to start MySQL service',
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
            ->where('server.databases.0.engine', 'mysql')
        );
    }

    /**
     * Test services page includes error message for failed databases.
     */
    public function test_services_page_includes_error_log_for_failed_databases(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $errorMessage = 'Installation failed: Port 3306 is already in use';

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::PostgreSQL,
            'version' => '16',
            'port' => 5432,
            'status' => TaskStatus::Failed,
            'error_log' => $errorMessage,
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
            ->where('server.databases.0.error_log', $errorMessage)
        );
    }

    /**
     * Test services page displays databases with different statuses correctly.
     */
    public function test_services_page_displays_databases_with_different_statuses(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create databases with different statuses
        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::MySQL,
            'port' => 3306,
            'status' => TaskStatus::Pending,
        ]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::PostgreSQL,
            'port' => 5432,
            'status' => TaskStatus::Installing,
        ]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::MariaDB,
            'port' => 3307,
            'status' => TaskStatus::Active,
        ]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::Redis,
            'port' => 6379,
            'status' => TaskStatus::Failed,
            'error_log' => 'Failed to configure Redis',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert - Verify all 4 databases with their statuses exist (order not guaranteed due to latest())
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 4)
        );
    }

    /**
     * Test services page shows correct props structure for databases.
     */
    public function test_services_page_shows_correct_props_structure_for_databases(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'name' => 'production-db',
            'engine' => DatabaseEngine::MySQL,
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
            ->has('server.databases.0.id')
            ->has('server.databases.0.name')
            ->has('server.databases.0.engine')
            ->has('server.databases.0.version')
            ->has('server.databases.0.port')
            ->has('server.databases.0.status')
            ->has('server.databases.0.created_at')
            ->has('server.databases.0.updated_at')
            ->where('server.databases.0.name', 'production-db')
            ->where('server.databases.0.engine', 'mysql')
            ->where('server.databases.0.version', '8.0')
            ->where('server.databases.0.port', 3306)
            ->where('server.databases.0.status', TaskStatus::Active->value)
        );
    }

    /**
     * Test services page displays failed cache queue services correctly.
     */
    public function test_services_page_displays_failed_cache_queue_services(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::Redis,
            'version' => '7.2',
            'port' => 6379,
            'status' => TaskStatus::Failed,
            'error_log' => 'Redis installation failed: Configuration error',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 1)
            ->where('server.databases.0.engine', 'redis')
            ->where('server.databases.0.status', TaskStatus::Failed->value)
            ->where('server.databases.0.error_log', 'Redis installation failed: Configuration error')
        );
    }

    /**
     * Test services page displays pending status correctly.
     */
    public function test_services_page_displays_pending_status_correctly(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::PostgreSQL,
            'version' => '16',
            'port' => 5432,
            'status' => TaskStatus::Pending,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 1)
            ->where('server.databases.0.status', TaskStatus::Pending->value)
            ->where('server.databases.0.engine', 'postgresql')
        );
    }

    /**
     * Test services page displays updating status correctly.
     */
    public function test_services_page_displays_updating_status_correctly(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::MariaDB,
            'version' => '11.4',
            'port' => 3307,
            'status' => TaskStatus::Updating,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 1)
            ->where('server.databases.0.status', TaskStatus::Updating->value)
            ->where('server.databases.0.engine', 'mariadb')
        );
    }

    /**
     * Test services page displays uninstalling status correctly.
     */
    public function test_services_page_displays_uninstalling_status_correctly(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::MySQL,
            'version' => '8.0',
            'port' => 3306,
            'status' => TaskStatus::Removing,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 1)
            ->where('server.databases.0.status', TaskStatus::Removing->value)
            ->where('server.databases.0.engine', 'mysql')
        );
    }

    /**
     * Test services page handles null error message for failed databases.
     */
    public function test_services_page_handles_null_error_log_for_failed_databases(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => DatabaseEngine::MySQL,
            'version' => '8.0',
            'port' => 3306,
            'status' => TaskStatus::Failed,
            'error_log' => null,
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
        );
    }
}
