<?php

namespace Tests\Unit\Policies;

use App\Models\Server;
use App\Models\User;
use App\Policies\ServerPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test viewAny returns true for any user.
     */
    public function test_view_any_returns_true_for_any_user(): void
    {
        // Arrange
        $user = User::factory()->create();
        $policy = new ServerPolicy;

        // Act
        $result = $policy->viewAny($user);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test view returns true when user owns the server.
     */
    public function test_view_returns_true_when_user_owns_the_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $policy = new ServerPolicy;

        // Act
        $result = $policy->view($user, $server);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test view returns false when user does not own the server.
     */
    public function test_view_returns_false_when_user_does_not_own_the_server(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $owner->id]);
        $policy = new ServerPolicy;

        // Act
        $result = $policy->view($otherUser, $server);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test create returns true for any user.
     */
    public function test_create_returns_true_for_any_user(): void
    {
        // Arrange
        $user = User::factory()->create();
        $policy = new ServerPolicy;

        // Act
        $result = $policy->create($user);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test update returns true when user owns the server.
     */
    public function test_update_returns_true_when_user_owns_the_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $policy = new ServerPolicy;

        // Act
        $result = $policy->update($user, $server);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test update returns false when user does not own the server.
     */
    public function test_update_returns_false_when_user_does_not_own_the_server(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $owner->id]);
        $policy = new ServerPolicy;

        // Act
        $result = $policy->update($otherUser, $server);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test delete returns true when user owns the server.
     */
    public function test_delete_returns_true_when_user_owns_the_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $policy = new ServerPolicy;

        // Act
        $result = $policy->delete($user, $server);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test delete returns false when user does not own the server.
     */
    public function test_delete_returns_false_when_user_does_not_own_the_server(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $owner->id]);
        $policy = new ServerPolicy;

        // Act
        $result = $policy->delete($otherUser, $server);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test restore returns true when user owns the server.
     */
    public function test_restore_returns_true_when_user_owns_the_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $policy = new ServerPolicy;

        // Act
        $result = $policy->restore($user, $server);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test restore returns false when user does not own the server.
     */
    public function test_restore_returns_false_when_user_does_not_own_the_server(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $owner->id]);
        $policy = new ServerPolicy;

        // Act
        $result = $policy->restore($otherUser, $server);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test forceDelete returns true when user owns the server.
     */
    public function test_force_delete_returns_true_when_user_owns_the_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $policy = new ServerPolicy;

        // Act
        $result = $policy->forceDelete($user, $server);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test forceDelete returns false when user does not own the server.
     */
    public function test_force_delete_returns_false_when_user_does_not_own_the_server(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $owner->id]);
        $policy = new ServerPolicy;

        // Act
        $result = $policy->forceDelete($otherUser, $server);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test authorization works with different user IDs.
     */
    public function test_authorization_works_with_different_user_ids(): void
    {
        // Arrange
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $server1 = Server::factory()->create(['user_id' => $user1->id]);
        $server2 = Server::factory()->create(['user_id' => $user2->id]);

        $policy = new ServerPolicy;

        // Act & Assert - User 1 can only access their server
        $this->assertTrue($policy->view($user1, $server1));
        $this->assertFalse($policy->view($user1, $server2));

        // User 2 can only access their server
        $this->assertTrue($policy->view($user2, $server2));
        $this->assertFalse($policy->view($user2, $server1));

        // User 3 cannot access any servers
        $this->assertFalse($policy->view($user3, $server1));
        $this->assertFalse($policy->view($user3, $server2));
    }

    /**
     * Test all destructive operations require ownership.
     */
    public function test_all_destructive_operations_require_ownership(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $owner->id]);
        $policy = new ServerPolicy;

        // Act & Assert - Owner can perform all operations
        $this->assertTrue($policy->update($owner, $server));
        $this->assertTrue($policy->delete($owner, $server));
        $this->assertTrue($policy->restore($owner, $server));
        $this->assertTrue($policy->forceDelete($owner, $server));

        // Attacker cannot perform any operations
        $this->assertFalse($policy->update($attacker, $server));
        $this->assertFalse($policy->delete($attacker, $server));
        $this->assertFalse($policy->restore($attacker, $server));
        $this->assertFalse($policy->forceDelete($attacker, $server));
    }
}
