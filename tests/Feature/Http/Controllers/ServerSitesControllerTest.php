<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerSitesControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test guest cannot access server sites page.
     */
    public function test_guest_cannot_access_server_sites_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->get("/servers/{$server->id}/sites");

        // Assert - guests should be redirected to login
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test authenticated user can access their server's sites page.
     */
    public function test_user_can_access_their_server_sites_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert
        $response->assertStatus(200);
    }

    /**
     * Test user cannot access other users server sites page.
     */
    public function test_user_cannot_access_other_users_server_sites_page(): void
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
     * Test sites page renders correct Inertia component.
     */
    public function test_sites_page_renders_correct_inertia_component(): void
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
     * Test sites page includes server data.
     */
    public function test_sites_page_includes_server_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Production Server',
            'public_ip' => '192.168.1.100',
            'ssh_port' => 22,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->where('server.id', $server->id)
            ->where('server.vanity_name', 'Production Server')
            ->where('server.public_ip', '192.168.1.100')
            ->where('server.ssh_port', 22)
        );
    }

    /**
     * Test sites page includes sites data.
     */
    public function test_sites_page_includes_sites_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
            'php_version' => '8.3',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 1)
            ->has('server.sites.0', fn ($siteData) => $siteData
                ->where('id', $site->id)
                ->where('domain', 'example.com')
                ->where('php_version', '8.3')
                ->where('status', 'active')
                ->has('document_root')
                ->has('ssl_enabled')
                ->etc()
            )
        );
    }

    /**
     * Test sites page shows multiple sites.
     */
    public function test_sites_page_shows_multiple_sites(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'domain' => 'site1.com',
            'created_at' => now()->subDays(3),
        ]);

        ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'domain' => 'site2.com',
            'created_at' => now()->subDays(2),
        ]);

        ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'domain' => 'site3.com',
            'created_at' => now()->subDays(1),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert - latest() orders by created_at DESC, so site3 should be first
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 3)
            ->where('server.sites.0.domain', 'site3.com')
            ->where('server.sites.1.domain', 'site2.com')
            ->where('server.sites.2.domain', 'site1.com')
        );
    }

    /**
     * Test sites page shows empty state when no sites exist.
     */
    public function test_sites_page_shows_empty_state_when_no_sites_exist(): void
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
     * Test sites page includes site status information.
     */
    public function test_sites_page_includes_site_status_information(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $activeSite = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'status' => 'active',
            'created_at' => now()->subDays(2),
        ]);

        $provisioningSite = ServerSite::factory()->create([
            'server_id' => $server->id,
            'status' => 'provisioning',
            'created_at' => now()->subDay(),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert - latest() orders by created_at DESC
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 2)
            ->where('server.sites.0.status', 'provisioning')
            ->where('server.sites.1.status', 'active')
        );
    }

    /**
     * Test sites page includes git status for sites.
     */
    public function test_sites_page_includes_git_status_for_sites(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $siteWithGit = ServerSite::factory()->withGit()->create([
            'server_id' => $server->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 1)
            ->where('server.sites.0.git_status', TaskStatus::Success->value)
            ->has('server.sites.0.configuration.git_repository')
        );
    }

    /**
     * Test sites page includes SSL information.
     */
    public function test_sites_page_includes_ssl_information(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $siteWithSSL = ServerSite::factory()->withSSL()->create([
            'server_id' => $server->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 1)
            ->where('server.sites.0.ssl_enabled', true)
        );
    }

    /**
     * Test sites page includes provisioned timestamp.
     */
    public function test_sites_page_includes_provisioned_timestamp(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $site = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'provisioned_at' => now()->subDays(5),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 1)
            ->has('server.sites.0.provisioned_at')
            ->has('server.sites.0.provisioned_at_human')
        );
    }

    /**
     * Test sites page includes last deployment information.
     */
    public function test_sites_page_includes_last_deployment_information(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $site = ServerSite::factory()->withGit()->create([
            'server_id' => $server->id,
            'last_deployed_at' => now()->subHours(2),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 1)
            ->has('server.sites.0.last_deployed_at')
            ->has('server.sites.0.last_deployed_at_human')
        );
    }

    /**
     * Test sites page includes PHP version for each site.
     */
    public function test_sites_page_includes_php_version_for_each_site(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerSite::factory()->create([
            'server_id' => $server->id,
            'php_version' => '8.1',
            'created_at' => now()->subDays(2),
        ]);

        ServerSite::factory()->create([
            'server_id' => $server->id,
            'php_version' => '8.3',
            'created_at' => now()->subDay(),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert - latest() orders by created_at DESC
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 2)
            ->where('server.sites.0.php_version', '8.3')
            ->where('server.sites.1.php_version', '8.1')
        );
    }

    /**
     * Test sites page includes document root for each site.
     */
    public function test_sites_page_includes_document_root_for_each_site(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
            'document_root' => '/home/brokeforge/example.com/public',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 1)
            ->where('server.sites.0.document_root', '/home/brokeforge/example.com/public')
        );
    }

    /**
     * Test sites are ordered by most recent first.
     */
    public function test_sites_are_ordered_by_most_recent_first(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create sites with different creation times
        $oldSite = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'old-site.com',
            'created_at' => now()->subDays(10),
        ]);

        $newSite = ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'new-site.com',
            'created_at' => now()->subDay(),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert - newest should be first
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 2)
            ->where('server.sites.0.domain', 'new-site.com')
            ->where('server.sites.1.domain', 'old-site.com')
        );
    }

    /**
     * Test sites page includes error log path if available.
     */
    public function test_sites_page_includes_error_log_path(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'error_log' => '/var/log/nginx/example.com-error.log',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 1)
            ->has('server.sites.0.error_log')
        );
    }

    /**
     * Test sites page includes site configuration data.
     */
    public function test_sites_page_includes_site_configuration_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $site = ServerSite::factory()->withGit()->create([
            'server_id' => $server->id,
            'configuration' => [
                'application_type' => 'application',
                'git_repository' => [
                    'provider' => 'github',
                    'repository' => 'laravel/laravel',
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
            ->has('server.sites.0.configuration')
            ->where('server.sites.0.configuration.git_repository.provider', 'github')
            ->where('server.sites.0.configuration.git_repository.repository', 'laravel/laravel')
            ->where('server.sites.0.configuration.git_repository.branch', 'main')
        );
    }

    /**
     * Test guest cannot set default site.
     */
    public function test_guest_cannot_set_default_site(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => false,
        ]);

        // Act
        $response = $this->patch("/servers/{$server->id}/sites/{$site->id}/set-default");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test user can set active site as default.
     */
    public function test_user_can_set_active_site_as_default(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => false,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/sites/{$site->id}/set-default");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/sites");
        $response->assertSessionHas('success');

        // Verify database
        $this->assertDatabaseHas('server_sites', [
            'id' => $site->id,
            'is_default' => 1,
        ]);
    }

    /**
     * Test user cannot set default site for other users server.
     */
    public function test_user_cannot_set_default_site_for_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);
        $site = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => false,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/sites/{$site->id}/set-default");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test cannot set non-active site as default.
     */
    public function test_cannot_set_non_active_site_as_default(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'status' => 'provisioning',
            'is_default' => false,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/sites/{$site->id}/set-default");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('error', 'Only active sites can be set as default.');

        // Verify database unchanged
        $this->assertDatabaseHas('server_sites', [
            'id' => $site->id,
            'is_default' => false,
        ]);
    }

    /**
     * Test setting new default unsets previous default site.
     */
    public function test_setting_new_default_unsets_previous_default(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $oldDefaultSite = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => true,
        ]);

        $newDefaultSite = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => false,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/sites/{$newDefaultSite->id}/set-default");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/sites");

        // Verify new site is default
        $this->assertDatabaseHas('server_sites', [
            'id' => $newDefaultSite->id,
            'is_default' => 1,
        ]);

        // Verify old site is no longer default
        $this->assertDatabaseHas('server_sites', [
            'id' => $oldDefaultSite->id,
            'is_default' => 0,
        ]);
    }

    /**
     * Test setting default site returns success message.
     */
    public function test_setting_default_site_returns_success_message(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
            'is_default' => false,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/sites/{$site->id}/set-default");

        // Assert
        $response->assertSessionHas('success');
        $successMessage = session('success');
        $this->assertStringContainsString('example.com', $successMessage);
        $this->assertStringContainsString('default', $successMessage);
    }

    /**
     * Test guest cannot unset default site.
     */
    public function test_guest_cannot_unset_default_site(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => true,
        ]);

        // Act
        $response = $this->patch("/servers/{$server->id}/sites/{$site->id}/unset-default");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test user can unset default site.
     */
    public function test_user_can_unset_default_site(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => true,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/sites/{$site->id}/unset-default");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/sites");
        $response->assertSessionHas('success');

        // Verify database - is_default remains true until job completes
        $this->assertDatabaseHas('server_sites', [
            'id' => $site->id,
            'is_default' => 1,
            'default_site_status' => TaskStatus::Removing->value,
        ]);
    }

    /**
     * Test user cannot unset default site for other users server.
     */
    public function test_user_cannot_unset_default_site_for_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);
        $site = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => true,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/sites/{$site->id}/unset-default");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test cannot unset site that is not default.
     */
    public function test_cannot_unset_site_that_is_not_default(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => false,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/sites/{$site->id}/unset-default");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('error', 'This site is not currently set as default.');

        // Verify database unchanged
        $this->assertDatabaseHas('server_sites', [
            'id' => $site->id,
            'is_default' => false,
        ]);
    }

    /**
     * Test unsetting default site returns success message.
     */
    public function test_unsetting_default_site_returns_success_message(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => true,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/sites/{$site->id}/unset-default");

        // Assert
        $response->assertSessionHas('success');
        $successMessage = session('success');
        $this->assertStringContainsString('default', $successMessage);
    }

    /**
     * Test sites page includes is_default field.
     */
    public function test_sites_page_includes_is_default_field(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $defaultSite = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'domain' => 'default.com',
            'is_default' => true,
            'created_at' => now()->subDay(),
        ]);

        $normalSite = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'domain' => 'normal.com',
            'is_default' => false,
            'created_at' => now(),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.sites', 2)
            ->where('server.sites.0.is_default', false) // normalSite (newest first)
            ->where('server.sites.1.is_default', true) // defaultSite
        );
    }

    /**
     * Test only one site can be default at a time.
     */
    public function test_only_one_site_can_be_default_at_a_time(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $site1 = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => true,
        ]);

        $site2 = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => false,
        ]);

        $site3 = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => false,
        ]);

        // Act - set site2 as default
        $this->actingAs($user)
            ->patch("/servers/{$server->id}/sites/{$site2->id}/set-default");

        // Assert - only site2 should be default
        $this->assertDatabaseHas('server_sites', [
            'id' => $site1->id,
            'is_default' => 0,
        ]);

        $this->assertDatabaseHas('server_sites', [
            'id' => $site2->id,
            'is_default' => 1,
        ]);

        $this->assertDatabaseHas('server_sites', [
            'id' => $site3->id,
            'is_default' => 0,
        ]);

        // Verify only one default site exists
        $defaultSitesCount = ServerSite::where('server_id', $server->id)
            ->where('is_default', 1)
            ->count();

        $this->assertEquals(1, $defaultSitesCount);
    }

    /**
     * Test setting default site sets default_site_status to installing.
     */
    public function test_setting_default_site_sets_default_site_status_to_installing(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => false,
            'default_site_status' => null,
        ]);

        // Act
        $this->actingAs($user)
            ->patch("/servers/{$server->id}/sites/{$site->id}/set-default");

        // Assert - default_site_status should be 'installing'
        $this->assertDatabaseHas('server_sites', [
            'id' => $site->id,
            'is_default' => 1,
            'default_site_status' => TaskStatus::Installing->value,
        ]);
    }

    /**
     * Test unsetting default site sets default_site_status to removing.
     */
    public function test_unsetting_default_site_sets_default_site_status_to_removing(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => true,
            'default_site_status' => null,
        ]);

        // Act
        $this->actingAs($user)
            ->patch("/servers/{$server->id}/sites/{$site->id}/unset-default");

        // Assert - default_site_status should be 'removing'
        $this->assertDatabaseHas('server_sites', [
            'id' => $site->id,
            'is_default' => 1, // Still true until job completes
            'default_site_status' => TaskStatus::Removing->value,
        ]);
    }

    /**
     * Test setting default site dispatches SiteSetDefaultJob.
     */
    public function test_setting_default_site_dispatches_job(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => false,
        ]);

        // Act
        $this->actingAs($user)
            ->patch("/servers/{$server->id}/sites/{$site->id}/set-default");

        // Assert - job should be dispatched
        Queue::assertPushed(\App\Packages\Services\Sites\SiteSetDefaultJob::class, function ($job) use ($site) {
            return $job->site->id === $site->id;
        });
    }

    /**
     * Test unsetting default site dispatches SiteUnsetDefaultJob.
     */
    public function test_unsetting_default_site_dispatches_job(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => true,
        ]);

        // Act
        $this->actingAs($user)
            ->patch("/servers/{$server->id}/sites/{$site->id}/unset-default");

        // Assert - job should be dispatched
        Queue::assertPushed(\App\Packages\Services\Sites\SiteUnsetDefaultJob::class, function ($job) use ($site) {
            return $job->site->id === $site->id;
        });
    }

    /**
     * Test setting default site clears previous default site's default_site_status.
     */
    public function test_setting_default_site_clears_previous_default_site_status(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $previousDefault = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => true,
            'default_site_status' => TaskStatus::Active->value,
        ]);

        $newSite = ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'is_default' => false,
            'default_site_status' => null,
        ]);

        // Act
        $this->actingAs($user)
            ->patch("/servers/{$server->id}/sites/{$newSite->id}/set-default");

        // Assert - previous default should have is_default=false and default_site_status=null
        $this->assertDatabaseHas('server_sites', [
            'id' => $previousDefault->id,
            'is_default' => 0,
            'default_site_status' => null,
        ]);

        // New site should have is_default=true and default_site_status=installing
        $this->assertDatabaseHas('server_sites', [
            'id' => $newSite->id,
            'is_default' => 1,
            'default_site_status' => TaskStatus::Installing->value,
        ]);
    }

    /**
     * Test sites page includes available frameworks in server data.
     */
    public function test_sites_page_includes_available_frameworks(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert - availableFrameworks should be in server data
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.availableFrameworks')
        );
    }

    /**
     * Test available frameworks include requirements structure.
     */
    public function test_available_frameworks_include_requirements(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites");

        // Assert - frameworks should include requirements
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/sites')
            ->has('server.availableFrameworks.0', fn ($framework) => $framework
                ->has('id')
                ->has('name')
                ->has('slug')
                ->has('requirements', fn ($req) => $req
                    ->has('database')
                    ->has('redis')
                    ->has('nodejs')
                    ->has('composer')
                    ->etc()
                )
                ->etc()
            )
        );
    }
}
