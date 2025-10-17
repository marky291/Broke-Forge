<?php

namespace Tests\Unit\Policies;

use App\Models\PaymentMethod;
use App\Models\User;
use App\Policies\PaymentMethodPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test viewAny returns true for any user.
     */
    public function test_view_any_returns_true_for_any_user(): void
    {
        // Arrange
        $user = User::factory()->create();
        $policy = new PaymentMethodPolicy;

        // Act
        $result = $policy->viewAny($user);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test view returns true when user owns the payment method.
     */
    public function test_view_returns_true_when_user_owns_the_payment_method(): void
    {
        // Arrange
        $user = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['user_id' => $user->id]);
        $policy = new PaymentMethodPolicy;

        // Act
        $result = $policy->view($user, $paymentMethod);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test view returns false when user does not own the payment method.
     */
    public function test_view_returns_false_when_user_does_not_own_the_payment_method(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['user_id' => $owner->id]);
        $policy = new PaymentMethodPolicy;

        // Act
        $result = $policy->view($otherUser, $paymentMethod);

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
        $policy = new PaymentMethodPolicy;

        // Act
        $result = $policy->create($user);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test update returns true when user owns the payment method.
     */
    public function test_update_returns_true_when_user_owns_the_payment_method(): void
    {
        // Arrange
        $user = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['user_id' => $user->id]);
        $policy = new PaymentMethodPolicy;

        // Act
        $result = $policy->update($user, $paymentMethod);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test update returns false when user does not own the payment method.
     */
    public function test_update_returns_false_when_user_does_not_own_the_payment_method(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['user_id' => $owner->id]);
        $policy = new PaymentMethodPolicy;

        // Act
        $result = $policy->update($otherUser, $paymentMethod);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test delete returns true when user owns the payment method.
     */
    public function test_delete_returns_true_when_user_owns_the_payment_method(): void
    {
        // Arrange
        $user = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['user_id' => $user->id]);
        $policy = new PaymentMethodPolicy;

        // Act
        $result = $policy->delete($user, $paymentMethod);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test delete returns false when user does not own the payment method.
     */
    public function test_delete_returns_false_when_user_does_not_own_the_payment_method(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['user_id' => $owner->id]);
        $policy = new PaymentMethodPolicy;

        // Act
        $result = $policy->delete($otherUser, $paymentMethod);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test restore returns true when user owns the payment method.
     */
    public function test_restore_returns_true_when_user_owns_the_payment_method(): void
    {
        // Arrange
        $user = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['user_id' => $user->id]);
        $policy = new PaymentMethodPolicy;

        // Act
        $result = $policy->restore($user, $paymentMethod);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test restore returns false when user does not own the payment method.
     */
    public function test_restore_returns_false_when_user_does_not_own_the_payment_method(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['user_id' => $owner->id]);
        $policy = new PaymentMethodPolicy;

        // Act
        $result = $policy->restore($otherUser, $paymentMethod);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test forceDelete returns true when user owns the payment method.
     */
    public function test_force_delete_returns_true_when_user_owns_the_payment_method(): void
    {
        // Arrange
        $user = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['user_id' => $user->id]);
        $policy = new PaymentMethodPolicy;

        // Act
        $result = $policy->forceDelete($user, $paymentMethod);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test forceDelete returns false when user does not own the payment method.
     */
    public function test_force_delete_returns_false_when_user_does_not_own_the_payment_method(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['user_id' => $owner->id]);
        $policy = new PaymentMethodPolicy;

        // Act
        $result = $policy->forceDelete($otherUser, $paymentMethod);

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

        $paymentMethod1 = PaymentMethod::factory()->create(['user_id' => $user1->id]);
        $paymentMethod2 = PaymentMethod::factory()->create(['user_id' => $user2->id]);

        $policy = new PaymentMethodPolicy;

        // Act & Assert - User 1 can only access their payment method
        $this->assertTrue($policy->view($user1, $paymentMethod1));
        $this->assertFalse($policy->view($user1, $paymentMethod2));

        // User 2 can only access their payment method
        $this->assertTrue($policy->view($user2, $paymentMethod2));
        $this->assertFalse($policy->view($user2, $paymentMethod1));

        // User 3 cannot access any payment methods
        $this->assertFalse($policy->view($user3, $paymentMethod1));
        $this->assertFalse($policy->view($user3, $paymentMethod2));
    }

    /**
     * Test all destructive operations require ownership.
     */
    public function test_all_destructive_operations_require_ownership(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['user_id' => $owner->id]);
        $policy = new PaymentMethodPolicy;

        // Act & Assert - Owner can perform all operations
        $this->assertTrue($policy->update($owner, $paymentMethod));
        $this->assertTrue($policy->delete($owner, $paymentMethod));
        $this->assertTrue($policy->restore($owner, $paymentMethod));
        $this->assertTrue($policy->forceDelete($owner, $paymentMethod));

        // Attacker cannot perform any operations
        $this->assertFalse($policy->update($attacker, $paymentMethod));
        $this->assertFalse($policy->delete($attacker, $paymentMethod));
        $this->assertFalse($policy->restore($attacker, $paymentMethod));
        $this->assertFalse($policy->forceDelete($attacker, $paymentMethod));
    }

    /**
     * Test user cannot access another user's payment method even with same ID pattern.
     */
    public function test_user_cannot_access_another_users_payment_method_even_with_same_id_pattern(): void
    {
        // Arrange
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create payment methods that might have similar IDs
        $paymentMethod1 = PaymentMethod::factory()->create(['user_id' => $user1->id]);
        $paymentMethod2 = PaymentMethod::factory()->create(['user_id' => $user2->id]);

        $policy = new PaymentMethodPolicy;

        // Act & Assert - Ensure complete isolation
        $this->assertTrue($policy->view($user1, $paymentMethod1));
        $this->assertFalse($policy->view($user1, $paymentMethod2));

        $this->assertTrue($policy->delete($user2, $paymentMethod2));
        $this->assertFalse($policy->delete($user2, $paymentMethod1));
    }
}
