<?php

namespace Tests\Inertia\Servers;

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
            'vanity_name' => 'Production Server',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server')
            ->where('server.vanity_name', 'Production Server')
        );
    }

    /**
     * Test services page shows installed databases.
     */
    public function test_services_page_shows_installed_databases(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'name' => 'Production MySQL',
            'engine' => 'mysql',
            'version' => '8.0',
            'port' => 3306,
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 1)
            ->where('server.databases.0.name', 'Production MySQL')
            ->where('server.databases.0.engine', 'mysql')
            ->where('server.databases.0.status', 'active')
        );
    }

    /**
     * Test services page shows empty state when no databases installed.
     */
    public function test_services_page_shows_empty_state(): void
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
     * Test services page provides available database types.
     */
    public function test_services_page_provides_available_database_engines(): void
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
     * Test services page provides available cache/queue types.
     */
    public function test_services_page_provides_available_cache_queue_types(): void
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
     * Test services page separates databases from cache/queue services.
     */
    public function test_services_page_separates_databases_from_cache_queue(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => 'mysql',
            'version' => '8.0',
            'port' => 3306,
            'status' => 'active',
        ]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => 'redis',
            'version' => '7.2',
            'port' => 6379,
            'status' => 'active',
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
     * Test services page shows database status correctly.
     */
    public function test_services_page_shows_database_status_correctly(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => 'mysql',
            'version' => '8.0',
            'port' => 3306,
            'status' => 'pending',
        ]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => 'redis',
            'version' => '7.2',
            'port' => 6379,
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->where('server.databases.0.status', 'pending')
            ->where('server.databases.1.status', 'active')
        );
    }

    /**
     * Test services page includes database port information.
     */
    public function test_services_page_includes_database_port_information(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => 'mysql',
            'version' => '8.0',
            'port' => 3307,
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->where('server.databases.0.port', 3307)
        );
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
     * Test guest is redirected to login.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act
        $response = $this->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test services page shows failed database installations.
     */
    public function test_services_page_shows_failed_database_installations(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => 'mysql',
            'version' => '8.0',
            'port' => 3306,
            'status' => 'failed',
            'error_log' => 'Installation failed',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 1)
            ->where('server.databases.0.status', 'failed')
            ->has('server.databases.0.error_log')
        );
    }

    /**
     * Test services page shows database version information.
     */
    public function test_services_page_shows_database_version_information(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'engine' => 'mariadb',
            'version' => '11.4',
            'port' => 3306,
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->where('server.databases.0.version', '11.4')
        );
    }

    /**
     * Test services page authentication state.
     */
    public function test_services_page_authentication_state(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('auth.user')
            ->where('auth.user.email', 'test@example.com')
        );
    }
}
