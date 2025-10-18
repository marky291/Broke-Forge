<?php

namespace Tests\Inertia\Servers;

use App\Enums\PhpStatus;
use App\Models\Server;
use App\Models\ServerPhp;
use App\Models\ServerPhpModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhpTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test PHP page renders correct Inertia component for modal.
     */
    public function test_php_page_renders_correct_component_for_modal(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/php");

        // Assert - verify Inertia component renders
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/php')
        );
    }

    /**
     * Test PHP page provides available PHP versions in Inertia props for modal.
     */
    public function test_php_page_provides_available_php_versions_for_modal(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/php");

        // Assert - verify available versions are in server props for modal dropdown
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/php')
            ->has('server.availablePhpVersions')
        );
    }

    /**
     * Test PHP page provides available extensions in Inertia props for modal.
     */
    public function test_php_page_provides_available_extensions_for_modal(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/php");

        // Assert - verify available extensions are in server props for modal checkboxes
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/php')
            ->has('server.phpExtensions')
            ->has('server.phpExtensions.bcmath')
            ->has('server.phpExtensions.curl')
            ->has('server.phpExtensions.gd')
            ->has('server.phpExtensions.mbstring')
            ->has('server.phpExtensions.redis')
            ->where('server.phpExtensions.gd', 'GD - Image processing')
            ->where('server.phpExtensions.redis', 'Redis - In-memory data structure store')
        );
    }

    /**
     * Test PHP page includes installed PHP versions with modules in Inertia props.
     */
    public function test_php_page_includes_installed_php_with_modules(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'status' => PhpStatus::Active,
            'is_cli_default' => true,
            'is_site_default' => true,
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
        $response = $this->actingAs($user)->get("/servers/{$server->id}/php");

        // Assert - verify PHP data with modules for display in page/modal
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/php')
            ->has('server.phps', 1)
            ->has('server.phps.0', fn ($phpData) => $phpData
                ->where('version', '8.3')
                ->where('status', PhpStatus::Active->value)
                ->where('is_cli_default', true)
                ->where('is_site_default', true)
                ->has('modules', 2)
                ->etc()
            )
        );
    }

    /**
     * Test PHP page shows empty state when no PHP versions installed.
     */
    public function test_php_page_shows_empty_state_in_inertia_props(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/php");

        // Assert - empty state props for showing "Add PHP Version" prompt
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/php')
            ->has('server.phps', 0)
        );
    }

    /**
     * Test PHP page provides server data in Inertia props.
     */
    public function test_php_page_provides_server_data_in_props(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Production Server',
            'public_ip' => '192.168.1.100',
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/php");

        // Assert - server data available for page header/breadcrumbs
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/php')
            ->has('server')
            ->where('server.id', $server->id)
            ->where('server.vanity_name', 'Production Server')
            ->where('server.public_ip', '192.168.1.100')
        );
    }

    /**
     * Test Inertia form submission creates PHP with extensions.
     */
    public function test_inertia_form_submission_creates_php_with_extensions(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act - simulate Inertia modal form POST
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php", [
                'version' => '8.3',
                'extensions' => ['gd', 'mbstring', 'redis'],
                'is_cli_default' => false,
            ]);

        // Assert - redirects with success message
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/php");
        $response->assertSessionHas('success', 'PHP installation started');

        // Verify database
        $this->assertDatabaseHas('server_phps', [
            'server_id' => $server->id,
            'version' => '8.3',
            'status' => PhpStatus::Pending->value,
        ]);

        $php = $server->phps()->first();
        $this->assertCount(3, $php->modules);
    }

    /**
     * Test Inertia form validation errors are returned to modal.
     */
    public function test_inertia_form_validation_errors_returned_to_modal(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act - submit invalid data via modal form
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php", [
                'version' => '', // Missing required field
                'extensions' => ['invalid_extension'],
            ]);

        // Assert - validation errors in session for modal display
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['version', 'extensions.0']);
    }

    /**
     * Test PHP page includes multiple PHP versions in Inertia props.
     */
    public function test_php_page_includes_multiple_php_versions(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.1',
            'is_cli_default' => true,
        ]);

        ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'is_site_default' => true,
        ]);

        ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.4',
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/php");

        // Assert - all PHP versions in props for page display
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/php')
            ->has('server.phps', 3)
            ->where('server.phps.0.version', '8.1')
            ->where('server.phps.1.version', '8.3')
            ->where('server.phps.2.version', '8.4')
        );
    }

    /**
     * Test PHP page includes PHP status information in Inertia props.
     */
    public function test_php_page_includes_php_status_information(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'status' => PhpStatus::Pending,
        ]);

        ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.4',
            'status' => PhpStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/php");

        // Assert - status information for UI display
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/php')
            ->has('server.phps', 2)
            ->where('server.phps.0.status', PhpStatus::Pending->value)
            ->where('server.phps.1.status', PhpStatus::Active->value)
        );
    }

    /**
     * Test PHP page includes modules for each PHP version in Inertia props.
     */
    public function test_php_page_includes_modules_for_each_php_version(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $php81 = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.1',
        ]);

        ServerPhpModule::factory()->create([
            'server_php_id' => $php81->id,
            'name' => 'gd',
        ]);

        ServerPhpModule::factory()->create([
            'server_php_id' => $php81->id,
            'name' => 'curl',
        ]);

        $php83 = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
        ]);

        ServerPhpModule::factory()->create([
            'server_php_id' => $php83->id,
            'name' => 'mbstring',
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/php");

        // Assert - modules data for each PHP version
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/php')
            ->has('server.phps', 2)
            ->has('server.phps.0.modules', 2)
            ->has('server.phps.1.modules', 1)
        );
    }

    /**
     * Test Inertia receives user authentication state.
     */
    public function test_inertia_receives_user_authentication_state(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/php");

        // Assert - user data shared with Inertia
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('auth.user')
            ->where('auth.user.name', 'Jane Doe')
            ->where('auth.user.email', 'jane@example.com')
        );
    }

    /**
     * Test PHP page returns proper Inertia structure for modal functionality.
     */
    public function test_php_page_returns_proper_inertia_structure_for_modal(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/php");

        // Assert - verify proper structure for modal to function
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/php')
            ->has('server')
            ->has('server.phps')
            ->has('server.availablePhpVersions')
            ->has('server.phpExtensions')
        );
    }

    /**
     * Test PHP page includes default indicators in Inertia props.
     */
    public function test_php_page_includes_default_indicators_in_props(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'is_cli_default' => true,
            'is_site_default' => true,
        ]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/php");

        // Assert - default indicators for UI badges/labels
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/php')
            ->has('server.phps', 1)
            ->where('server.phps.0.is_cli_default', true)
            ->where('server.phps.0.is_site_default', true)
        );
    }

    /**
     * Test successful Inertia form submission provides flash message.
     */
    public function test_successful_inertia_form_submission_provides_flash_message(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act - submit modal form
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php", [
                'version' => '8.3',
                'extensions' => ['gd'],
            ]);

        // Assert - success flash message for toast notification
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'PHP installation started');
    }

    /**
     * Test Inertia form handles updating existing PHP version.
     */
    public function test_inertia_form_handles_updating_existing_php(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'status' => PhpStatus::Active,
        ]);

        // Act - resubmit same version with different extensions
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php", [
                'version' => '8.3',
                'extensions' => ['mbstring', 'redis'],
            ]);

        // Assert - updates existing PHP
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/php");

        $php->refresh();
        $this->assertEquals(PhpStatus::Installing, $php->status);
    }

    /**
     * Test Inertia modal can install PHP version via install endpoint.
     */
    public function test_inertia_modal_can_install_php_version_via_install_endpoint(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act - simulate modal form submission to /install endpoint
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php/install", [
                'version' => '8.3',
            ]);

        // Assert - redirects with success message
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/php");
        $response->assertSessionHas('success', 'PHP 8.3 installation started');

        // Verify database
        $this->assertDatabaseHas('server_phps', [
            'server_id' => $server->id,
            'version' => '8.3',
            'status' => PhpStatus::Pending->value,
        ]);
    }

    /**
     * Test Inertia modal validates all available PHP versions correctly.
     */
    public function test_inertia_modal_validates_all_available_php_versions(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $availableVersions = ['8.1', '8.2', '8.3', '8.4'];

        // Act & Assert - test each available version
        foreach ($availableVersions as $version) {
            $response = $this->actingAs($user)
                ->post("/servers/{$server->id}/php/install", [
                    'version' => $version,
                ]);

            $response->assertStatus(302);
            $response->assertSessionHasNoErrors();
            $response->assertSessionHas('success');
        }
    }

    /**
     * Test Inertia modal rejects invalid PHP version.
     */
    public function test_inertia_modal_rejects_invalid_php_version(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act - submit invalid version via modal
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php/install", [
                'version' => '7.4', // Not in available versions
            ]);

        // Assert - validation error returned to modal
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['version']);
    }

    /**
     * Test Inertia modal requires version field.
     */
    public function test_inertia_modal_requires_version_field(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act - submit without version
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php/install", []);

        // Assert - validation error for missing version
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['version']);
    }

    /**
     * Test Inertia modal handles duplicate PHP version gracefully.
     */
    public function test_inertia_modal_handles_duplicate_php_version(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
        ]);

        // Act - try to install duplicate via modal
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php/install", [
                'version' => '8.3',
            ]);

        // Assert - error message returned to modal
        $response->assertStatus(302);
        $response->assertSessionHas('error', 'PHP 8.3 is already installed on this server');
    }

    /**
     * Test Inertia modal sets first PHP as CLI and Site default.
     */
    public function test_inertia_modal_sets_first_php_as_defaults(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act - install first PHP via modal
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php/install", [
                'version' => '8.4',
            ]);

        // Assert - first PHP is both defaults
        $response->assertStatus(302);

        $php = $server->phps()->first();
        $this->assertTrue($php->is_cli_default);
        $this->assertTrue($php->is_site_default);
    }

    /**
     * Test Inertia modal does not set second PHP as default.
     */
    public function test_inertia_modal_does_not_set_second_php_as_default(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create first PHP
        ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.1',
            'is_cli_default' => true,
            'is_site_default' => true,
        ]);

        // Act - install second PHP via modal
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php/install", [
                'version' => '8.4',
            ]);

        // Assert - second PHP is not default
        $response->assertStatus(302);

        $php = $server->phps()->where('version', '8.4')->first();
        $this->assertFalse($php->is_cli_default);
        $this->assertFalse($php->is_site_default);
    }
}
