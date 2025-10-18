<?php

namespace Tests\Feature\Inertia\Servers;

use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test manage sites navigation returns 200 and renders correct component.
     */
    public function test_manage_sites_navigation_returns_200(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server')
        );
    }

    /**
     * Test php navigation returns 200 and renders correct component.
     */
    public function test_php_navigation_returns_200(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/php");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/php')
            ->has('server')
        );
    }

    /**
     * Test database navigation returns 200 and renders correct component.
     */
    public function test_database_navigation_returns_200(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/databases");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/databases')
            ->has('server')
        );
    }

    /**
     * Test firewall navigation returns 200 and renders correct component.
     */
    public function test_firewall_navigation_returns_200(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/firewall");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/firewall')
            ->has('server')
        );
    }

    /**
     * Test monitoring navigation returns 200 and renders correct component.
     */
    public function test_monitoring_navigation_returns_200(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/monitoring");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/monitoring')
            ->has('server')
        );
    }

    /**
     * Test scheduler navigation returns 200 and renders correct component.
     */
    public function test_scheduler_navigation_returns_200(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/scheduler");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/scheduler')
            ->has('server')
        );
    }

    /**
     * Test supervisor navigation returns 200 and renders correct component.
     */
    public function test_supervisor_navigation_returns_200(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/supervisor");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/supervisor')
            ->has('server')
        );
    }

    /**
     * Test settings navigation returns 200 and renders correct component.
     */
    public function test_settings_navigation_returns_200(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/settings");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/settings')
            ->has('server')
        );
    }

    /**
     * Test guest cannot access any navigation pages.
     */
    public function test_guest_cannot_access_navigation_pages(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $pages = [
            "/servers/{$server->id}/sites",
            "/servers/{$server->id}/php",
            "/servers/{$server->id}/databases",
            "/servers/{$server->id}/firewall",
            "/servers/{$server->id}/monitoring",
            "/servers/{$server->id}/scheduler",
            "/servers/{$server->id}/supervisor",
            "/servers/{$server->id}/settings",
        ];

        // Act & Assert
        foreach ($pages as $page) {
            $response = $this->get($page);
            $response->assertStatus(302);
            $response->assertRedirect('/login');
        }
    }

    /**
     * Test user cannot access other users server navigation pages.
     */
    public function test_user_cannot_access_other_users_server_navigation_pages(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        $pages = [
            "/servers/{$server->id}/sites",
            "/servers/{$server->id}/php",
            "/servers/{$server->id}/databases",
            "/servers/{$server->id}/firewall",
            "/servers/{$server->id}/monitoring",
            "/servers/{$server->id}/scheduler",
            "/servers/{$server->id}/supervisor",
            "/servers/{$server->id}/settings",
        ];

        // Act & Assert
        foreach ($pages as $page) {
            $response = $this->actingAs($user)->get($page);
            $response->assertStatus(403);
        }
    }

    /**
     * Test all navigation pages include server data.
     */
    public function test_all_navigation_pages_include_server_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Test Server',
        ]);

        $pages = [
            "/servers/{$server->id}/sites",
            "/servers/{$server->id}/php",
            "/servers/{$server->id}/databases",
            "/servers/{$server->id}/firewall",
            "/servers/{$server->id}/monitoring",
            "/servers/{$server->id}/scheduler",
            "/servers/{$server->id}/supervisor",
            "/servers/{$server->id}/settings",
        ];

        // Act & Assert
        foreach ($pages as $page) {
            $response = $this->actingAs($user)->get($page);
            $response->assertStatus(200);
            $response->assertInertia(fn ($p) => $p
                ->has('server')
                ->where('server.id', $server->id)
                ->where('server.vanity_name', 'Test Server')
            );
        }
    }

    /**
     * Test server show page redirects to provisioning setup.
     */
    public function test_server_show_page_redirects_to_provisioning_setup(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}");

        // Assert - Server show page redirects to provisioning setup
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/provisioning/setup");
    }
}
