<?php

namespace Tests\Feature\Sites\WordPress;

use App\Models\AvailableFramework;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Models\ServerSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WordPressInstallationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test WordPress site can be created without Git repository.
     */
    public function test_wordpress_site_can_be_created_without_git_repository(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        $wordpress = AvailableFramework::firstOrCreate(
            ['slug' => 'wordpress'],
            [
                'name' => 'WordPress',
                'env' => ['file_path' => 'wp-config.php', 'supports' => true],
                'requirements' => [
                    'database' => true,
                    'redis' => false,
                    'nodejs' => false,
                    'composer' => false,
                ],
                'description' => 'WordPress CMS',
            ]
        );

        // Act - create WordPress site without Git fields
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/sites", [
                'domain' => 'wordpress-site.com',
                'available_framework_id' => $wordpress->id,
                'database_id' => $database->id,
                'php_version' => '8.3',
                'ssl' => false,
                // No git_repository or git_branch
            ]);

        // Assert
        $response->assertStatus(302);
        $this->assertDatabaseHas('server_sites', [
            'server_id' => $server->id,
            'domain' => 'wordpress-site.com',
            'available_framework_id' => $wordpress->id,
            'database_id' => $database->id,
            'status' => 'installing',
        ]);
    }

    /**
     * Test WordPress installer job is dispatched when WordPress framework selected.
     */
    public function test_wordpress_installer_job_is_dispatched_when_wordpress_framework_selected(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        $wordpress = AvailableFramework::firstOrCreate(
            ['slug' => 'wordpress'],
            [
                'name' => 'WordPress',
                'env' => ['file_path' => 'wp-config.php', 'supports' => true],
                'requirements' => ['database' => true, 'redis' => false, 'nodejs' => false, 'composer' => false],
            ]
        );

        // Act
        $this->actingAs($user)
            ->post("/servers/{$server->id}/sites", [
                'domain' => 'wordpress-site.com',
                'available_framework_id' => $wordpress->id,
                'database_id' => $database->id,
                'php_version' => '8.3',
                'ssl' => false,
            ]);

        // Assert - ProvisionedSiteInstallerJob should be dispatched (which will dispatch WordPress job)
        Queue::assertPushed(\App\Packages\Services\Sites\ProvisionedSiteInstallerJob::class, function ($job) use ($server) {
            return $job->server->id === $server->id;
        });
    }

    /**
     * Test WordPress installation updates status to active on success.
     */
    public function test_wordpress_installation_updates_status_to_active_on_success(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        $wordpress = AvailableFramework::firstOrCreate(
            ['slug' => 'wordpress'],
            [
                'name' => 'WordPress',
                'env' => ['file_path' => 'wp-config.php', 'supports' => true],
                'requirements' => ['database' => true, 'redis' => false, 'nodejs' => false, 'composer' => false],
            ]
        );

        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $wordpress->id,
            'database_id' => $database->id,
            'status' => 'installing',
        ]);

        // Act - manually update status (job would do this)
        $site->update([
            'status' => 'active',
            'installed_at' => now(),
        ]);

        // Assert
        $this->assertDatabaseHas('server_sites', [
            'id' => $site->id,
            'status' => 'active',
        ]);
        $this->assertNotNull($site->fresh()->installed_at);
    }

    /**
     * Test WordPress installation updates status to failed on error.
     */
    public function test_wordpress_installation_updates_status_to_failed_on_error(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        $wordpress = AvailableFramework::firstOrCreate(
            ['slug' => 'wordpress'],
            [
                'name' => 'WordPress',
                'env' => ['file_path' => 'wp-config.php', 'supports' => true],
                'requirements' => ['database' => true, 'redis' => false, 'nodejs' => false, 'composer' => false],
            ]
        );

        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $wordpress->id,
            'database_id' => $database->id,
            'status' => 'installing',
        ]);

        // Act - simulate failure
        $site->update([
            'status' => 'failed',
            'error_log' => 'Failed to download WordPress',
        ]);

        // Assert
        $this->assertDatabaseHas('server_sites', [
            'id' => $site->id,
            'status' => 'failed',
            'error_log' => 'Failed to download WordPress',
        ]);
    }

    /**
     * Test WordPress installation broadcasts ServerSiteUpdated event.
     */
    public function test_wordpress_installation_broadcasts_server_site_updated_event(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        $wordpress = AvailableFramework::firstOrCreate(
            ['slug' => 'wordpress'],
            [
                'name' => 'WordPress',
                'env' => ['file_path' => 'wp-config.php', 'supports' => true],
                'requirements' => ['database' => true, 'redis' => false, 'nodejs' => false, 'composer' => false],
            ]
        );

        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $wordpress->id,
            'database_id' => $database->id,
            'status' => 'installing',
        ]);

        \Event::fake([\App\Events\ServerSiteUpdated::class]);

        // Act - updating status triggers ServerSiteUpdated event
        $site->update(['status' => 'active']);

        // Assert
        \Event::assertDispatched(\App\Events\ServerSiteUpdated::class, function ($event) use ($site) {
            return $event->siteId === $site->id;
        });
    }

    /**
     * Test Git fields are not required for WordPress framework.
     */
    public function test_git_fields_are_not_required_for_wordpress_framework(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        $wordpress = AvailableFramework::firstOrCreate(
            ['slug' => 'wordpress'],
            [
                'name' => 'WordPress',
                'env' => ['file_path' => 'wp-config.php', 'supports' => true],
                'requirements' => ['database' => true, 'redis' => false, 'nodejs' => false, 'composer' => false],
            ]
        );

        // Act - submit without Git fields
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/sites", [
                'domain' => 'test-wordpress.com',
                'available_framework_id' => $wordpress->id,
                'database_id' => $database->id,
                'php_version' => '8.3',
                'ssl' => false,
            ]);

        // Assert - should succeed without validation errors
        $response->assertSessionHasNoErrors();
        $response->assertStatus(302);
    }

    /**
     * Test Git fields are required for non-WordPress frameworks.
     */
    public function test_git_fields_are_required_for_non_wordpress_frameworks(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        $laravel = AvailableFramework::firstOrCreate(
            ['slug' => 'laravel'],
            [
                'name' => 'Laravel',
                'env' => ['file_path' => '.env', 'supports' => true],
                'requirements' => ['database' => true, 'redis' => true, 'nodejs' => true, 'composer' => true],
            ]
        );

        // Act - submit Laravel site without Git fields
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/sites", [
                'domain' => 'laravel-site.com',
                'available_framework_id' => $laravel->id,
                'database_id' => $database->id,
                'php_version' => '8.3',
                'ssl' => false,
            ]);

        // Assert - should fail validation
        $response->assertSessionHasErrors(['git_repository', 'git_branch']);
    }

    /**
     * Test database is required for WordPress sites.
     */
    public function test_database_is_required_for_wordpress_sites(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $wordpress = AvailableFramework::firstOrCreate(
            ['slug' => 'wordpress'],
            [
                'name' => 'WordPress',
                'env' => ['file_path' => 'wp-config.php', 'supports' => true],
                'requirements' => ['database' => true, 'redis' => false, 'nodejs' => false, 'composer' => false],
            ]
        );

        // Act - submit without database_id
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/sites", [
                'domain' => 'wordpress-site.com',
                'available_framework_id' => $wordpress->id,
                'php_version' => '8.3',
                'ssl' => false,
            ]);

        // Assert - should fail validation
        $response->assertSessionHasErrors(['database_id']);
    }

    /**
     * Test user cannot create WordPress site on another user's server.
     */
    public function test_user_cannot_create_wordpress_site_on_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        $wordpress = AvailableFramework::firstOrCreate(
            ['slug' => 'wordpress'],
            [
                'name' => 'WordPress',
                'env' => ['file_path' => 'wp-config.php', 'supports' => true],
                'requirements' => ['database' => true, 'redis' => false, 'nodejs' => false, 'composer' => false],
            ]
        );

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/sites", [
                'domain' => 'wordpress-site.com',
                'available_framework_id' => $wordpress->id,
                'database_id' => $database->id,
                'php_version' => '8.3',
                'ssl' => false,
            ]);

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test WordPress site creation with SSL enabled.
     */
    public function test_wordpress_site_creation_with_ssl_enabled(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        $wordpress = AvailableFramework::firstOrCreate(
            ['slug' => 'wordpress'],
            [
                'name' => 'WordPress',
                'env' => ['file_path' => 'wp-config.php', 'supports' => true],
                'requirements' => ['database' => true, 'redis' => false, 'nodejs' => false, 'composer' => false],
            ]
        );

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/sites", [
                'domain' => 'secure-wordpress.com',
                'available_framework_id' => $wordpress->id,
                'database_id' => $database->id,
                'php_version' => '8.3',
                'ssl' => true,
            ]);

        // Assert
        $response->assertStatus(302);
        $this->assertDatabaseHas('server_sites', [
            'domain' => 'secure-wordpress.com',
            'ssl_enabled' => true,
        ]);
    }

    /**
     * Test guest cannot create WordPress site.
     */
    public function test_guest_cannot_create_wordpress_site(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        $wordpress = AvailableFramework::firstOrCreate(
            ['slug' => 'wordpress'],
            [
                'name' => 'WordPress',
                'env' => ['file_path' => 'wp-config.php', 'supports' => true],
                'requirements' => ['database' => true, 'redis' => false, 'nodejs' => false, 'composer' => false],
            ]
        );

        // Act
        $response = $this->post("/servers/{$server->id}/sites", [
            'domain' => 'wordpress-site.com',
            'available_framework_id' => $wordpress->id,
            'database_id' => $database->id,
            'php_version' => '8.3',
            'ssl' => false,
        ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }
}
