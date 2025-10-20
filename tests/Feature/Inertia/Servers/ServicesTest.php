<?php

namespace Tests\Feature\Inertia\Servers;

use App\Enums\DatabaseStatus;
use App\Enums\DatabaseType;
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
            'type' => DatabaseType::MySQL,
            'version' => '8.0',
            'port' => 3306,
            'status' => DatabaseStatus::Active,
        ]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::PostgreSQL,
            'version' => '16',
            'port' => 5432,
            'status' => DatabaseStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 2)
            ->where('server.databases.0.type', 'mysql')
            ->where('server.databases.1.type', 'postgresql')
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
            'type' => DatabaseType::Redis,
            'version' => '7.2',
            'port' => 6379,
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
            ->where('server.databases.0.type', 'redis')
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
            'type' => DatabaseType::MySQL,
            'version' => '8.0',
            'port' => 3306,
            'status' => DatabaseStatus::Active,
        ]);

        // Create a cache/queue service
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

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 2)
            ->where('server.databases.0.type', 'mysql')
            ->where('server.databases.1.type', 'redis')
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
            'type' => DatabaseType::MySQL,
            'port' => 3306,
            'status' => DatabaseStatus::Installing,
        ]);

        $redis = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::Redis,
            'port' => 6379,
            'status' => DatabaseStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert - just verify both services with their statuses exist
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 2)
            ->where('server.databases.0.type', 'mysql')
            ->where('server.databases.0.status', DatabaseStatus::Installing->value)
            ->where('server.databases.1.type', 'redis')
            ->where('server.databases.1.status', DatabaseStatus::Active->value)
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
    public function test_services_page_includes_all_database_types_in_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create one of each type with unique ports
        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MySQL,
            'port' => 3306,
        ]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::MariaDB,
            'port' => 3307,
        ]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::PostgreSQL,
            'port' => 5432,
        ]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::Redis,
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
}
