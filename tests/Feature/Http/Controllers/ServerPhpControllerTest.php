<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerPhp;
use App\Models\ServerPhpModule;
use App\Models\User;
use App\Packages\Services\PHP\DefaultPhpCliInstallerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerPhpControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed available PHP versions for validation
        $this->artisan('db:seed', ['--class' => 'AvailablePhpVersionSeeder']);
    }

    /**
     * Test guest cannot access server PHP page.
     */
    public function test_guest_cannot_access_server_php_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->get("/servers/{$server->id}/php");

        // Assert - guests should be redirected to login
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test authenticated user can access their server's PHP page.
     */
    public function test_user_can_access_their_server_php_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/php");

        // Assert
        $response->assertStatus(200);
    }

    /**
     * Test user cannot access other users server PHP page.
     */
    public function test_user_cannot_access_other_users_server_php_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/php");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test PHP page renders correct Inertia component.
     */
    public function test_php_page_renders_correct_inertia_component(): void
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
     * Test PHP page includes server data.
     */
    public function test_php_page_includes_server_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'My Server',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/php");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/php')
            ->where('server.id', $server->id)
            ->where('server.vanity_name', 'My Server')
        );
    }

    /**
     * Test PHP page includes installed PHP versions.
     */
    public function test_php_page_includes_installed_php_versions(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $php81 = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.1',
            'status' => TaskStatus::Active,
        ]);

        $php83 = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/php");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/php')
            ->has('server.phps', 2)
        );
    }

    /**
     * Test PHP page includes PHP modules.
     */
    public function test_php_page_includes_php_modules(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
        ]);

        ServerPhpModule::factory()->create([
            'server_php_id' => $php->id,
            'name' => 'gd',
            'is_enabled' => true,
        ]);

        ServerPhpModule::factory()->create([
            'server_php_id' => $php->id,
            'name' => 'mbstring',
            'is_enabled' => true,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/php");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/php')
            ->has('server.phps.0.modules', 2)
        );
    }

    /**
     * Test user can install new PHP version.
     */
    public function test_user_can_install_new_php_version(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php/install", [
                'version' => '8.3',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/php");
        $response->assertSessionHas('success', 'PHP 8.3 installation started');

        $this->assertDatabaseHas('server_phps', [
            'server_id' => $server->id,
            'version' => '8.3',
            'status' => TaskStatus::Pending->value,
        ]);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Packages\Services\PHP\PhpInstallerJob::class);
    }

    /**
     * Test first PHP version becomes CLI and Site default.
     */
    public function test_first_php_version_becomes_cli_and_site_default(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php/install", [
                'version' => '8.3',
            ]);

        // Assert
        $response->assertStatus(302);

        $php = $server->phps()->first();
        $this->assertTrue($php->is_cli_default);
        $this->assertTrue($php->is_site_default);
    }

    /**
     * Test second PHP version does not become default.
     */
    public function test_second_php_version_does_not_become_default(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create first PHP version
        ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.1',
            'is_cli_default' => true,
            'is_site_default' => true,
        ]);

        // Act - install second PHP version
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php/install", [
                'version' => '8.3',
            ]);

        // Assert
        $response->assertStatus(302);

        $php = $server->phps()->where('version', '8.3')->first();
        $this->assertFalse($php->is_cli_default);
        $this->assertFalse($php->is_site_default);
    }

    /**
     * Test cannot install duplicate PHP version.
     */
    public function test_cannot_install_duplicate_php_version(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php/install", [
                'version' => '8.3',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/php");
        $response->assertSessionHas('error', 'PHP 8.3 is already installed on this server');
    }

    /**
     * Test install validates required version field.
     */
    public function test_install_validates_required_version_field(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php/install", []);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['version']);
    }

    /**
     * Test install validates version is in allowed list.
     */
    public function test_install_validates_version_is_in_allowed_list(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php/install", [
                'version' => '7.4',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['version']);
    }

    /**
     * Test user can set CLI default PHP version.
     */
    public function test_user_can_set_cli_default_php_version(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $php81 = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.1',
            'is_cli_default' => true,
            'status' => TaskStatus::Active,
        ]);

        $php83 = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'is_cli_default' => false,
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/php/{$php83->id}/set-cli-default");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/php");
        $response->assertSessionHas('success', 'PHP 8.3 CLI default update started');

        $php81->refresh();
        $php83->refresh();

        // Previous default unset
        $this->assertFalse($php81->is_cli_default);

        // New PHP status set to 'updating', is_cli_default still false (job will set it)
        $this->assertEquals(TaskStatus::Updating, $php83->status);
        $this->assertFalse($php83->is_cli_default);
    }

    /**
     * Test setting CLI default dispatches DefaultPhpCliInstallerJob.
     */
    public function test_setting_cli_default_dispatches_job(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $php81 = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.1',
            'is_cli_default' => true,
        ]);

        $php83 = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'is_cli_default' => false,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/php/{$php83->id}/set-cli-default");

        // Assert
        $response->assertStatus(302);

        Queue::assertPushed(DefaultPhpCliInstallerJob::class, function ($job) use ($server, $php83) {
            return $job->server->id === $server->id
                && $job->serverPhp->id === $php83->id;
        });
    }

    /**
     * Test user can set Site default PHP version.
     */
    public function test_user_can_set_site_default_php_version(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $php81 = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.1',
            'is_site_default' => true,
        ]);

        $php83 = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'is_site_default' => false,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/php/{$php83->id}/set-site-default");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/php");
        $response->assertSessionHas('success', 'PHP 8.3 set as Site default');

        $php81->refresh();
        $php83->refresh();

        $this->assertFalse($php81->is_site_default);
        $this->assertTrue($php83->is_site_default);
    }

    /**
     * Test cannot set CLI default for PHP from different server.
     */
    public function test_cannot_set_cli_default_for_php_from_different_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server1 = Server::factory()->create(['user_id' => $user->id]);
        $server2 = Server::factory()->create(['user_id' => $user->id]);

        $php = ServerPhp::factory()->create([
            'server_id' => $server2->id,
            'version' => '8.3',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server1->id}/php/{$php->id}/set-cli-default");

        // Assert
        $response->assertStatus(404);
    }

    /**
     * Test cannot set Site default for PHP from different server.
     */
    public function test_cannot_set_site_default_for_php_from_different_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server1 = Server::factory()->create(['user_id' => $user->id]);
        $server2 = Server::factory()->create(['user_id' => $user->id]);

        $php = ServerPhp::factory()->create([
            'server_id' => $server2->id,
            'version' => '8.3',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server1->id}/php/{$php->id}/set-site-default");

        // Assert
        $response->assertStatus(404);
    }

    /**
     * Test user can remove PHP version.
     */
    public function test_user_can_remove_php_version(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'is_cli_default' => false,
            'is_site_default' => false,
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/php/{$php->id}");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/php");
        $response->assertSessionHas('success', 'PHP 8.3 removal started');

        $php->refresh();
        $this->assertEquals(TaskStatus::Pending, $php->status);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Packages\Services\PHP\PhpRemoverJob::class);
    }

    /**
     * Test cannot remove CLI default PHP version.
     */
    public function test_cannot_remove_cli_default_php_version(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'is_cli_default' => true,
            'is_site_default' => false,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/php/{$php->id}");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/php");
        $response->assertSessionHas('error', 'Cannot remove PHP 8.3 as it is the CLI default version');

        $php->refresh();
        $this->assertNotEquals(TaskStatus::Removing, $php->status);
    }

    /**
     * Test cannot remove Site default PHP version.
     */
    public function test_cannot_remove_site_default_php_version(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'is_cli_default' => false,
            'is_site_default' => true,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/php/{$php->id}");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/php");
        $response->assertSessionHas('error', 'Cannot remove PHP 8.3 as it is the Site default version');

        $php->refresh();
        $this->assertNotEquals(TaskStatus::Removing, $php->status);
    }

    /**
     * Test cannot remove PHP from different server.
     */
    public function test_cannot_remove_php_from_different_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server1 = Server::factory()->create(['user_id' => $user->id]);
        $server2 = Server::factory()->create(['user_id' => $user->id]);

        $php = ServerPhp::factory()->create([
            'server_id' => $server2->id,
            'version' => '8.3',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server1->id}/php/{$php->id}");

        // Assert
        $response->assertStatus(404);
    }

    /**
     * Test user cannot install PHP on other users server.
     */
    public function test_user_cannot_install_php_on_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php/install", [
                'version' => '8.3',
            ]);

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user cannot set CLI default on other users server.
     */
    public function test_user_cannot_set_cli_default_on_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/php/{$php->id}/set-cli-default");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user cannot set Site default on other users server.
     */
    public function test_user_cannot_set_site_default_on_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/php/{$php->id}/set-site-default");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user cannot remove PHP from other users server.
     */
    public function test_user_cannot_remove_php_from_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'is_cli_default' => false,
            'is_site_default' => false,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/php/{$php->id}");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test PHP page shows empty state when no PHP versions installed.
     */
    public function test_php_page_shows_empty_state_when_no_php_versions_installed(): void
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
            ->has('server.phps', 0)
        );
    }

    /**
     * Test PHP page includes PHP status.
     */
    public function test_php_page_includes_php_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/php");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/php')
            ->has('server.phps', 1)
            ->where('server.phps.0.status', TaskStatus::Active->value)
        );
    }

    /**
     * Test PHP page includes CLI default indicator.
     */
    public function test_php_page_includes_cli_default_indicator(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'is_cli_default' => true,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/php");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/php')
            ->has('server.phps', 1)
            ->where('server.phps.0.is_cli_default', true)
        );
    }

    /**
     * Test PHP page includes Site default indicator.
     */
    public function test_php_page_includes_site_default_indicator(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'is_site_default' => true,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/php");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/php')
            ->has('server.phps', 1)
            ->where('server.phps.0.is_site_default', true)
        );
    }

    /**
     * Test user can add PHP version with extensions via store method.
     */
    public function test_user_can_add_php_version_with_extensions(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php", [
                'version' => '8.3',
                'extensions' => ['gd', 'mbstring', 'redis'],
                'is_cli_default' => false,
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/php");
        $response->assertSessionHas('success', 'PHP installation started');

        $this->assertDatabaseHas('server_phps', [
            'server_id' => $server->id,
            'version' => '8.3',
            'status' => \App\Enums\TaskStatus::Pending->value,
        ]);

        $php = $server->phps()->first();
        $this->assertCount(3, $php->modules);
        $this->assertTrue($php->modules->pluck('name')->contains('gd'));
        $this->assertTrue($php->modules->pluck('name')->contains('mbstring'));
        $this->assertTrue($php->modules->pluck('name')->contains('redis'));
    }

    /**
     * Test user can update existing PHP version extensions via store method.
     */
    public function test_user_can_update_existing_php_version_extensions(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'status' => TaskStatus::Active,
        ]);

        // Create initial modules
        ServerPhpModule::factory()->create([
            'server_php_id' => $php->id,
            'name' => 'gd',
        ]);

        ServerPhpModule::factory()->create([
            'server_php_id' => $php->id,
            'name' => 'curl',
        ]);

        // Act - update with new extensions list
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php", [
                'version' => '8.3',
                'extensions' => ['gd', 'mbstring', 'redis'], // curl removed, mbstring and redis added
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/php");

        $php->refresh();
        $this->assertEquals(TaskStatus::Installing, $php->status);

        // Verify modules updated
        $this->assertCount(3, $php->modules);
        $this->assertTrue($php->modules->pluck('name')->contains('gd'));
        $this->assertTrue($php->modules->pluck('name')->contains('mbstring'));
        $this->assertTrue($php->modules->pluck('name')->contains('redis'));
        $this->assertFalse($php->modules->pluck('name')->contains('curl'));
    }

    /**
     * Test store method validates required version field.
     */
    public function test_store_validates_required_version_field(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php", [
                'extensions' => ['gd'],
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['version']);
    }

    /**
     * Test store method validates version is in allowed list.
     */
    public function test_store_validates_version_is_in_allowed_list(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php", [
                'version' => '5.6', // Not in allowed list
                'extensions' => ['gd'],
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['version']);
    }

    /**
     * Test store method validates extensions are in allowed list.
     */
    public function test_store_validates_extensions_are_in_allowed_list(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php", [
                'version' => '8.3',
                'extensions' => ['gd', 'invalid_extension', 'mbstring'],
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['extensions.1']);
    }

    /**
     * Test user cannot add PHP via store method to other users server.
     */
    public function test_user_cannot_add_php_via_store_to_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php", [
                'version' => '8.3',
                'extensions' => ['gd'],
            ]);

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test guest cannot add PHP via store method.
     */
    public function test_guest_cannot_add_php_via_store_method(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->post("/servers/{$server->id}/php", [
            'version' => '8.3',
            'extensions' => ['gd'],
        ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test store method can set is_cli_default when updating existing PHP.
     */
    public function test_store_can_set_cli_default_when_updating_existing_php(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'is_cli_default' => false,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php", [
                'version' => '8.3',
                'is_cli_default' => true,
                'extensions' => ['gd'],
            ]);

        // Assert
        $response->assertStatus(302);

        $php->refresh();
        $this->assertTrue($php->is_cli_default);
    }

    /**
     * Test store method preserves is_cli_default when not provided.
     */
    public function test_store_preserves_cli_default_when_not_provided(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'is_cli_default' => true,
        ]);

        // Act - update without providing is_cli_default
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php", [
                'version' => '8.3',
                'extensions' => ['mbstring'],
            ]);

        // Assert
        $response->assertStatus(302);

        $php->refresh();
        $this->assertTrue($php->is_cli_default); // Should be preserved
    }

    /**
     * Test store method creates PHP without extensions.
     */
    public function test_store_creates_php_without_extensions(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php", [
                'version' => '8.3',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/php");

        $this->assertDatabaseHas('server_phps', [
            'server_id' => $server->id,
            'version' => '8.3',
        ]);

        $php = $server->phps()->first();
        $this->assertCount(0, $php->modules);
    }

    /**
     * Test store method dispatches PHP installer job.
     */
    public function test_store_dispatches_php_installer_job(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php", [
                'version' => '8.3',
                'extensions' => ['gd'],
            ]);

        // Assert
        $response->assertStatus(302);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Packages\Services\PHP\PhpInstallerJob::class, function ($job) use ($server) {
            return $job->server->id === $server->id;
        });
    }
}
