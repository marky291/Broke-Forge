<?php

namespace Tests\Feature\Inertia\Servers;

use App\Enums\TaskStatus;
use App\Models\AvailableFramework;
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
                ->has('installed_at')
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

    /**
     * Test sites page includes default_site_status field.
     */
    public function test_sites_page_includes_default_site_status_field(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'domain' => 'site1.example.com',
            'is_default' => true,
            'default_site_status' => TaskStatus::Installing,
        ]);

        ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'domain' => 'site2.example.com',
            'is_default' => false,
            'default_site_status' => null,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert - both sites should have default_site_status field
        // Sites are sorted by latest('id'), so site2 is index 0, site1 is index 1
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 2)
            ->where('server.sites.0.default_site_status', null)
            ->where('server.sites.1.default_site_status', 'installing')
        );
    }

    /**
     * Test sites page displays site with installing default status.
     */
    public function test_sites_page_displays_site_with_installing_default_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'domain' => 'installing-default.example.com',
            'is_default' => true,
            'default_site_status' => TaskStatus::Installing,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert - frontend receives installing status
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 1)
            ->where('server.sites.0.is_default', true)
            ->where('server.sites.0.default_site_status', 'installing')
        );
    }

    /**
     * Test sites page displays site with removing default status.
     */
    public function test_sites_page_displays_site_with_removing_default_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'domain' => 'removing-default.example.com',
            'is_default' => true,
            'default_site_status' => TaskStatus::Removing,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert - frontend receives removing status
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 1)
            ->where('server.sites.0.is_default', true)
            ->where('server.sites.0.default_site_status', 'removing')
        );
    }

    /**
     * Test sites page displays site with failed default status.
     */
    public function test_sites_page_displays_site_with_failed_default_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'domain' => 'failed-default.example.com',
            'is_default' => false,
            'default_site_status' => TaskStatus::Failed,
            'error_log' => 'Permission denied',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert - frontend receives failed status
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 1)
            ->where('server.sites.0.is_default', false)
            ->where('server.sites.0.default_site_status', 'failed')
        );
    }

    /**
     * Test sites page displays site with active default status.
     */
    public function test_sites_page_displays_site_with_active_default_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'domain' => 'active-default.example.com',
            'is_default' => true,
            'default_site_status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert - frontend receives active status
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 1)
            ->where('server.sites.0.is_default', true)
            ->where('server.sites.0.default_site_status', 'active')
        );
    }

    /**
     * Test all sites must have a framework (framework is mandatory).
     *
     * This test ensures that the database enforces framework requirement
     * and that all sites always have an associated framework.
     */
    public function test_all_sites_must_have_a_framework(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act - Try to create a site without a framework
        $this->expectException(\Illuminate\Database\QueryException::class);

        ServerSite::create([
            'server_id' => $server->id,
            'domain' => 'test.example.com',
            'document_root' => '/var/www/html/public',
            'php_version' => '8.3',
            'nginx_config_path' => '/etc/nginx/sites-available/test',
            'status' => 'active',
            'available_framework_id' => null, // This should fail
        ]);

        // Assert - Exception should be thrown
    }

    /**
     * Test sites page includes framework data when present.
     */
    public function test_sites_page_includes_framework_data_when_present(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $framework = AvailableFramework::firstOrCreate(
            ['slug' => 'laravel'],
            [
                'name' => 'Laravel',
                'env' => [
                    'file_path' => '.env',
                    'supports' => true,
                ],
                'requirements' => [
                    'database' => true,
                    'redis' => true,
                    'nodejs' => true,
                    'composer' => true,
                ],
                'description' => 'Laravel PHP framework with full-stack capabilities',
            ]
        );

        ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'laravel-site.example.com',
            'available_framework_id' => $framework->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert - framework data should be included
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 1)
            ->where('server.sites.0.domain', 'laravel-site.example.com')
            ->has('server.sites.0.site_framework', fn ($framework) => $framework
                ->has('id')
                ->where('name', 'Laravel')
                ->where('slug', 'laravel')
                ->has('env')
                ->has('requirements')
                ->etc()
            )
        );
    }

    /**
     * Test complete site structure includes site_framework field.
     *
     * This test ensures the site_framework field is always present in the response,
     * even when null, preventing frontend errors.
     */
    public function test_complete_site_structure_includes_site_framework_field(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'test.example.com',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert - site_framework field should always be present
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
                ->has('site_framework') // This field must be present
                ->etc()
            )
        );
    }

    /**
     * Test sites page displays multiple sites with different frameworks.
     */
    public function test_sites_page_displays_sites_with_different_frameworks(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $wordpress = AvailableFramework::firstOrCreate(
            ['slug' => 'wordpress'],
            [
                'name' => 'WordPress',
                'env' => [
                    'file_path' => 'wp-config.php',
                    'supports' => true,
                ],
                'requirements' => [
                    'database' => true,
                    'redis' => false,
                    'nodejs' => false,
                    'composer' => false,
                ],
                'description' => 'WordPress CMS with PHP and MySQL',
            ]
        );

        $laravel = AvailableFramework::firstOrCreate(
            ['slug' => 'laravel'],
            [
                'name' => 'Laravel',
                'env' => [
                    'file_path' => '.env',
                    'supports' => true,
                ],
                'requirements' => [
                    'database' => true,
                    'redis' => true,
                    'nodejs' => true,
                    'composer' => true,
                ],
                'description' => 'Laravel PHP framework with full-stack capabilities',
            ]
        );

        // Site with WordPress
        ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'wordpress-site.example.com',
            'available_framework_id' => $wordpress->id,
        ]);

        // Site with Laravel
        ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'laravel-site.example.com',
            'available_framework_id' => $laravel->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert - both sites should have frameworks
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 2)
            // Sites are ordered by latest ID, so laravel is index 0
            ->where('server.sites.0.site_framework.name', 'Laravel')
            ->where('server.sites.1.site_framework.name', 'WordPress')
        );
    }

    /**
     * Test sites page includes available frameworks for form selection.
     */
    public function test_sites_page_includes_available_frameworks_for_form(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert - availableFrameworks should be present for form
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.availableFrameworks')
            ->has('server.availableFrameworks.0', fn ($framework) => $framework
                ->has('id')
                ->has('name')
                ->has('slug')
                ->has('requirements')
                ->etc()
            )
        );
    }

    /**
     * Test available frameworks include all requirement flags.
     */
    public function test_available_frameworks_include_requirement_flags(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Ensure Laravel framework exists with requirements
        AvailableFramework::firstOrCreate(
            ['slug' => 'laravel'],
            [
                'name' => 'Laravel',
                'env' => ['file_path' => '.env', 'supports' => true],
                'requirements' => [
                    'database' => true,
                    'redis' => true,
                    'nodejs' => true,
                    'composer' => true,
                ],
            ]
        );

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert - framework requirements should be boolean flags
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.availableFrameworks')
            ->where('server.availableFrameworks.0.requirements.database', true)
            ->where('server.availableFrameworks.0.requirements.redis', true)
            ->where('server.availableFrameworks.0.requirements.nodejs', true)
            ->where('server.availableFrameworks.0.requirements.composer', true)
        );
    }

    /**
     * Test sites page includes databases for framework requirements.
     */
    public function test_sites_page_includes_databases_for_requirements(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert - databases should be included for framework requirements
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.databases')
        );
    }

    /**
     * Test sites page includes node versions for framework requirements.
     */
    public function test_sites_page_includes_node_versions_for_requirements(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert - node versions should be included for framework requirements
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.nodes')
        );
    }
}
