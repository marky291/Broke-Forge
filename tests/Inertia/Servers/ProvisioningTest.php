<?php

namespace Tests\Inertia\Servers;

use App\Models\Server;
use App\Models\User;
use App\Packages\Enums\ProvisionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProvisioningTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test provisioning page renders correct Inertia component.
     */
    public function test_provisioning_page_renders_correct_component(): void
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
        );
    }

    /**
     * Test provisioning page provides server props.
     */
    public function test_provisioning_page_provides_server_props(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'My Production Server',
            'public_ip' => '192.168.100.50',
            'ssh_port' => 2222,
            'provision_status' => ProvisionStatus::Installing,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert - verify frontend receives server data
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->has('server')
            ->where('server.id', $server->id)
            ->where('server.vanity_name', 'My Production Server')
            ->where('server.public_ip', '192.168.100.50')
            ->where('server.ssh_port', 2222)
            ->where('server.provision_status', 'installing')
        );
    }

    /**
     * Test provisioning page provides provision command prop.
     */
    public function test_provisioning_page_provides_provision_command(): void
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

        // Assert - provision command should be available for frontend display
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->has('provision')
            ->has('provision.command')
            ->where('provision.command', fn ($command) => is_string($command) &&
                str_contains($command, 'wget') &&
                str_contains($command, "servers/{$server->id}/provision")
            )
        );
    }

    /**
     * Test provisioning page provides root password prop.
     */
    public function test_provisioning_page_provides_root_password(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Pending,
            'ssh_root_password' => 'secure-password-456',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert - root password should be provided for frontend display
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->has('provision.root_password')
            ->where('provision.root_password', 'secure-password-456')
        );
    }

    /**
     * Test provisioning page provides steps data.
     */
    public function test_provisioning_page_provides_steps_data(): void
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

        // Assert - steps should be provided for progress display
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->has('server.steps', 8)
            ->has('server.steps.0', fn ($step) => $step
                ->has('step')
                ->has('name')
                ->has('description')
                ->has('status')
                ->etc()
            )
        );
    }

    /**
     * Test provisioning page shows correct step statuses.
     */
    public function test_provisioning_page_shows_correct_step_statuses(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Installing,
            'provision' => collect([
                1 => 'completed',
                2 => 'installing',
            ]),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert - step statuses should reflect current provision state
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
            ->has('server.steps.1.status', fn ($status) => $status
                ->where('isInstalling', true)
                ->where('isCompleted', false)
                ->has('isFailed')
                ->has('isPending')
                ->etc()
            )
        );
    }

    /**
     * Test provisioning page for pending status.
     */
    public function test_provisioning_page_for_pending_status(): void
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
            ->where('server.provision_status', 'pending')
        );
    }

    /**
     * Test provisioning page for installing status.
     */
    public function test_provisioning_page_for_installing_status(): void
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
     * Test provisioning page for failed status.
     */
    public function test_provisioning_page_for_failed_status(): void
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
            ->where('server.provision_status', 'failed')
        );
    }

    /**
     * Test provisioning page with empty provision data shows initial state.
     */
    public function test_provisioning_page_with_empty_provision_shows_initial_state(): void
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

        // Assert - should still provide step data even with empty provision
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->has('server.steps', 8)
        );
    }

    /**
     * Test provisioning page includes server ID for frontend routing.
     */
    public function test_provisioning_page_includes_server_id(): void
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

        // Assert - server ID needed for frontend routing/actions
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->where('server.id', $server->id)
        );
    }

    /**
     * Test provisioning page data structure is consistent.
     */
    public function test_provisioning_page_data_structure_is_consistent(): void
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

        // Assert - ensure consistent structure for frontend
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->has('server', fn ($server) => $server
                ->has('id')
                ->has('vanity_name')
                ->has('public_ip')
                ->has('ssh_port')
                ->has('provision_status')
                ->has('steps')
                ->etc()
            )
            ->has('provision', fn ($provision) => $provision
                ->has('command')
                ->has('root_password')
            )
        );
    }

    /**
     * Test user sees initial pending state before provisioning starts.
     */
    public function test_user_sees_initial_pending_state_all_steps_pending(): void
    {
        // Arrange - server just created, no provision started yet
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Pending,
            'provision' => collect([
                1 => 'installing',
            ]),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert - user should see step 1 installing
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->where('server.provision_status', 'pending')
            ->has('server.steps.0.status', fn ($status) => $status
                ->where('isInstalling', true)
                ->where('isCompleted', false)
                ->where('isPending', false)
                ->where('isFailed', false)
            )
        );
    }

    /**
     * Test user sees step 1 completed, step 2 installing.
     */
    public function test_user_sees_step_1_completed_step_2_installing(): void
    {
        // Arrange - connection established, SSH keys being set up
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Installing,
            'provision' => collect([
                1 => 'completed',
                2 => 'installing',
            ]),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert - user should see progress
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->where('server.provision_status', 'installing')
            ->has('server.steps.0.status', fn ($status) => $status
                ->where('isCompleted', true)
                ->where('isInstalling', false)
                ->where('isPending', false)
                ->where('isFailed', false)
            )
            ->has('server.steps.1.status', fn ($status) => $status
                ->where('isCompleted', false)
                ->where('isInstalling', true)
                ->where('isPending', false)
                ->where('isFailed', false)
            )
        );
    }

    /**
     * Test user sees step 2 completed, step 3 installing.
     */
    public function test_user_sees_step_2_completed_step_3_installing(): void
    {
        // Arrange - SSH keys set up, provisioning script running
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Installing,
            'provision' => collect([
                1 => 'completed',
                2 => 'completed',
                3 => 'installing',
            ]),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert - user should see provisioning script progress
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->where('server.provision_status', 'installing')
            ->where('server.steps.0.status.isCompleted', true)
            ->where('server.steps.1.status.isCompleted', true)
            ->has('server.steps.2.status', fn ($status) => $status
                ->where('isInstalling', true)
                ->where('isCompleted', false)
                ->where('isPending', false)
                ->etc()
            )
        );
    }

    /**
     * Test user sees step 3 completed, step 4 installing.
     */
    public function test_user_sees_step_3_completed_step_4_installing(): void
    {
        // Arrange - provisioning script complete, web services installing
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Installing,
            'provision' => collect([
                1 => 'completed',
                2 => 'completed',
                3 => 'completed',
                4 => 'installing',
            ]),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert - user should see web services installation progress
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->where('server.provision_status', 'installing')
            ->where('server.steps.0.status.isCompleted', true)
            ->where('server.steps.1.status.isCompleted', true)
            ->where('server.steps.2.status.isCompleted', true)
            ->has('server.steps.3.status', fn ($status) => $status
                ->where('isInstalling', true)
                ->where('isCompleted', false)
                ->etc()
            )
        );
    }

    /**
     * Test user sees step failed during step 2.
     */
    public function test_user_sees_step_2_failed(): void
    {
        // Arrange - SSH key setup failed
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Failed,
            'provision' => collect([
                1 => 'completed',
                2 => 'failed',
            ]),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert - user should see failure state
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->where('server.provision_status', 'failed')
            ->where('server.steps.0.status.isCompleted', true)
            ->has('server.steps.1.status', fn ($status) => $status
                ->where('isFailed', true)
                ->where('isCompleted', false)
                ->where('isInstalling', false)
                ->etc()
            )
        );
    }

    /**
     * Test user sees step failed during step 3.
     */
    public function test_user_sees_step_3_failed(): void
    {
        // Arrange - provisioning script failed
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
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert - user should see which step failed
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->where('server.provision_status', 'failed')
            ->has('server.steps.2.status', fn ($status) => $status
                ->where('isFailed', true)
                ->where('isCompleted', false)
                ->etc()
            )
        );
    }

    /**
     * Test user sees all 8 steps with correct numbering.
     */
    public function test_user_sees_all_8_steps_with_correct_numbering(): void
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

        // Assert - user should see all 8 steps numbered correctly
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->has('server.steps', 8)
            ->where('server.steps.0.step', 1)
            ->where('server.steps.1.step', 2)
            ->where('server.steps.2.step', 3)
            ->where('server.steps.3.step', 4)
            ->where('server.steps.4.step', 5)
            ->where('server.steps.5.step', 6)
            ->where('server.steps.6.step', 7)
            ->where('server.steps.7.step', 8)
        );
    }

    /**
     * Test user sees step names for display.
     */
    public function test_user_sees_step_names_for_display(): void
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

        // Assert - user should see human-readable step names
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->where('server.steps.0.name', 'Waiting for connection')
            ->has('server.steps.0.description')
            ->has('server.steps.1.name')
            ->has('server.steps.1.description')
        );
    }

    /**
     * Test user sees multiple steps completed with remaining pending.
     */
    public function test_user_sees_multiple_steps_completed_remaining_pending(): void
    {
        // Arrange - halfway through provisioning
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Installing,
            'provision' => collect([
                1 => 'completed',
                2 => 'completed',
                3 => 'completed',
                4 => 'completed',
            ]),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert - user should see completed vs pending steps
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->where('server.steps.0.status.isCompleted', true)
            ->where('server.steps.1.status.isCompleted', true)
            ->where('server.steps.2.status.isCompleted', true)
            ->where('server.steps.3.status.isCompleted', true)
            ->has('server.steps.4.status', fn ($status) => $status
                ->where('isPending', true)
                ->where('isCompleted', false)
                ->etc()
            )
            ->where('server.steps.5.status.isPending', true)
            ->where('server.steps.6.status.isPending', true)
            ->where('server.steps.7.status.isPending', true)
        );
    }

    /**
     * Test user sees provision status changes reflected in UI.
     */
    public function test_user_sees_provision_status_changes_in_ui(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Test each status
        $statuses = [
            ['enum' => ProvisionStatus::Pending, 'string' => 'pending'],
            ['enum' => ProvisionStatus::Installing, 'string' => 'installing'],
            ['enum' => ProvisionStatus::Failed, 'string' => 'failed'],
        ];

        foreach ($statuses as $status) {
            $server = Server::factory()->create([
                'user_id' => $user->id,
                'provision_status' => $status['enum'],
            ]);

            // Act
            $response = $this->actingAs($user)
                ->get("/servers/{$server->id}/provisioning/setup");

            // Assert - status displayed correctly
            $response->assertStatus(200);
            $response->assertInertia(fn ($page) => $page
                ->component('servers/provisioning')
                ->where('server.provision_status', $status['string'])
            );
        }
    }

    /**
     * Test user sees real-time step transitions.
     */
    public function test_user_sees_step_transition_from_installing_to_completed(): void
    {
        // Arrange - simulate real-time update
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Installing,
            'provision' => collect([
                1 => 'completed',
                2 => 'completed',
                3 => 'completed',
            ]),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert - steps reflect the current state
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->where('server.provision_status', 'installing')
            ->where('server.steps.0.status.isCompleted', true)
            ->where('server.steps.1.status.isCompleted', true)
            ->where('server.steps.2.status.isCompleted', true)
        );
    }

    /**
     * Test user sees pending steps have no progress indicator.
     */
    public function test_user_sees_pending_steps_have_pending_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => ProvisionStatus::Installing,
            'provision' => collect([
                1 => 'completed',
            ]),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/provisioning/setup");

        // Assert - remaining steps show as pending
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/provisioning')
            ->has('server.steps.1.status', fn ($status) => $status
                ->where('isPending', true)
                ->where('isCompleted', false)
                ->where('isInstalling', false)
                ->where('isFailed', false)
            )
        );
    }
}
