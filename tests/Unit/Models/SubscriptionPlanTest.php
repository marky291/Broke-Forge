<?php

namespace Tests\Unit\Models;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPlanTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test formatted price attribute returns correct euro format.
     */
    public function test_formatted_price_returns_euro_format(): void
    {
        // Arrange
        $plan = SubscriptionPlan::factory()->create([
            'amount' => 2500, // €25.00 in cents
        ]);

        // Act
        $formattedPrice = $plan->formatted_price;

        // Assert
        $this->assertEquals('€25.00', $formattedPrice);
    }

    /**
     * Test formatted price handles zero amount correctly.
     */
    public function test_formatted_price_handles_zero_amount(): void
    {
        // Arrange
        $plan = SubscriptionPlan::factory()->create([
            'amount' => 0,
        ]);

        // Act
        $formattedPrice = $plan->formatted_price;

        // Assert
        $this->assertEquals('€0.00', $formattedPrice);
    }

    /**
     * Test formatted price handles large amounts correctly.
     */
    public function test_formatted_price_handles_large_amounts(): void
    {
        // Arrange
        $plan = SubscriptionPlan::factory()->create([
            'amount' => 999999, // €9,999.99
        ]);

        // Act
        $formattedPrice = $plan->formatted_price;

        // Assert
        $this->assertEquals('€9,999.99', $formattedPrice);
    }

    /**
     * Test price per interval attribute returns correct format for monthly plan.
     */
    public function test_price_per_interval_returns_correct_format_for_monthly(): void
    {
        // Arrange
        $plan = SubscriptionPlan::factory()->monthly()->create([
            'amount' => 1999,
        ]);

        // Act
        $pricePerInterval = $plan->price_per_interval;

        // Assert
        $this->assertEquals('€19.99/month', $pricePerInterval);
    }

    /**
     * Test price per interval attribute returns correct format for yearly plan.
     */
    public function test_price_per_interval_returns_correct_format_for_yearly(): void
    {
        // Arrange
        $plan = SubscriptionPlan::factory()->yearly()->create([
            'amount' => 9999,
        ]);

        // Act
        $pricePerInterval = $plan->price_per_interval;

        // Assert
        $this->assertEquals('€99.99/year', $pricePerInterval);
    }

    /**
     * Test active scope returns only active plans.
     */
    public function test_active_scope_returns_only_active_plans(): void
    {
        // Arrange
        SubscriptionPlan::factory()->create(['is_active' => true, 'sort_order' => 2]);
        SubscriptionPlan::factory()->create(['is_active' => true, 'sort_order' => 1]);
        SubscriptionPlan::factory()->inactive()->create(['sort_order' => 3]);

        // Act
        $activePlans = SubscriptionPlan::active()->get();

        // Assert
        $this->assertCount(2, $activePlans);
        $this->assertTrue($activePlans->every(fn ($plan) => $plan->is_active === true));
    }

    /**
     * Test active scope orders by sort order.
     */
    public function test_active_scope_orders_by_sort_order(): void
    {
        // Arrange
        SubscriptionPlan::factory()->create(['is_active' => true, 'sort_order' => 3, 'name' => 'Third']);
        SubscriptionPlan::factory()->create(['is_active' => true, 'sort_order' => 1, 'name' => 'First']);
        SubscriptionPlan::factory()->create(['is_active' => true, 'sort_order' => 2, 'name' => 'Second']);

        // Act
        $activePlans = SubscriptionPlan::active()->get();

        // Assert
        $this->assertEquals('First', $activePlans->first()->name);
        $this->assertEquals('Second', $activePlans->get(1)->name);
        $this->assertEquals('Third', $activePlans->last()->name);
    }

    /**
     * Test amount is cast to integer.
     */
    public function test_amount_is_cast_to_integer(): void
    {
        // Arrange
        $plan = SubscriptionPlan::factory()->create([
            'amount' => '1999',
        ]);

        // Act
        $amount = $plan->amount;

        // Assert
        $this->assertIsInt($amount);
        $this->assertEquals(1999, $amount);
    }

    /**
     * Test server limit is cast to integer.
     */
    public function test_server_limit_is_cast_to_integer(): void
    {
        // Arrange
        $plan = SubscriptionPlan::factory()->create([
            'server_limit' => '10',
        ]);

        // Act
        $serverLimit = $plan->server_limit;

        // Assert
        $this->assertIsInt($serverLimit);
        $this->assertEquals(10, $serverLimit);
    }

    /**
     * Test interval count is cast to integer.
     */
    public function test_interval_count_is_cast_to_integer(): void
    {
        // Arrange
        $plan = SubscriptionPlan::factory()->create([
            'interval_count' => '1',
        ]);

        // Act
        $intervalCount = $plan->interval_count;

        // Assert
        $this->assertIsInt($intervalCount);
        $this->assertEquals(1, $intervalCount);
    }

    /**
     * Test sort order is cast to integer.
     */
    public function test_sort_order_is_cast_to_integer(): void
    {
        // Arrange
        $plan = SubscriptionPlan::factory()->create([
            'sort_order' => '5',
        ]);

        // Act
        $sortOrder = $plan->sort_order;

        // Assert
        $this->assertIsInt($sortOrder);
        $this->assertEquals(5, $sortOrder);
    }

    /**
     * Test features is cast to array.
     */
    public function test_features_is_cast_to_array(): void
    {
        // Arrange
        $features = ['Feature 1', 'Feature 2', 'Feature 3'];
        $plan = SubscriptionPlan::factory()->create([
            'features' => $features,
        ]);

        // Act
        $result = $plan->features;

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($features, $result);
    }

    /**
     * Test is active is cast to boolean.
     */
    public function test_is_active_is_cast_to_boolean(): void
    {
        // Arrange
        $plan = SubscriptionPlan::factory()->create([
            'is_active' => 1,
        ]);

        // Act
        $isActive = $plan->is_active;

        // Assert
        $this->assertIsBool($isActive);
        $this->assertTrue($isActive);
    }

    /**
     * Test is active false is cast to boolean.
     */
    public function test_is_active_false_is_cast_to_boolean(): void
    {
        // Arrange
        $plan = SubscriptionPlan::factory()->inactive()->create();

        // Act
        $isActive = $plan->is_active;

        // Assert
        $this->assertIsBool($isActive);
        $this->assertFalse($isActive);
    }

    /**
     * Test factory creates plan with correct attributes.
     */
    public function test_factory_creates_plan_with_correct_attributes(): void
    {
        // Act
        $plan = SubscriptionPlan::factory()->create();

        // Assert
        $this->assertNotNull($plan->stripe_product_id);
        $this->assertNotNull($plan->stripe_price_id);
        $this->assertNotNull($plan->name);
        $this->assertNotNull($plan->slug);
        $this->assertNotNull($plan->amount);
        $this->assertEquals('eur', $plan->currency);
        $this->assertNotNull($plan->interval);
        $this->assertEquals(1, $plan->interval_count);
        $this->assertNotNull($plan->server_limit);
        $this->assertIsArray($plan->features);
        $this->assertTrue($plan->is_active);
        $this->assertNotNull($plan->sort_order);
    }

    /**
     * Test factory inactive state sets is active to false.
     */
    public function test_factory_inactive_state(): void
    {
        // Act
        $plan = SubscriptionPlan::factory()->inactive()->create();

        // Assert
        $this->assertFalse($plan->is_active);
    }

    /**
     * Test factory monthly state sets interval to month.
     */
    public function test_factory_monthly_state(): void
    {
        // Act
        $plan = SubscriptionPlan::factory()->monthly()->create();

        // Assert
        $this->assertEquals('month', $plan->interval);
        $this->assertEquals(1, $plan->interval_count);
    }

    /**
     * Test factory yearly state sets interval to year.
     */
    public function test_factory_yearly_state(): void
    {
        // Act
        $plan = SubscriptionPlan::factory()->yearly()->create();

        // Assert
        $this->assertEquals('year', $plan->interval);
        $this->assertEquals(1, $plan->interval_count);
    }

    /**
     * Test formatted price and price per interval are appended to model.
     */
    public function test_formatted_price_and_price_per_interval_are_appended(): void
    {
        // Arrange
        $plan = SubscriptionPlan::factory()->create([
            'amount' => 2999,
        ]);

        // Act
        $array = $plan->toArray();

        // Assert
        $this->assertArrayHasKey('formatted_price', $array);
        $this->assertArrayHasKey('price_per_interval', $array);
        $this->assertEquals('€29.99', $array['formatted_price']);
    }

    /**
     * Test features can be empty array.
     */
    public function test_features_can_be_empty_array(): void
    {
        // Arrange
        $plan = SubscriptionPlan::factory()->create([
            'features' => [],
        ]);

        // Act
        $features = $plan->features;

        // Assert
        $this->assertIsArray($features);
        $this->assertEmpty($features);
    }

    /**
     * Test plan can have multiple features.
     */
    public function test_plan_can_have_multiple_features(): void
    {
        // Arrange
        $features = [
            'Unlimited Servers',
            '24/7 Support',
            'Automated Backups',
            'SSL Certificates',
            'Team Collaboration',
        ];
        $plan = SubscriptionPlan::factory()->create([
            'features' => $features,
        ]);

        // Act
        $result = $plan->features;

        // Assert
        $this->assertCount(5, $result);
        $this->assertEquals($features, $result);
    }
}
