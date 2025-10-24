<?php

namespace Tests\Unit\Policies;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\User;
use App\Policies\ServerSupervisorPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerSupervisorPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test view returns allowed when user owns the server.
     */
    public function test_view_returns_allowed_when_user_owns_the_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $policy = new ServerSupervisorPolicy;

        // Act
        $response = $policy->view($user, $server);

        // Assert
        $this->assertTrue($response->allowed());
    }

    /**
     * Test view returns denied when user does not own the server.
     */
    public function test_view_returns_denied_when_user_does_not_own_the_server(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $owner->id]);
        $policy = new ServerSupervisorPolicy;

        // Act
        $response = $policy->view($otherUser, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('do not have permission to view', $response->message());
    }

    /**
     * Test install returns allowed when user owns server and supervisor not installed.
     */
    public function test_install_returns_allowed_when_user_owns_server_and_supervisor_not_installed(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id, 'supervisor_status' => null]);
        $policy = new ServerSupervisorPolicy;

        // Act
        $response = $policy->install($user, $server);

        // Assert
        $this->assertTrue($response->allowed());
    }

    /**
     * Test install returns denied when user does not own the server.
     */
    public function test_install_returns_denied_when_user_does_not_own_the_server(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $owner->id]);
        $policy = new ServerSupervisorPolicy;

        // Act
        $response = $policy->install($otherUser, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('do not have permission to install', $response->message());
    }

    /**
     * Test install returns denied when supervisor is installing.
     */
    public function test_install_returns_denied_when_supervisor_is_installing(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => TaskStatus::Installing,
        ]);
        $policy = new ServerSupervisorPolicy;

        // Act
        $response = $policy->install($user, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('already installed or being installed', $response->message());
    }

    /**
     * Test install returns denied when supervisor is active.
     */
    public function test_install_returns_denied_when_supervisor_is_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => TaskStatus::Active,
        ]);
        $policy = new ServerSupervisorPolicy;

        // Act
        $response = $policy->install($user, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('already installed or being installed', $response->message());
    }

    /**
     * Test install returns allowed when supervisor is failed.
     */
    public function test_install_returns_allowed_when_supervisor_is_failed(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => TaskStatus::Failed,
        ]);
        $policy = new ServerSupervisorPolicy;

        // Act
        $response = $policy->install($user, $server);

        // Assert
        $this->assertTrue($response->allowed());
    }

    /**
     * Test uninstall returns allowed when user owns server and supervisor is active.
     */
    public function test_uninstall_returns_allowed_when_user_owns_server_and_supervisor_is_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => TaskStatus::Active,
        ]);
        $policy = new ServerSupervisorPolicy;

        // Act
        $response = $policy->uninstall($user, $server);

        // Assert
        $this->assertTrue($response->allowed());
    }

    /**
     * Test uninstall returns denied when user does not own the server.
     */
    public function test_uninstall_returns_denied_when_user_does_not_own_the_server(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $owner->id,
            'supervisor_status' => TaskStatus::Active,
        ]);
        $policy = new ServerSupervisorPolicy;

        // Act
        $response = $policy->uninstall($otherUser, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('do not have permission to uninstall', $response->message());
    }

    /**
     * Test uninstall returns denied when supervisor is not active.
     */
    public function test_uninstall_returns_denied_when_supervisor_is_not_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => TaskStatus::Installing,
        ]);
        $policy = new ServerSupervisorPolicy;

        // Act
        $response = $policy->uninstall($user, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('must be active to uninstall', $response->message());
    }

    /**
     * Test uninstall returns denied when supervisor status is null.
     */
    public function test_uninstall_returns_denied_when_supervisor_status_is_null(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id, 'supervisor_status' => null]);
        $policy = new ServerSupervisorPolicy;

        // Act
        $response = $policy->uninstall($user, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('must be active to uninstall', $response->message());
    }

    /**
     * Test createTask returns allowed when user owns server and supervisor is active.
     */
    public function test_create_task_returns_allowed_when_user_owns_server_and_supervisor_is_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => TaskStatus::Active,
        ]);
        $policy = new ServerSupervisorPolicy;

        // Act
        $response = $policy->createTask($user, $server);

        // Assert
        $this->assertTrue($response->allowed());
    }

    /**
     * Test createTask returns denied when user does not own the server.
     */
    public function test_create_task_returns_denied_when_user_does_not_own_the_server(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $owner->id,
            'supervisor_status' => TaskStatus::Active,
        ]);
        $policy = new ServerSupervisorPolicy;

        // Act
        $response = $policy->createTask($otherUser, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('do not have permission to create tasks', $response->message());
    }

    /**
     * Test createTask returns denied when supervisor is not active.
     */
    public function test_create_task_returns_denied_when_supervisor_is_not_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => TaskStatus::Installing,
        ]);
        $policy = new ServerSupervisorPolicy;

        // Act
        $response = $policy->createTask($user, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('must be active before creating tasks', $response->message());
    }

    /**
     * Test createTask returns denied when supervisor status is null.
     */
    public function test_create_task_returns_denied_when_supervisor_status_is_null(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id, 'supervisor_status' => null]);
        $policy = new ServerSupervisorPolicy;

        // Act
        $response = $policy->createTask($user, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('must be active before creating tasks', $response->message());
    }

    /**
     * Test updateTask returns allowed when user owns server and supervisor is active.
     */
    public function test_update_task_returns_allowed_when_user_owns_server_and_supervisor_is_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => TaskStatus::Active,
        ]);
        $policy = new ServerSupervisorPolicy;

        // Act
        $response = $policy->updateTask($user, $server);

        // Assert
        $this->assertTrue($response->allowed());
    }

    /**
     * Test updateTask returns denied when user does not own the server.
     */
    public function test_update_task_returns_denied_when_user_does_not_own_the_server(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $owner->id,
            'supervisor_status' => TaskStatus::Active,
        ]);
        $policy = new ServerSupervisorPolicy;

        // Act
        $response = $policy->updateTask($otherUser, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('do not have permission to update tasks', $response->message());
    }

    /**
     * Test updateTask returns denied when supervisor is not active.
     */
    public function test_update_task_returns_denied_when_supervisor_is_not_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => TaskStatus::Failed,
        ]);
        $policy = new ServerSupervisorPolicy;

        // Act
        $response = $policy->updateTask($user, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('must be active to update tasks', $response->message());
    }

    /**
     * Test deleteTask returns allowed when user owns the server.
     */
    public function test_delete_task_returns_allowed_when_user_owns_the_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $policy = new ServerSupervisorPolicy;

        // Act
        $response = $policy->deleteTask($user, $server);

        // Assert
        $this->assertTrue($response->allowed());
    }

    /**
     * Test deleteTask returns denied when user does not own the server.
     */
    public function test_delete_task_returns_denied_when_user_does_not_own_the_server(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $owner->id]);
        $policy = new ServerSupervisorPolicy;

        // Act
        $response = $policy->deleteTask($otherUser, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('do not have permission to delete tasks', $response->message());
    }

    /**
     * Test toggleTask returns allowed when user owns the server.
     */
    public function test_toggle_task_returns_allowed_when_user_owns_the_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $policy = new ServerSupervisorPolicy;

        // Act
        $response = $policy->toggleTask($user, $server);

        // Assert
        $this->assertTrue($response->allowed());
    }

    /**
     * Test toggleTask returns denied when user does not own the server.
     */
    public function test_toggle_task_returns_denied_when_user_does_not_own_the_server(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $owner->id]);
        $policy = new ServerSupervisorPolicy;

        // Act
        $response = $policy->toggleTask($otherUser, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('do not have permission to toggle tasks', $response->message());
    }

    /**
     * Test restartTask returns allowed when user owns the server.
     */
    public function test_restart_task_returns_allowed_when_user_owns_the_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $policy = new ServerSupervisorPolicy;

        // Act
        $response = $policy->restartTask($user, $server);

        // Assert
        $this->assertTrue($response->allowed());
    }

    /**
     * Test restartTask returns denied when user does not own the server.
     */
    public function test_restart_task_returns_denied_when_user_does_not_own_the_server(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $owner->id]);
        $policy = new ServerSupervisorPolicy;

        // Act
        $response = $policy->restartTask($otherUser, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('do not have permission to restart tasks', $response->message());
    }

    /**
     * Test all operations fail for attacker attempting to access owner's server.
     */
    public function test_all_operations_fail_for_attacker_attempting_to_access_owners_server(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $owner->id,
            'supervisor_status' => TaskStatus::Active,
        ]);
        $policy = new ServerSupervisorPolicy;

        // Act & Assert
        $this->assertTrue($policy->view($attacker, $server)->denied());
        $this->assertTrue($policy->install($attacker, $server)->denied());
        $this->assertTrue($policy->uninstall($attacker, $server)->denied());
        $this->assertTrue($policy->createTask($attacker, $server)->denied());
        $this->assertTrue($policy->updateTask($attacker, $server)->denied());
        $this->assertTrue($policy->deleteTask($attacker, $server)->denied());
        $this->assertTrue($policy->toggleTask($attacker, $server)->denied());
        $this->assertTrue($policy->restartTask($attacker, $server)->denied());
    }

    /**
     * Test install allows reinstallation after uninstalled status.
     */
    public function test_install_allows_reinstallation_after_uninstalled_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'supervisor_status' => null,
        ]);
        $policy = new ServerSupervisorPolicy;

        // Act
        $response = $policy->install($user, $server);

        // Assert
        $this->assertTrue($response->allowed());
    }

    /**
     * Test task operations work with null supervisor status for delete/toggle/restart.
     */
    public function test_task_operations_work_with_null_supervisor_status_for_simple_operations(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id, 'supervisor_status' => null]);
        $policy = new ServerSupervisorPolicy;

        // Act & Assert - deleteTask, toggleTask, restartTask only check ownership
        $this->assertTrue($policy->deleteTask($user, $server)->allowed());
        $this->assertTrue($policy->toggleTask($user, $server)->allowed());
        $this->assertTrue($policy->restartTask($user, $server)->allowed());
    }
}
