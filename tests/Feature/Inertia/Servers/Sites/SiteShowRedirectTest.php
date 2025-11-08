<?php

namespace Tests\Feature\Inertia\Servers\Sites;

use App\Enums\TaskStatus;
use App\Models\AvailableFramework;
use App\Models\Server;
use App\Models\ServerSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteShowRedirectTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test site show redirects to deployments when Git is installed.
     */
    public function test_site_show_redirects_to_deployments_when_git_installed(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $framework = AvailableFramework::firstOrCreate(
            ['slug' => 'laravel'],
            [
                'name' => 'Laravel',
                'env' => [
                    'supports' => true,
                    'file_path' => '.env',
                ],
                'requirements' => [
                    'database' => true,
                    'redis' => false,
                    'nodejs' => false,
                    'composer' => true,
                ],
            ]
        );
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $framework->id,
            'git_status' => TaskStatus::Success,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get(route('servers.sites.show', [
                'server' => $server->id,
                'site' => $site->id,
            ]));

        // Assert - should redirect to deployments
        $response->assertRedirect(route('servers.sites.deployments', [
            'server' => $server->id,
            'site' => $site->id,
        ]));
    }

    /**
     * Test site show redirects to settings when Git is not installed.
     */
    public function test_site_show_redirects_to_settings_when_git_not_installed(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $framework = AvailableFramework::firstOrCreate(
            ['slug' => 'static-html'],
            [
                'name' => 'Static HTML',
                'env' => [
                    'supports' => false,
                    'file_path' => null,
                ],
                'requirements' => [
                    'database' => false,
                    'redis' => false,
                    'nodejs' => false,
                    'composer' => false,
                ],
            ]
        );
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $framework->id,
            'git_status' => null,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get(route('servers.sites.show', [
                'server' => $server->id,
                'site' => $site->id,
            ]));

        // Assert - should redirect to settings
        $response->assertRedirect(route('servers.sites.settings', [
            'server' => $server->id,
            'site' => $site->id,
        ]));
    }

    /**
     * Test site show redirects to settings when Git is installing.
     */
    public function test_site_show_redirects_to_settings_when_git_installing(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $framework = AvailableFramework::firstOrCreate(
            ['slug' => 'laravel'],
            [
                'name' => 'Laravel',
                'env' => [
                    'supports' => true,
                    'file_path' => '.env',
                ],
                'requirements' => [
                    'database' => true,
                    'redis' => false,
                    'nodejs' => false,
                    'composer' => true,
                ],
            ]
        );
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $framework->id,
            'git_status' => TaskStatus::Installing,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get(route('servers.sites.show', [
                'server' => $server->id,
                'site' => $site->id,
            ]));

        // Assert - should redirect to settings (not deployments) while installing
        $response->assertRedirect(route('servers.sites.settings', [
            'server' => $server->id,
            'site' => $site->id,
        ]));
    }
}
