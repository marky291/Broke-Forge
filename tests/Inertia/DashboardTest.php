<?php

namespace Tests\Inertia;

use App\Models\Server;
use App\Models\ServerSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test dashboard renders correct Inertia component.
     */
    public function test_dashboard_renders_correct_component(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - verify Inertia component
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
        );
    }

    /**
     * Test dashboard displays latest 5 servers in Inertia props.
     */
    public function test_dashboard_displays_latest_five_servers(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);

        // Create 7 servers to ensure only 5 are shown
        Server::factory()->count(7)->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - verify Inertia props contain the correct number of servers
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('dashboard.servers', 5)
        );
    }

    /**
     * Test dashboard provides server data in Inertia props.
     */
    public function test_dashboard_provides_server_data_in_props(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Production Server',
            'public_ip' => '192.168.1.100',
            'ssh_port' => 22,
        ]);

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - verify server data is in Inertia props
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('dashboard.servers', 1)
            ->where('dashboard.servers.0.name', 'Production Server')
            ->where('dashboard.servers.0.public_ip', '192.168.1.100')
            ->where('dashboard.servers.0.ssh_port', 22)
        );
    }

    /**
     * Test dashboard displays latest 5 sites in Inertia props.
     */
    public function test_dashboard_displays_latest_five_sites(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create 7 sites to ensure only 5 are shown
        ServerSite::factory()->count(7)->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - should see latest 5 sites in Inertia props
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('dashboard.sites', 5)
        );
    }

    /**
     * Test dashboard provides site data in Inertia props.
     */
    public function test_dashboard_provides_site_data_in_props(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'My Server',
        ]);
        ServerSite::factory()->active()->create([
            'server_id' => $server->id,
            'domain' => 'example.com',
            'php_version' => '8.3',
        ]);

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - verify site data is in Inertia props
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('dashboard.sites', 1)
            ->where('dashboard.sites.0.domain', 'example.com')
            ->where('dashboard.sites.0.php_version', '8.3')
            ->where('dashboard.sites.0.server_name', 'My Server')
        );
    }

    /**
     * Test dashboard provides recent activities in Inertia props.
     */
    public function test_dashboard_provides_recent_activities_in_props(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);

        // Create 12 activities to ensure only 10 are shown
        for ($i = 0; $i < 12; $i++) {
            activity()
                ->causedBy($user)
                ->event('auth.login')
                ->withProperties(['email' => $user->email])
                ->log('User logged in');
        }

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - should see latest 10 activities in Inertia props
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('dashboard.activities', 10)
        );
    }

    /**
     * Test dashboard provides activity information in Inertia props.
     */
    public function test_dashboard_provides_activity_data_in_props(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);

        activity()
            ->causedBy($user)
            ->event('server.created')
            ->withProperties([
                'name' => 'Test Server',
                'public_ip' => '10.0.0.1',
            ])
            ->log('Server was created');

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - verify activity data is in Inertia props
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('dashboard.activities', 1)
            ->where('dashboard.activities.0.type', 'server.created')
            ->where('dashboard.activities.0.label', 'Server created')
            ->where('dashboard.activities.0.description', 'Server was created')
            ->where('dashboard.activities.0.detail', 'Test Server — 10.0.0.1')
        );
    }

    /**
     * Test dashboard shows empty state in Inertia props when no data exists.
     */
    public function test_dashboard_shows_empty_state(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - empty arrays for empty state
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('dashboard.servers', 0)
            ->has('dashboard.sites', 0)
            ->has('dashboard.activities', 0)
        );
    }

    /**
     * Test dashboard eager loads server relationships in Inertia props.
     */
    public function test_dashboard_eager_loads_server_relationships(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create related data
        ServerSite::factory()->count(2)->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - should have sites count in Inertia props
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('dashboard.servers.0.sites_count', 2)
        );
    }

    /**
     * Test dashboard returns proper Inertia structure.
     */
    public function test_dashboard_returns_proper_inertia_structure(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - verify proper Inertia structure
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('dashboard')
            ->has('dashboard.servers')
            ->has('dashboard.sites')
            ->has('dashboard.activities')
        );
    }

    /**
     * Test dashboard provides servers ordered by latest in Inertia props.
     */
    public function test_dashboard_displays_servers_ordered_by_latest(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);

        // Create servers with specific names to identify order
        Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Old Server',
            'created_at' => now()->subDays(5),
        ]);

        Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'New Server',
            'created_at' => now(),
        ]);

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - newest server should be first in Inertia props
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('dashboard.servers.0.name', 'New Server')
            ->where('dashboard.servers.1.name', 'Old Server')
        );
    }

    /**
     * Test dashboard provides sites ordered by latest in Inertia props.
     */
    public function test_dashboard_displays_sites_ordered_by_latest(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create sites with specific domains to identify order
        ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'old.example.com',
            'created_at' => now()->subDays(5),
        ]);

        ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'new.example.com',
            'created_at' => now(),
        ]);

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - newest site should be first in Inertia props
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('dashboard.sites.0.domain', 'new.example.com')
            ->where('dashboard.sites.1.domain', 'old.example.com')
        );
    }

    /**
     * Test Inertia receives user authentication state.
     */
    public function test_inertia_receives_user_authentication_state(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'email_verified_at' => now(),
        ]);

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - user data is shared with Inertia
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('auth.user')
            ->where('auth.user.name', 'John Doe')
            ->where('auth.user.email', 'john@example.com')
        );
    }

    /**
     * Test Inertia form submission creates server.
     */
    public function test_inertia_form_submission_creates_server(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);

        // Act - simulate Inertia form POST
        $response = $this->actingAs($user)
            ->post('/servers', [
                'vanity_name' => 'Inertia Test Server',
                'public_ip' => '203.0.113.10',
                'ssh_port' => 22,
                'php_version' => '8.3',
                'provider' => 'custom',
            ]);

        // Assert - redirects with success message
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Server created');

        // Verify database
        $this->assertDatabaseHas('servers', [
            'vanity_name' => 'Inertia Test Server',
            'user_id' => $user->id,
        ]);
    }

    /**
     * Test Inertia form validation errors are returned.
     */
    public function test_inertia_form_validation_errors_returned(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);

        // Act - submit invalid data
        $response = $this->actingAs($user)
            ->post('/servers', [
                'vanity_name' => '',
                'public_ip' => 'invalid-ip',
                'ssh_port' => 'not-a-number',
            ]);

        // Assert - validation errors in session
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['vanity_name', 'public_ip', 'ssh_port', 'php_version']);
    }

    /**
     * Test successful server creation redirects with provisioning data in session.
     */
    public function test_successful_server_creation_provides_provisioning_data(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);

        // Act - create server through Inertia form
        $response = $this->actingAs($user)
            ->post('/servers', [
                'vanity_name' => 'New Server',
                'public_ip' => '192.168.50.50',
                'ssh_port' => 22,
                'php_version' => '8.4',
                'provider' => 'digitalocean',
                'add_ssh_key_to_github' => true,
            ]);

        // Assert - session contains provisioning data
        $response->assertStatus(302);
        $response->assertSessionHas('provision');

        $provision = session('provision');
        $this->assertArrayHasKey('command', $provision);
        $this->assertArrayHasKey('root_password', $provision);
    }

    /**
     * Test Inertia form handles server limit correctly.
     */
    public function test_inertia_form_handles_server_limit_correctly(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);

        // Create servers up to user's limit
        $limit = $user->getServerLimit();
        Server::factory()->count($limit)->create([
            'user_id' => $user->id,
            'counted_in_subscription' => true,
        ]);

        // Act - attempt to create one more server
        $response = $this->actingAs($user)
            ->post('/servers', [
                'vanity_name' => 'Over Limit Server',
                'public_ip' => '192.168.100.100',
                'ssh_port' => 22,
                'php_version' => '8.4',
            ]);

        // Assert - redirected back with error message
        $response->assertStatus(302);
        $response->assertSessionHas('error');
    }

    /**
     * Test Inertia form with all optional fields.
     */
    public function test_inertia_form_with_all_optional_fields(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);

        // Act - submit form with all optional fields
        $response = $this->actingAs($user)
            ->post('/servers', [
                'vanity_name' => 'Full Featured Server',
                'public_ip' => '10.0.0.50',
                'private_ip' => '10.0.0.51',
                'ssh_port' => 2222,
                'php_version' => '8.3',
                'provider' => 'aws',
                'add_ssh_key_to_github' => true,
            ]);

        // Assert - all fields saved correctly
        $response->assertStatus(302);
        $this->assertDatabaseHas('servers', [
            'vanity_name' => 'Full Featured Server',
            'public_ip' => '10.0.0.50',
            'private_ip' => '10.0.0.51',
            'ssh_port' => 2222,
            'provider' => 'aws',
        ]);
    }

    /**
     * Test Inertia validation for unique IP address constraint.
     */
    public function test_inertia_validation_for_unique_ip_address(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);

        // Create existing server
        Server::factory()->create([
            'user_id' => $user->id,
            'public_ip' => '192.168.1.100',
        ]);

        // Act - attempt to create server with duplicate IP
        $response = $this->actingAs($user)
            ->post('/servers', [
                'vanity_name' => 'Duplicate IP Server',
                'public_ip' => '192.168.1.100',
                'ssh_port' => 22,
                'php_version' => '8.4',
            ]);

        // Assert - validation error for duplicate IP
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['public_ip']);
    }

    /**
     * Test Inertia page includes dashboard data structure for servers.
     */
    public function test_inertia_dashboard_data_structure_for_servers(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Test Server',
            'public_ip' => '10.0.0.1',
            'ssh_port' => 22,
        ]);

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - verify dashboard data structure for server card display
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('dashboard.servers.0', fn ($server) => $server
                ->has('id')
                ->has('name')
                ->has('public_ip')
                ->has('ssh_port')
                ->has('provider')
                ->has('connection')
                ->has('provision_status')
                ->has('sites_count')
                ->etc()
            )
        );
    }

    /**
     * Test Inertia props only include user's own servers.
     */
    public function test_inertia_props_only_include_users_own_servers(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $otherUser = User::factory()->create(['email_verified_at' => now()]);

        // Create servers for both users
        $userServer = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'User Server',
        ]);

        $otherUserServer = Server::factory()->create([
            'user_id' => $otherUser->id,
            'vanity_name' => 'Other User Server',
        ]);

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - Inertia props should only contain user's server
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('dashboard.servers', 1)
            ->where('dashboard.servers.0.name', 'User Server')
            ->where('dashboard.servers.0.id', $userServer->id)
        );
    }

    /**
     * Test Inertia props only include sites from user's servers.
     */
    public function test_inertia_props_only_include_users_own_sites(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $otherUser = User::factory()->create(['email_verified_at' => now()]);

        $userServer = Server::factory()->create(['user_id' => $user->id]);
        $otherUserServer = Server::factory()->create(['user_id' => $otherUser->id]);

        // Create sites for both users
        $userSite = ServerSite::factory()->create([
            'server_id' => $userServer->id,
            'domain' => 'mysite.com',
        ]);

        $otherUserSite = ServerSite::factory()->create([
            'server_id' => $otherUserServer->id,
            'domain' => 'othersite.com',
        ]);

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - Inertia props should only contain user's site
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('dashboard.sites', 1)
            ->where('dashboard.sites.0.domain', 'mysite.com')
            ->where('dashboard.sites.0.id', $userSite->id)
        );
    }

    /**
     * Test Inertia props only include user's activities.
     */
    public function test_inertia_props_only_include_users_activities(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $otherUser = User::factory()->create(['email_verified_at' => now()]);

        // Create activities for both users
        activity()
            ->causedBy($user)
            ->event('server.created')
            ->withProperties(['name' => 'User Server'])
            ->log('User created server');

        activity()
            ->causedBy($otherUser)
            ->event('server.created')
            ->withProperties(['name' => 'Other User Server'])
            ->log('Other user created server');

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - Inertia props should only contain user's activity
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('dashboard.activities', 1)
            ->where('dashboard.activities.0.detail', 'User Server')
        );
    }

    /**
     * Test Inertia props show empty arrays when user has no data but others do.
     */
    public function test_inertia_props_empty_when_user_has_no_data_but_others_do(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $otherUser = User::factory()->create(['email_verified_at' => now()]);

        // Create data only for other user
        $otherServer = Server::factory()->create(['user_id' => $otherUser->id]);
        ServerSite::factory()->create(['server_id' => $otherServer->id]);
        activity()
            ->causedBy($otherUser)
            ->event('server.created')
            ->log('Other user activity');

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - Inertia props should have empty arrays for this user
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('dashboard.servers', 0)
            ->has('dashboard.sites', 0)
            ->has('dashboard.activities', 0)
        );
    }

    /**
     * Test Inertia props respect 5 server limit per user.
     */
    public function test_inertia_props_respect_five_server_limit_per_user(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $otherUser = User::factory()->create(['email_verified_at' => now()]);

        // Create 7 servers for user, 10 for other user
        Server::factory()->count(7)->create(['user_id' => $user->id]);
        Server::factory()->count(10)->create(['user_id' => $otherUser->id]);

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - should show only 5 of user's servers, not affected by other user's servers
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('dashboard.servers', 5)
        );
    }

    /**
     * Test Inertia props respect 5 site limit per user.
     */
    public function test_inertia_props_respect_five_site_limit_per_user(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $otherUser = User::factory()->create(['email_verified_at' => now()]);

        $userServer = Server::factory()->create(['user_id' => $user->id]);
        $otherUserServer = Server::factory()->create(['user_id' => $otherUser->id]);

        // Create 7 sites for user's server, 10 for other user's server
        ServerSite::factory()->count(7)->create(['server_id' => $userServer->id]);
        ServerSite::factory()->count(10)->create(['server_id' => $otherUserServer->id]);

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - should show only 5 of user's sites, not affected by other user's sites
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('dashboard.sites', 5)
        );
    }

    /**
     * Test Inertia props do not leak other users' server IDs.
     */
    public function test_inertia_props_do_not_leak_other_users_server_ids(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $otherUser = User::factory()->create(['email_verified_at' => now()]);

        $userServer = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'User Server',
        ]);

        $otherUserServer = Server::factory()->create([
            'user_id' => $otherUser->id,
            'vanity_name' => 'Other Server',
        ]);

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - response should only contain user's server, not other user's
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('dashboard.servers', 1)
            ->where('dashboard.servers.0.id', $userServer->id)
        );
    }

    /**
     * Test Inertia props do not leak other users' site data.
     */
    public function test_inertia_props_do_not_leak_other_users_site_data(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $otherUser = User::factory()->create(['email_verified_at' => now()]);

        $userServer = Server::factory()->create(['user_id' => $user->id]);
        $otherUserServer = Server::factory()->create(['user_id' => $otherUser->id]);

        $userSite = ServerSite::factory()->create([
            'server_id' => $userServer->id,
            'domain' => 'mysite.com',
        ]);

        $otherUserSite = ServerSite::factory()->create([
            'server_id' => $otherUserServer->id,
            'domain' => 'secret-other-site.com',
        ]);

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - response should only contain user's site, not other user's
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('dashboard.sites', 1)
            ->where('dashboard.sites.0.domain', 'mysite.com')
            ->where('dashboard.sites.0.id', $userSite->id)
        );
    }

    /**
     * Test Inertia shares can_create_server true when user has available slots.
     */
    public function test_inertia_shares_can_create_server_true_when_user_has_available_slots(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        // User has no servers, free plan allows 1

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('auth.user.can_create_server', true)
        );
    }

    /**
     * Test Inertia shares can_create_server false when user is at server limit.
     */
    public function test_inertia_shares_can_create_server_false_when_user_at_limit(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        Server::factory()->create([
            'user_id' => $user->id,
            'counted_in_subscription' => true,
        ]);
        // Free plan limit is 1, user now has 1 server

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('auth.user.can_create_server', false)
        );
    }

    /**
     * Test Inertia shares can_create_server true when user has servers not counted in subscription.
     */
    public function test_inertia_shares_can_create_server_true_when_servers_not_counted(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        Server::factory()->create([
            'user_id' => $user->id,
            'counted_in_subscription' => false, // Not counted toward limit
        ]);
        // User has a server that doesn't count, so they can still create

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('auth.user.can_create_server', true)
        );
    }

    /**
     * Test Inertia shares plan_name with user data.
     */
    public function test_inertia_shares_plan_name_with_user_data(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('auth.user.plan_name', 'Free')
        );
    }
}
