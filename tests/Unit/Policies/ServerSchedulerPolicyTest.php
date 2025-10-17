<?php

namespace Tests\Unit\Policies;

use App\Enums\SchedulerStatus;
use App\Models\Server;
use App\Models\ServerScheduledTask;
use App\Models\User;
use App\Policies\ServerSchedulerPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerSchedulerPolicyTest extends TestCase
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
        $policy = new ServerSchedulerPolicy;

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
        $policy = new ServerSchedulerPolicy;

        // Act
        $response = $policy->view($otherUser, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('do not have permission to view', $response->message());
    }

    /**
     * Test install returns allowed when user owns server and scheduler not installed.
     */
    public function test_install_returns_allowed_when_user_owns_server_and_scheduler_not_installed(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id, 'scheduler_status' => null]);
        $policy = new ServerSchedulerPolicy;

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
        $policy = new ServerSchedulerPolicy;

        // Act
        $response = $policy->install($otherUser, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('do not have permission to install', $response->message());
    }

    /**
     * Test install returns denied when scheduler is installing.
     */
    public function test_install_returns_denied_when_scheduler_is_installing(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Installing,
        ]);
        $policy = new ServerSchedulerPolicy;

        // Act
        $response = $policy->install($user, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('already installed or being installed', $response->message());
    }

    /**
     * Test install returns denied when scheduler is active.
     */
    public function test_install_returns_denied_when_scheduler_is_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $policy = new ServerSchedulerPolicy;

        // Act
        $response = $policy->install($user, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('already installed or being installed', $response->message());
    }

    /**
     * Test install returns allowed when scheduler is failed.
     */
    public function test_install_returns_allowed_when_scheduler_is_failed(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Failed,
        ]);
        $policy = new ServerSchedulerPolicy;

        // Act
        $response = $policy->install($user, $server);

        // Assert
        $this->assertTrue($response->allowed());
    }

    /**
     * Test uninstall returns allowed when user owns server and scheduler is active.
     */
    public function test_uninstall_returns_allowed_when_user_owns_server_and_scheduler_is_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $policy = new ServerSchedulerPolicy;

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
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $policy = new ServerSchedulerPolicy;

        // Act
        $response = $policy->uninstall($otherUser, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('do not have permission to uninstall', $response->message());
    }

    /**
     * Test uninstall returns denied when scheduler is not active.
     */
    public function test_uninstall_returns_denied_when_scheduler_is_not_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Installing,
        ]);
        $policy = new ServerSchedulerPolicy;

        // Act
        $response = $policy->uninstall($user, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('must be active to uninstall', $response->message());
    }

    /**
     * Test uninstall returns denied when scheduler status is null.
     */
    public function test_uninstall_returns_denied_when_scheduler_status_is_null(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id, 'scheduler_status' => null]);
        $policy = new ServerSchedulerPolicy;

        // Act
        $response = $policy->uninstall($user, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('must be active to uninstall', $response->message());
    }

    /**
     * Test createTask returns allowed when user owns server and scheduler is active.
     */
    public function test_create_task_returns_allowed_when_user_owns_server_and_scheduler_is_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $policy = new ServerSchedulerPolicy;

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
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $policy = new ServerSchedulerPolicy;

        // Act
        $response = $policy->createTask($otherUser, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('do not have permission to create tasks', $response->message());
    }

    /**
     * Test createTask returns denied when scheduler is not active.
     */
    public function test_create_task_returns_denied_when_scheduler_is_not_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Installing,
        ]);
        $policy = new ServerSchedulerPolicy;

        // Act
        $response = $policy->createTask($user, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('must be active before creating tasks', $response->message());
    }

    /**
     * Test createTask returns denied when max tasks limit reached.
     */
    public function test_create_task_returns_denied_when_max_tasks_limit_reached(): void
    {
        // Arrange
        config(['scheduler.max_tasks_per_server' => 2]);
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        // Create 2 tasks to reach the limit
        ServerScheduledTask::factory()->count(2)->create(['server_id' => $server->id]);

        $policy = new ServerSchedulerPolicy;

        // Act
        $response = $policy->createTask($user, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('Maximum number of scheduled tasks', $response->message());
        $this->assertStringContainsString('(2)', $response->message());
    }

    /**
     * Test updateTask returns allowed when user owns server and scheduler is active.
     */
    public function test_update_task_returns_allowed_when_user_owns_server_and_scheduler_is_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $policy = new ServerSchedulerPolicy;

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
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $policy = new ServerSchedulerPolicy;

        // Act
        $response = $policy->updateTask($otherUser, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('do not have permission to update tasks', $response->message());
    }

    /**
     * Test updateTask returns denied when scheduler is not active.
     */
    public function test_update_task_returns_denied_when_scheduler_is_not_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Failed,
        ]);
        $policy = new ServerSchedulerPolicy;

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
        $policy = new ServerSchedulerPolicy;

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
        $policy = new ServerSchedulerPolicy;

        // Act
        $response = $policy->deleteTask($otherUser, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('do not have permission to delete tasks', $response->message());
    }

    /**
     * Test runTask returns allowed when user owns server and scheduler is active.
     */
    public function test_run_task_returns_allowed_when_user_owns_server_and_scheduler_is_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $policy = new ServerSchedulerPolicy;

        // Act
        $response = $policy->runTask($user, $server);

        // Assert
        $this->assertTrue($response->allowed());
    }

    /**
     * Test runTask returns denied when user does not own the server.
     */
    public function test_run_task_returns_denied_when_user_does_not_own_the_server(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $owner->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $policy = new ServerSchedulerPolicy;

        // Act
        $response = $policy->runTask($otherUser, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('do not have permission to run tasks', $response->message());
    }

    /**
     * Test runTask returns denied when scheduler is not active.
     */
    public function test_run_task_returns_denied_when_scheduler_is_not_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Uninstalled,
        ]);
        $policy = new ServerSchedulerPolicy;

        // Act
        $response = $policy->runTask($user, $server);

        // Assert
        $this->assertTrue($response->denied());
        $this->assertStringContainsString('must be active to run tasks', $response->message());
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
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $policy = new ServerSchedulerPolicy;

        // Act & Assert
        $this->assertTrue($policy->view($attacker, $server)->denied());
        $this->assertTrue($policy->install($attacker, $server)->denied());
        $this->assertTrue($policy->uninstall($attacker, $server)->denied());
        $this->assertTrue($policy->createTask($attacker, $server)->denied());
        $this->assertTrue($policy->updateTask($attacker, $server)->denied());
        $this->assertTrue($policy->deleteTask($attacker, $server)->denied());
        $this->assertTrue($policy->runTask($attacker, $server)->denied());
    }
}
