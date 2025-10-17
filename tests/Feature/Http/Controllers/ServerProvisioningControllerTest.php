<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Server;
use App\Models\User;
use App\Packages\Enums\ConnectionStatus;
use App\Packages\Enums\ProvisionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerProvisioningControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that guests cannot access provisioning setup page.
     */
    public function test_guest_cannot_access_provisioning_setup(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->get("/servers/{$server->id}/provisioning/setup");

        // Assert - guests should be redirected to login
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test authenticated user can access their server's provisioning page.
     */
    public function test_user_can_access_their_server_provisioning_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Pending,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert
        $response->assertStatus(200);
    }

    /**
     * Test user cannot access other user's server provisioning page.
     */
    public function test_user_cannot_access_other_users_server_provisioning(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $otherUser->id,
            'provision_status' => ProvisionStatus::Pending,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test provisioning page redirects to server page if fully provisioned.
     */
    public function test_redirects_to_server_page_when_provisioning_completed(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Completed,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert - should redirect to server show page
        $response->assertStatus(302);
        $response->assertRedirect(route('servers.show', $server));
    }

    /**
     * Test provisioning page displays for pending status.
     */
    public function test_displays_provisioning_page_for_pending_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Pending,
            'vanity_name' => 'Test Server',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->has('server')
            ->has('provision')
        );
    }

    /**
     * Test provisioning page displays for installing status.
     */
    public function test_displays_provisioning_page_for_installing_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Installing,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
        );
    }

    /**
     * Test provisioning page displays for failed status.
     */
    public function test_displays_provisioning_page_for_failed_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Failed,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
        );
    }

    /**
     * Test provisioning page includes server data.
     */
    public function test_provisioning_page_includes_server_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Pending,
            'vanity_name' => 'My Test Server',
            'public_ip' => '10.20.30.40',
            'ssh_port' => 22,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->where('server.id', $server->id)
            ->where('server.vanity_name', 'My Test Server')
            ->where('server.public_ip', '10.20.30.40')
            ->where('server.ssh_port', 22)
        );
    }

    /**
     * Test provisioning page includes provision data with command.
     */
    public function test_provisioning_page_includes_provision_command(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Pending,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->has('provision.command')
            ->where('provision.command', function ($command) use ($server) {
                return str_contains($command, "servers/{$server->id}/provision");
            })
        );
    }

    /**
     * Test provisioning page includes root password.
     */
    public function test_provisioning_page_includes_root_password(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Pending,
            'ssh_root_password' => 'test-password-123',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->has('provision.root_password')
            ->where('provision.root_password', 'test-password-123')
        );
    }

    /**
     * Test provisioning page includes provisioning steps.
     */
    public function test_provisioning_page_includes_provisioning_steps(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Pending,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert - should have 8 provisioning steps
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->has('server.steps', 8)
            ->has('server.steps.0', fn ($step) => $step
                ->where('step', 1)
                ->where('name', 'Waiting for connection')
                ->has('description')
                ->has('status')
                ->etc()
            )
        );
    }

    /**
     * Test provisioning page shows step status based on provision data.
     */
    public function test_provisioning_page_shows_correct_step_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Installing,
            'provision' => [
                1 => 'completed',
                2 => 'completed',
                3 => 'installing',
                4 => 'pending',
            ],
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->has('server.steps.0.status', fn ($status) => $status
                ->where('isCompleted', true)
                ->where('isPending', false)
                ->has('isFailed')
                ->has('isInstalling')
                ->etc()
            )
            ->has('server.steps.2.status', fn ($status) => $status
                ->where('isInstalling', true)
                ->where('isCompleted', false)
                ->has('isFailed')
                ->has('isPending')
                ->etc()
            )
        );
    }

    /**
     * Test provisioning page returns proper Inertia response structure.
     */
    public function test_provisioning_page_returns_proper_inertia_structure(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Pending,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->has('server')
            ->has('server.id')
            ->has('server.vanity_name')
            ->has('server.provision_status')
            ->has('server.steps')
            ->has('provision')
            ->has('provision.command')
            ->has('provision.root_password')
        );
    }

    /**
     * Test provisioning page with empty provision data.
     */
    public function test_provisioning_page_with_empty_provision_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Pending,
            'provision' => null,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert - should still work with empty provision data
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->has('server.steps', 8)
        );
    }

    /**
     * Test provisioning page includes server provision status.
     */
    public function test_provisioning_page_includes_provision_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Installing,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->where('server.provision_status', 'installing')
        );
    }

    /**
     * Test provisioning script endpoint returns shell script.
     */
    public function test_provision_script_endpoint_returns_shell_script(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act - this is a public endpoint, no auth required
        $response = $this->get("/servers/{$server->id}/provision");

        // Assert - should return shell script with correct content type
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/x-shellscript; charset=utf-8');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /**
     * Test provisioning script contains necessary commands.
     */
    public function test_provision_script_contains_necessary_commands(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->get("/servers/{$server->id}/provision");

        // Assert - script should contain shell commands (check for pipefail which is common in bash scripts)
        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertNotEmpty($content);
        $this->assertStringContainsString('set -', $content);
    }

    /**
     * Test retry provisioning requires authentication.
     */
    public function test_retry_provisioning_requires_authentication(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Failed,
        ]);

        // Act - guest attempt
        $response = $this->post("/servers/{$server->id}/provision/retry");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test retry provisioning requires authorization.
     */
    public function test_retry_provisioning_requires_authorization(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $otherUser->id,
            'provision_status' => ProvisionStatus::Failed,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/provision/retry");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test retry provisioning only works for failed status.
     */
    public function test_retry_only_works_for_failed_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Pending,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/provision/retry");

        // Assert - should redirect back with error
        $response->assertStatus(302);
        $response->assertRedirect(route('servers.provisioning', $server));
        $response->assertSessionHas('error', 'Provisioning is not in a failed state.');
    }

    /**
     * Test retry provisioning resets server state.
     */
    public function test_retry_provisioning_resets_server_state(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Failed,
            'connection_status' => ConnectionStatus::CONNECTED,
            'provision' => collect([
                1 => 'completed',
                2 => 'failed',
            ]),
        ]);

        // Create some events that should be cleared
        \App\Models\ServerEvent::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/provision/retry");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect(route('servers.provisioning', $server));
        $response->assertSessionHas('success', 'Provisioning reset. Run the provisioning command again.');

        $server->refresh();

        // Verify server state was reset
        $this->assertEquals(ProvisionStatus::Pending, $server->provision_status);
        $this->assertEquals(ConnectionStatus::PENDING, $server->connection_status);
        $this->assertEquals(0, $server->events()->count());
    }

    /**
     * Test retry provisioning generates new root password.
     */
    public function test_retry_provisioning_generates_new_root_password(): void
    {
        // Arrange
        $user = User::factory()->create();
        $oldPassword = 'old-password-123';
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Failed,
            'ssh_root_password' => $oldPassword,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/provision/retry");

        // Assert
        $response->assertStatus(302);

        $server->refresh();

        // New password should be different
        $this->assertNotEquals($oldPassword, $server->ssh_root_password);
        $this->assertNotEmpty($server->ssh_root_password);
    }

    /**
     * Test retry provisioning keeps provision data for display.
     */
    public function test_retry_provisioning_keeps_provision_data_for_history(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Failed,
            'provision' => collect([
                1 => 'completed',
                2 => 'completed',
                3 => 'failed',
            ]),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/provision/retry");

        // Assert
        $response->assertStatus(302);

        $server->refresh();

        // Provision data is kept for history (will be cleared on step 1 completion)
        $this->assertNotEmpty($server->provision);
        $this->assertEquals('failed', $server->provision->get(3));
    }
}
