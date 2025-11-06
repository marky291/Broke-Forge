<?php

namespace Tests\Feature\Inertia\Servers\Sites;

use App\Models\Server;
use App\Models\ServerSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test site application page renders correct Inertia component.
     */
    public function test_site_application_page_renders_correct_component(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites/{$site->id}/application");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/site-application')
        );
    }

    /**
     * Test site application page provides git_status as 'success' when git is installed.
     * This ensures the deployments menu will be visible in the layout.
     */
    public function test_site_application_page_provides_git_status_success_when_git_installed(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()
            ->withGit()
            ->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites/{$site->id}/application");

        // Assert - git_status is 'success', so deployments menu should appear
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/site-application')
            ->where('site.git_status', 'success')
            ->where('site.git_provider', 'github')
            ->where('site.git_repository', 'laravel/laravel')
            ->where('site.git_branch', 'main')
        );
    }

    /**
     * Test site application page provides git_status as null when git is not installed.
     * This ensures the deployments menu will NOT be visible in the layout.
     */
    public function test_site_application_page_provides_null_git_status_when_not_installed(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'git_status' => null,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites/{$site->id}/application");

        // Assert - git_status is null, so deployments menu should NOT appear
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/site-application')
            ->where('site.git_status', null)
        );
    }

    /**
     * Test site application page provides git_status as 'installing' during installation.
     * This ensures the deployments menu will NOT be visible in the layout.
     */
    public function test_site_application_page_provides_installing_git_status_during_installation(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()
            ->gitInstalling()
            ->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites/{$site->id}/application");

        // Assert - git_status is 'installing', so deployments menu should NOT appear
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/site-application')
            ->where('site.git_status', 'installing')
        );
    }

    /**
     * Test site application page provides git_status as 'failed' when installation fails.
     * This ensures the deployments menu will NOT be visible in the layout.
     */
    public function test_site_application_page_provides_failed_git_status_when_installation_fails(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()
            ->gitFailed()
            ->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites/{$site->id}/application");

        // Assert - git_status is 'failed', so deployments menu should NOT appear
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/site-application')
            ->where('site.git_status', 'failed')
        );
    }

    /**
     * Test site application page includes complete site structure.
     */
    public function test_site_application_page_includes_complete_site_structure(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()
            ->withGit()
            ->create([
                'server_id' => $server->id,
                'domain' => 'example.com',
                'php_version' => '8.3',
                'ssl_enabled' => true,
            ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites/{$site->id}/application");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/site-application')
            ->has('site', fn ($site) => $site
                ->has('id')
                ->has('domain')
                ->has('document_root')
                ->has('php_version')
                ->has('ssl_enabled')
                ->has('status')
                ->has('git_status')
                ->has('server')
                ->has('executionContext')
                ->etc()
            )
        );
    }

    /**
     * Test site application page includes server data for layout.
     */
    public function test_site_application_page_includes_server_data_for_layout(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Production Server',
            'public_ip' => '192.168.1.100',
        ]);
        $site = ServerSite::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites/{$site->id}/application");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/site-application')
            ->where('site.server.vanity_name', 'Production Server')
            ->where('site.server.public_ip', '192.168.1.100')
        );
    }

    /**
     * Test unauthorized user cannot access site application page.
     */
    public function test_unauthorized_user_cannot_access_site_application_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);
        $site = ServerSite::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites/{$site->id}/application");

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
        $site = ServerSite::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->get("/servers/{$server->id}/sites/{$site->id}/application");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }
}
