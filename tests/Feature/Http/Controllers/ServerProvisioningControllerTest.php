<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerSite;
use App\Models\User;
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
            'provision_status' => TaskStatus::Pending,
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
            'provision_status' => TaskStatus::Pending,
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
            'provision_status' => TaskStatus::Success,
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
            'provision_status' => TaskStatus::Pending,
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
            'provision_status' => TaskStatus::Installing,
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
            'provision_status' => TaskStatus::Failed,
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
            'provision_status' => TaskStatus::Pending,
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
            'provision_status' => TaskStatus::Pending,
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
            'provision_status' => TaskStatus::Pending,
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
            'provision_status' => TaskStatus::Pending,
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
            'provision_status' => TaskStatus::Installing,
            'provision_state' => [
                1 => 'success',
                2 => 'success',
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
            'provision_status' => TaskStatus::Pending,
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
            'provision_status' => TaskStatus::Pending,
            'provision_state' => null,
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
            'provision_status' => TaskStatus::Installing,
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
            'provision_status' => TaskStatus::Failed,
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
            'provision_status' => TaskStatus::Failed,
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
            'provision_status' => TaskStatus::Pending,
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
            'provision_status' => TaskStatus::Failed,
            'connection_status' => TaskStatus::Success,
            'provision_state' => collect([
                1 => 'success',
                2 => 'failed',
            ]),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/provision/retry");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect(route('servers.provisioning', $server));
        $response->assertSessionHas('success', 'Provisioning reset. Run the provisioning command again.');

        $server->refresh();

        // Verify server state was reset
        $this->assertEquals(TaskStatus::Pending, $server->provision_status);
        $this->assertEquals(TaskStatus::Pending, $server->connection_status);
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
            'provision_status' => TaskStatus::Failed,
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
     * Test retry provisioning clears provision state when SSH not established.
     */
    public function test_retry_provisioning_clears_provision_state_when_ssh_not_established(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => TaskStatus::Failed,
            'provision_state' => collect([
                1 => 'success',
                2 => 'success',
                3 => 'failed', // SSH not established
            ]),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/provision/retry");

        // Assert
        $response->assertStatus(302);

        $server->refresh();

        // Provision state should be cleared since SSH failed
        $this->assertTrue($server->provision_state->isEmpty());
        $this->assertEquals(TaskStatus::Pending, $server->provision_status);
    }

    /**
     * Test retry provisioning dispatches job when SSH is established with correct resumeFromStep.
     */
    public function test_retry_dispatches_job_when_ssh_established(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => TaskStatus::Failed,
            'provision_state' => collect([
                1 => 'success',
                2 => 'success',
                3 => 'success', // SSH established
                4 => 'success',
                5 => 'failed',
            ]),
            'provision_config' => collect([
                'php_version' => '8.4',
            ]),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/provision/retry");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect(route('servers.provisioning', $server));
        $response->assertSessionHas('success', 'Provisioning resumed from failed step.');

        $server->refresh();

        // Status should be installing, not pending
        $this->assertEquals(TaskStatus::Installing, $server->provision_status);

        // Job should be dispatched with resumeFromStep=5
        \Illuminate\Support\Facades\Queue::assertPushed(
            \App\Packages\Services\Nginx\NginxInstallerJob::class,
            fn ($job) => $job->server->id === $server->id
                && $job->isProvisioningServer === true
                && $job->resumeFromStep === 5
        );
    }

    /**
     * Test retry provisioning cleans up resources from failed step when step 5 fails.
     */
    public function test_retry_cleans_up_all_resources_when_step_5_fails(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => TaskStatus::Failed,
            'provision_state' => collect([
                1 => 'success',
                2 => 'success',
                3 => 'success',
                4 => 'success',
                5 => 'failed', // Firewall step failed
            ]),
            'provision_config' => collect([
                'php_version' => '8.4',
            ]),
        ]);

        // Create firewall (should be deleted since step 5 failed)
        $firewall = $server->firewall()->create(['status' => 'failed']);
        $firewall->rules()->create(['name' => 'HTTP', 'port' => '80', 'rule_type' => 'allow', 'status' => 'failed']);

        // Create resources from later steps (should also be deleted)
        $server->phps()->create(['version' => '8.4', 'is_cli_default' => true, 'status' => 'failed']);
        $server->nodes()->create(['version' => '20', 'status' => 'failed']);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/provision/retry");

        // Assert
        $response->assertStatus(302);

        $server->refresh();

        // All resources should be deleted when step 5 fails
        $this->assertNull($server->firewall);
        $this->assertEquals(0, $server->phps()->count());
        $this->assertEquals(0, $server->nodes()->count());
    }

    /**
     * Test retry preserves firewall when step 6 (PHP) fails.
     */
    public function test_retry_preserves_firewall_when_step_6_fails(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => TaskStatus::Failed,
            'provision_state' => collect([
                1 => 'success',
                2 => 'success',
                3 => 'success',
                4 => 'success',
                5 => 'success', // Firewall completed
                6 => 'failed',  // PHP failed
            ]),
            'provision_config' => collect([
                'php_version' => '8.4',
            ]),
        ]);

        // Create firewall (should be preserved)
        $firewall = $server->firewall()->create(['status' => 'active']);
        $firewall->rules()->create(['name' => 'HTTP', 'port' => '80', 'rule_type' => 'allow', 'status' => 'active']);

        // Create PHP (should be deleted)
        $server->phps()->create(['version' => '8.4', 'is_cli_default' => true, 'status' => 'failed']);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/provision/retry");

        // Assert
        $response->assertStatus(302);

        $server->refresh();

        // Firewall should be preserved
        $this->assertNotNull($server->firewall);
        $this->assertEquals(1, $server->firewall->rules()->count());

        // PHP should be deleted
        $this->assertEquals(0, $server->phps()->count());

        // Job should resume from step 6
        \Illuminate\Support\Facades\Queue::assertPushed(
            \App\Packages\Services\Nginx\NginxInstallerJob::class,
            fn ($job) => $job->resumeFromStep === 6
        );
    }

    /**
     * Test retry preserves firewall and PHP when step 7 (Nginx) fails.
     */
    public function test_retry_preserves_firewall_and_php_when_step_7_fails(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => TaskStatus::Failed,
            'provision_state' => collect([
                1 => 'success',
                2 => 'success',
                3 => 'success',
                4 => 'success',
                5 => 'success', // Firewall completed
                6 => 'success', // PHP completed
                7 => 'failed',  // Nginx failed
            ]),
            'provision_config' => collect([
                'php_version' => '8.4',
            ]),
        ]);

        // Create firewall (should be preserved)
        $firewall = $server->firewall()->create(['status' => 'active']);
        $firewall->rules()->create(['name' => 'HTTP', 'port' => '80', 'rule_type' => 'allow', 'status' => 'active']);

        // Create PHP (should be preserved)
        $server->phps()->create(['version' => '8.4', 'is_cli_default' => true, 'status' => 'active']);

        // Create reverse proxy (should be deleted)
        $server->reverseProxy()->create(['type' => 'nginx', 'status' => 'failed']);

        // Create default site (should be deleted)
        ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'default',
            'is_default' => true,
            'status' => 'failed',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/provision/retry");

        // Assert
        $response->assertStatus(302);

        $server->refresh();

        // Firewall and PHP should be preserved
        $this->assertNotNull($server->firewall);
        $this->assertEquals(1, $server->phps()->count());

        // Reverse proxy and default site should be deleted
        $this->assertNull($server->reverseProxy);
        $this->assertEquals(0, $server->sites()->where('is_default', true)->count());

        // Job should resume from step 7
        \Illuminate\Support\Facades\Queue::assertPushed(
            \App\Packages\Services\Nginx\NginxInstallerJob::class,
            fn ($job) => $job->resumeFromStep === 7
        );
    }

    /**
     * Test retry preserves all earlier resources when step 8 (final touches) fails.
     */
    public function test_retry_preserves_earlier_resources_when_step_8_fails(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => TaskStatus::Failed,
            'provision_state' => collect([
                1 => 'success',
                2 => 'success',
                3 => 'success',
                4 => 'success',
                5 => 'success', // Firewall completed
                6 => 'success', // PHP completed
                7 => 'success', // Nginx completed
                8 => 'failed',  // Final touches failed
            ]),
            'provision_config' => collect([
                'php_version' => '8.4',
            ]),
        ]);

        // Create firewall (should be preserved)
        $firewall = $server->firewall()->create(['status' => 'active']);
        $firewall->rules()->create(['name' => 'HTTP', 'port' => '80', 'rule_type' => 'allow', 'status' => 'active']);

        // Create PHP (should be preserved)
        $server->phps()->create(['version' => '8.4', 'is_cli_default' => true, 'status' => 'active']);

        // Create reverse proxy (should be preserved)
        $server->reverseProxy()->create(['type' => 'nginx', 'status' => 'active']);

        // Create default site (should be preserved)
        ServerSite::factory()->create([
            'server_id' => $server->id,
            'domain' => 'default',
            'is_default' => true,
            'status' => 'active',
        ]);

        // Create step 8 resources (should be deleted)
        $server->scheduledTasks()->create([
            'name' => 'Test task',
            'command' => 'echo test',
            'frequency' => 'daily',
            'status' => 'failed',
        ]);
        $server->nodes()->create(['version' => '20', 'status' => 'failed']);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/provision/retry");

        // Assert
        $response->assertStatus(302);

        $server->refresh();

        // Steps 5-7 resources should be preserved
        $this->assertNotNull($server->firewall);
        $this->assertEquals(1, $server->phps()->count());
        $this->assertNotNull($server->reverseProxy);
        $this->assertEquals(1, $server->sites()->where('is_default', true)->count());

        // Step 8 resources should be deleted
        $this->assertEquals(0, $server->scheduledTasks()->count());
        $this->assertEquals(0, $server->nodes()->count());

        // Job should resume from step 8
        \Illuminate\Support\Facades\Queue::assertPushed(
            \App\Packages\Services\Nginx\NginxInstallerJob::class,
            fn ($job) => $job->resumeFromStep === 8
        );
    }

    /**
     * Test retry sets correct provision_state preserving completed steps.
     */
    public function test_retry_preserves_completed_steps_in_provision_state(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => TaskStatus::Failed,
            'provision_state' => collect([
                1 => 'success',
                2 => 'success',
                3 => 'success',
                4 => 'success',
                5 => 'success',
                6 => 'success',
                7 => 'failed', // Step 7 failed
            ]),
            'provision_config' => collect([
                'php_version' => '8.4',
            ]),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/provision/retry");

        // Assert
        $server->refresh();

        // Steps 1-6 should remain success, step 7 should be installing
        $this->assertEquals('success', $server->provision_state->get(1));
        $this->assertEquals('success', $server->provision_state->get(2));
        $this->assertEquals('success', $server->provision_state->get(3));
        $this->assertEquals('success', $server->provision_state->get(4));
        $this->assertEquals('success', $server->provision_state->get(5));
        $this->assertEquals('success', $server->provision_state->get(6));
        $this->assertEquals('installing', $server->provision_state->get(7));

        // Step 8 should not exist yet
        $this->assertNull($server->provision_state->get(8));
    }

    /**
     * Test retry without php_version in provision_config falls back to reset.
     */
    public function test_retry_falls_back_to_reset_without_php_version(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => TaskStatus::Failed,
            'provision_state' => collect([
                1 => 'success',
                2 => 'success',
                3 => 'success', // SSH established but no php_version
            ]),
            'provision_config' => collect([]), // No php_version
        ]);

        $oldPassword = $server->ssh_root_password;

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/provision/retry");

        // Assert - should fall back to reset behavior
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Provisioning reset. Run the provisioning command again.');

        $server->refresh();

        $this->assertEquals(TaskStatus::Pending, $server->provision_status);
        $this->assertNotEquals($oldPassword, $server->ssh_root_password);
    }
}
