<?php

namespace Tests\Unit\Models;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test payment method belongs to a user.
     */
    public function test_belongs_to_user(): void
    {
        // Arrange
        $user = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create([
            'user_id' => $user->id,
        ]);

        // Act
        $result = $paymentMethod->user;

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($user->id, $result->id);
    }

    /**
     * Test get display name attribute returns formatted string.
     */
    public function test_get_display_name_attribute_returns_formatted_string(): void
    {
        // Arrange
        $paymentMethod = PaymentMethod::factory()->create([
            'brand' => 'visa',
            'last_four' => '4242',
        ]);

        // Act
        $displayName = $paymentMethod->display_name;

        // Assert
        $this->assertEquals('Visa •••• 4242', $displayName);
    }

    /**
     * Test display name capitalizes brand correctly.
     */
    public function test_display_name_capitalizes_brand(): void
    {
        // Arrange
        $paymentMethod = PaymentMethod::factory()->create([
            'brand' => 'mastercard',
            'last_four' => '5555',
        ]);

        // Act
        $displayName = $paymentMethod->display_name;

        // Assert
        $this->assertEquals('Mastercard •••• 5555', $displayName);
    }

    /**
     * Test is expired returns false for future expiration date.
     */
    public function test_is_expired_returns_false_for_future_date(): void
    {
        // Arrange
        $paymentMethod = PaymentMethod::factory()->create([
            'exp_month' => now()->addYear()->month,
            'exp_year' => now()->addYear()->year,
        ]);

        // Act
        $result = $paymentMethod->isExpired();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test is expired returns true for past expiration date.
     */
    public function test_is_expired_returns_true_for_past_date(): void
    {
        // Arrange
        $paymentMethod = PaymentMethod::factory()->expired()->create();

        // Act
        $result = $paymentMethod->isExpired();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test is expired handles current month correctly.
     */
    public function test_is_expired_handles_current_month(): void
    {
        // Arrange - card expiring this month (should not be expired until end of month)
        $paymentMethod = PaymentMethod::factory()->create([
            'exp_month' => now()->month,
            'exp_year' => now()->year,
        ]);

        // Act
        $result = $paymentMethod->isExpired();

        // Assert
        // Card should not be expired if it's still the expiration month
        $this->assertFalse($result);
    }

    /**
     * Test is expired returns false when exp month is null.
     */
    public function test_is_expired_returns_false_when_exp_month_is_null(): void
    {
        // Arrange
        $paymentMethod = PaymentMethod::factory()->create([
            'exp_month' => null,
            'exp_year' => now()->year,
        ]);

        // Act
        $result = $paymentMethod->isExpired();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test is expired returns false when exp year is null.
     */
    public function test_is_expired_returns_false_when_exp_year_is_null(): void
    {
        // Arrange
        $paymentMethod = PaymentMethod::factory()->create([
            'exp_month' => 12,
            'exp_year' => null,
        ]);

        // Act
        $result = $paymentMethod->isExpired();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test is expired returns false when both exp month and year are null.
     */
    public function test_is_expired_returns_false_when_both_exp_values_are_null(): void
    {
        // Arrange
        $paymentMethod = PaymentMethod::factory()->create([
            'exp_month' => null,
            'exp_year' => null,
        ]);

        // Act
        $result = $paymentMethod->isExpired();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test exp month is cast to integer.
     */
    public function test_exp_month_is_cast_to_integer(): void
    {
        // Arrange
        $paymentMethod = PaymentMethod::factory()->create([
            'exp_month' => '12',
        ]);

        // Act
        $expMonth = $paymentMethod->exp_month;

        // Assert
        $this->assertIsInt($expMonth);
        $this->assertEquals(12, $expMonth);
    }

    /**
     * Test exp year is cast to integer.
     */
    public function test_exp_year_is_cast_to_integer(): void
    {
        // Arrange
        $paymentMethod = PaymentMethod::factory()->create([
            'exp_year' => '2025',
        ]);

        // Act
        $expYear = $paymentMethod->exp_year;

        // Assert
        $this->assertIsInt($expYear);
        $this->assertEquals(2025, $expYear);
    }

    /**
     * Test is default is cast to boolean.
     */
    public function test_is_default_is_cast_to_boolean(): void
    {
        // Arrange
        $paymentMethod = PaymentMethod::factory()->create([
            'is_default' => 1,
        ]);

        // Act
        $isDefault = $paymentMethod->is_default;

        // Assert
        $this->assertIsBool($isDefault);
        $this->assertTrue($isDefault);
    }

    /**
     * Test factory creates payment method with correct attributes.
     */
    public function test_factory_creates_payment_method_with_correct_attributes(): void
    {
        // Act
        $paymentMethod = PaymentMethod::factory()->create();

        // Assert
        $this->assertNotNull($paymentMethod->user_id);
        $this->assertNotNull($paymentMethod->stripe_payment_method_id);
        $this->assertNotNull($paymentMethod->type);
        $this->assertNotNull($paymentMethod->brand);
        $this->assertNotNull($paymentMethod->last_four);
        $this->assertNotNull($paymentMethod->exp_month);
        $this->assertNotNull($paymentMethod->exp_year);
        $this->assertFalse($paymentMethod->is_default);
    }

    /**
     * Test factory is default state sets is default to true.
     */
    public function test_factory_is_default_state(): void
    {
        // Act
        $paymentMethod = PaymentMethod::factory()->isDefault()->create();

        // Assert
        $this->assertTrue($paymentMethod->is_default);
    }

    /**
     * Test factory expired state creates expired payment method.
     */
    public function test_factory_expired_state(): void
    {
        // Act
        $paymentMethod = PaymentMethod::factory()->expired()->create();

        // Assert
        $this->assertTrue($paymentMethod->isExpired());
    }
}
