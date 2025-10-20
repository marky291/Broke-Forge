<?php

namespace Tests\Feature\Inertia\Servers;

use App\Models\Server;
use App\Models\ServerSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test sites page renders correct Inertia component.
     */
    public function test_sites_page_renders_correct_component(): void
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
        );
    }

    /**
     * Test sites page provides server data in Inertia props.
     */
    public function test_sites_page_provides_server_data_in_props(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Production Server',
            'public_ip' => '192.168.1.100',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server')
            ->where('server.id', $server->id)
            ->where('server.vanity_name', 'Production Server')
            ->where('server.public_ip', '192.168.1.100')
        );
    }

    /**
     * Test sites page includes sites array with domain for avatar rendering.
     */
    public function test_sites_page_includes_sites_with_domain_for_avatar(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
            'php_version' => '8.3',
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 1)
            ->where('server.sites.0.domain', 'example.com')
            ->where('server.sites.0.php_version', '8.3')
            ->where('server.sites.0.status', 'active')
        );
    }

    /**
     * Test sites page displays multiple sites with different domains for avatar generation.
     */
    public function test_sites_page_displays_multiple_sites_with_different_domains(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'apple.com',
        ]);

        ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'banana.org',
        ]);

        ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'cherry.net',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert - SiteAvatar component will generate different colors/initials
        // Sites are ordered by ID DESC (newest first)
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 3)
            ->where('server.sites.0.domain', 'cherry.net')
            ->where('server.sites.1.domain', 'banana.org')
            ->where('server.sites.2.domain', 'apple.com')
        );
    }

    /**
     * Test sites page includes git repository configuration for metadata display.
     */
    public function test_sites_page_includes_git_repository_configuration(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
            'configuration' => [
                'git_repository' => [
                    'provider' => 'github',
                    'repository' => 'user/repo',
                    'branch' => 'main',
                ],
            ],
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 1)
            ->where('server.sites.0.domain', 'example.com')
            ->where('server.sites.0.configuration.git_repository.repository', 'user/repo')
            ->where('server.sites.0.configuration.git_repository.branch', 'main')
        );
    }

    /**
     * Test sites page includes SSL enabled status for metadata display.
     */
    public function test_sites_page_includes_ssl_enabled_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'secure.example.com',
            'ssl_enabled' => true,
        ]);

        ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'insecure.example.com',
            'ssl_enabled' => false,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert - Sites ordered by ID DESC (newest first)
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 2)
            ->where('server.sites.0.ssl_enabled', false)
            ->where('server.sites.1.ssl_enabled', true)
        );
    }

    /**
     * Test sites page includes deployment timestamp for metadata display.
     */
    public function test_sites_page_includes_deployment_timestamp(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $deployedSite = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'deployed.example.com',
            'last_deployed_at' => now()->subHours(2),
        ]);

        ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'not-deployed.example.com',
            'last_deployed_at' => null,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert - Sites ordered by ID DESC (newest first)
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 2)
            ->where('server.sites.0.last_deployed_at', null)
            ->has('server.sites.1.last_deployed_at')
            ->has('server.sites.1.last_deployed_at_human')
        );
    }

    /**
     * Test sites page displays all site statuses correctly.
     */
    public function test_sites_page_displays_all_site_statuses(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'pending.example.com',
            'status' => 'pending',
        ]);

        ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'installing.example.com',
            'status' => 'installing',
        ]);

        ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'active.example.com',
            'status' => 'active',
        ]);

        ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'failed.example.com',
            'status' => 'failed',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert - ensures CardBadge component receives correct status
        // Sites ordered by ID DESC (newest first)
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 4)
            ->where('server.sites.0.status', 'failed')
            ->where('server.sites.1.status', 'active')
            ->where('server.sites.2.status', 'installing')
            ->where('server.sites.3.status', 'pending')
        );
    }

    /**
     * Test sites page shows empty state when no sites exist.
     */
    public function test_sites_page_shows_empty_state(): void
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
            ->has('server.sites', 0)
        );
    }

    /**
     * Test sites page includes complete site structure for rendering.
     */
    public function test_sites_page_includes_complete_site_structure(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'test.example.com',
            'document_root' => '/var/www/html/public',
            'php_version' => '8.3',
            'ssl_enabled' => true,
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites.0', fn ($site) => $site
                ->has('id')
                ->has('domain')
                ->has('document_root')
                ->has('php_version')
                ->has('ssl_enabled')
                ->has('status')
                ->has('provisioned_at')
                ->has('last_deployed_at')
                ->has('configuration')
                ->etc()
            )
        );
    }

    /**
     * Test unauthorized user cannot access sites page.
     */
    public function test_unauthorized_user_cannot_access_sites_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test guest user redirected to login.
     */
    public function test_guest_user_redirected_to_login(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->get("/servers/{$server->id}/sites");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test sites page displays site without repository configuration.
     */
    public function test_sites_page_displays_site_without_repository(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'no-repo.example.com',
            'configuration' => null,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert - frontend should display "No repository configured"
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 1)
            ->where('server.sites.0.domain', 'no-repo.example.com')
        );
    }
}
