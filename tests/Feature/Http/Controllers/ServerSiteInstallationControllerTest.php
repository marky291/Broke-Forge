<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AvailableFramework;
use App\Models\Server;
use App\Models\ServerSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerSiteInstallationControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test guest cannot access site installation page.
     */
    public function test_guest_cannot_access_site_installation_page(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->laravel()->create(['server_id' => $server->id]);

        // Act
        $response = $this->get(route('servers.sites.installing', [$server, $site]));

        // Assert
        $response->assertRedirect(route('login'));
    }

    /**
     * Test user can access their site installation page.
     */
    public function test_user_can_access_their_site_installation_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->laravel()->create([
            'server_id' => $server->id,
            'status' => 'installing',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get(route('servers.sites.installing', [$server, $site]));

        // Assert
        $response->assertStatus(200);
    }

    /**
     * Test user cannot access other users site installation page.
     */
    public function test_user_cannot_access_other_users_site_installation_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);
        $site = ServerSite::factory()->laravel()->create([
            'server_id' => $server->id,
            'status' => 'installing',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get(route('servers.sites.installing', [$server, $site]));

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test redirects when site installation is complete.
     */
    public function test_redirects_when_site_installation_is_complete(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->laravel()->create([
            'server_id' => $server->id,
            'status' => 'active',
            'domain' => 'example.com',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get(route('servers.sites.installing', [$server, $site]));

        // Assert
        $response->assertRedirect(route('servers.show', $server));
        $response->assertSessionHas('success');
    }

    /**
     * Test shows installation page when site installation failed.
     */
    public function test_shows_installation_page_when_site_installation_failed(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->wordpress()->create([
            'server_id' => $server->id,
            'status' => 'failed',
            'domain' => 'example.com',
            'installation_state' => collect([
                1 => 'success',
                2 => 'failed', // Failed on step 2
                3 => 'pending',
            ]),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get(route('servers.sites.installing', [$server, $site]));

        // Assert - should show installation page, not redirect
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites/installing')
            ->where('site.status', 'failed')
            ->has('site.steps')
        );
    }

    /**
     * Test returns 404 if site does not belong to server.
     */
    public function test_returns_404_if_site_does_not_belong_to_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server1 = Server::factory()->create(['user_id' => $user->id]);
        $server2 = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->laravel()->create([
            'server_id' => $server2->id,
            'status' => 'installing',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get(route('servers.sites.installing', [$server1, $site]));

        // Assert
        $response->assertStatus(404);
    }

    /**
     * Test installation page renders correct Inertia component.
     */
    public function test_installation_page_renders_correct_inertia_component(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->laravel()->create([
            'server_id' => $server->id,
            'status' => 'installing',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get(route('servers.sites.installing', [$server, $site]));

        // Assert
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites/installing')
        );
    }

    /**
     * Test installation page includes server data.
     */
    public function test_installation_page_includes_server_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Production Server',
            'public_ip' => '192.168.1.100',
        ]);
        $site = ServerSite::factory()->laravel()->create([
            'server_id' => $server->id,
            'status' => 'installing',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get(route('servers.sites.installing', [$server, $site]));

        // Assert
        $response->assertInertia(fn ($page) => $page
            ->has('server')
            ->where('server.id', $server->id)
            ->where('server.vanity_name', 'Production Server')
            ->where('server.public_ip', '192.168.1.100')
        );
    }

    /**
     * Test installation page includes site data with steps.
     */
    public function test_installation_page_includes_site_data_with_steps(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->wordpress()->create([
            'server_id' => $server->id,
            'status' => 'installing',
            'domain' => 'example.com',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get(route('servers.sites.installing', [$server, $site]));

        // Assert
        $response->assertInertia(fn ($page) => $page
            ->has('site')
            ->where('site.domain', 'example.com')
            ->where('site.status', 'installing')
            ->where('site.framework', AvailableFramework::WORDPRESS)
            ->has('site.steps') // Should have framework-specific steps
        );
    }
}
