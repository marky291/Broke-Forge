<?php

namespace Tests\Feature\Inertia\Servers\Sites;

use App\Models\AvailableFramework;
use App\Models\Server;
use App\Models\ServerSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test site layout includes Environment menu item for Laravel sites.
     */
    public function test_site_layout_includes_environment_menu_for_laravel_sites(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $framework = AvailableFramework::factory()->laravel()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $framework->id,
        ]);

        // Act - visit any site page (settings page)
        $response = $this->actingAs($user)
            ->get(route('servers.sites.settings', [
                'server' => $server->id,
                'site' => $site->id,
            ]));

        // Assert - verify page includes site framework data that supports environment
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('site.site_framework.env.supports', true)
        );
    }

    /**
     * Test Environment menu doesn't show for static HTML sites.
     */
    public function test_environment_menu_not_shown_for_static_html_sites(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $framework = AvailableFramework::factory()->staticHtml()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $framework->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get(route('servers.sites.settings', [
                'server' => $server->id,
                'site' => $site->id,
            ]));

        // Assert - verify framework does not support environment
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('site.site_framework.env.supports', false)
        );
    }

    /**
     * Test Environment menu appears for WordPress sites.
     */
    public function test_environment_menu_shown_for_wordpress_sites(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $framework = AvailableFramework::factory()->wordpress()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $framework->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get(route('servers.sites.settings', [
                'server' => $server->id,
                'site' => $site->id,
            ]));

        // Assert - verify WordPress framework supports environment
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('site.site_framework.env.supports', true)
        );
    }

    /**
     * Test site navigation includes all standard menu items.
     */
    public function test_site_navigation_includes_standard_menu_items(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $framework = AvailableFramework::factory()->laravel()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $framework->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get(route('servers.sites.settings', [
                'server' => $server->id,
                'site' => $site->id,
            ]));

        // Assert - verify site data includes framework that supports environment
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('site.id')
            ->has('site.domain')
            ->has('site.site_framework.env')
            ->where('site.site_framework.env.supports', true)
        );
    }

    /**
     * Test site settings page provides framework env data in props.
     */
    public function test_site_settings_page_provides_framework_env_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $framework = AvailableFramework::factory()->laravel()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $framework->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get(route('servers.sites.settings', [
                'server' => $server->id,
                'site' => $site->id,
            ]));

        // Assert - verify site includes framework env data for navigation
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('site.site_framework.env')
            ->where('site.site_framework.env.supports', true)
        );
    }

    /**
     * Test Deployments menu appears when git is installed.
     */
    public function test_deployments_menu_appears_when_git_installed(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $framework = AvailableFramework::factory()->laravel()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $framework->id,
            'git_status' => 'success',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get(route('servers.sites.settings', [
                'server' => $server->id,
                'site' => $site->id,
            ]));

        // Assert - verify site has git installed for deployments menu
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('site.git_status', 'success')
        );
    }

    /**
     * Test Deployments menu doesn't appear when git is not installed.
     */
    public function test_deployments_menu_not_shown_without_git(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $framework = AvailableFramework::factory()->laravel()->create();
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $framework->id,
            'git_status' => null,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get(route('servers.sites.settings', [
                'server' => $server->id,
                'site' => $site->id,
            ]));

        // Assert - verify site does not have git installed
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('site.git_status', null)
        );
    }
}
