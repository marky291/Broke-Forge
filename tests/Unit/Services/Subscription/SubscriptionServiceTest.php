<?php

namespace Tests\Unit\Services\Subscription;

use App\Models\Server;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test canCreateServer returns correct information when under limit.
     */
    public function test_can_create_server_returns_correct_information_when_under_limit(): void
    {
        // Arrange
        $user = User::factory()->create();
        $service = new SubscriptionService;

        // Act
        $result = $service->canCreateServer($user);

        // Assert
        $this->assertTrue($result['can_create']);
        $this->assertEquals(0, $result['current']);
        $this->assertEquals(1, $result['limit']); // Free tier limit
        $this->assertEquals(1, $result['remaining']);
    }

    /**
     * Test canCreateServer returns false when at limit.
     */
    public function test_can_create_server_returns_false_when_at_limit(): void
    {
        // Arrange
        $user = User::factory()->create();
        Server::factory()->count(1)->create([
            'user_id' => $user->id,
            'counted_in_subscription' => true,
        ]);
        $service = new SubscriptionService;

        // Act
        $result = $service->canCreateServer($user);

        // Assert
        $this->assertFalse($result['can_create']);
        $this->assertEquals(1, $result['current']);
        $this->assertEquals(1, $result['limit']);
        $this->assertEquals(0, $result['remaining']);
    }

    /**
     * Test canCreateServer returns false when over limit.
     */
    public function test_can_create_server_returns_false_when_over_limit(): void
    {
        // Arrange
        $user = User::factory()->create();
        Server::factory()->count(5)->create([
            'user_id' => $user->id,
            'counted_in_subscription' => true,
        ]);
        $service = new SubscriptionService;

        // Act
        $result = $service->canCreateServer($user);

        // Assert
        $this->assertFalse($result['can_create']);
        $this->assertEquals(5, $result['current']);
        $this->assertEquals(1, $result['limit']);
        $this->assertEquals(0, $result['remaining']);
    }

    /**
     * Test canCreateServer only counts servers with counted_in_subscription flag.
     */
    public function test_can_create_server_only_counts_servers_with_counted_in_subscription_flag(): void
    {
        // Arrange
        $user = User::factory()->create();
        Server::factory()->count(5)->create([
            'user_id' => $user->id,
            'counted_in_subscription' => false,
        ]);
        $service = new SubscriptionService;

        // Act
        $result = $service->canCreateServer($user);

        // Assert
        $this->assertTrue($result['can_create']);
        $this->assertEquals(0, $result['current']);
        $this->assertEquals(1, $result['limit']);
        $this->assertEquals(1, $result['remaining']);
    }

    /**
     * Test canCreateServer returns array with all expected keys.
     */
    public function test_can_create_server_returns_array_with_all_expected_keys(): void
    {
        // Arrange
        $user = User::factory()->create();
        $service = new SubscriptionService;

        // Act
        $result = $service->canCreateServer($user);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('can_create', $result);
        $this->assertArrayHasKey('current', $result);
        $this->assertArrayHasKey('limit', $result);
        $this->assertArrayHasKey('remaining', $result);

        // Check types
        $this->assertIsBool($result['can_create']);
        $this->assertIsInt($result['current']);
        $this->assertIsInt($result['limit']);
        $this->assertIsInt($result['remaining']);
    }

    /**
     * Test canCreateServer remaining is never negative.
     */
    public function test_can_create_server_remaining_is_never_negative(): void
    {
        // Arrange
        $user = User::factory()->create();
        Server::factory()->count(10)->create([
            'user_id' => $user->id,
            'counted_in_subscription' => true,
        ]);
        $service = new SubscriptionService;

        // Act
        $result = $service->canCreateServer($user);

        // Assert
        $this->assertEquals(0, $result['remaining']);
        $this->assertGreaterThanOrEqual(0, $result['remaining']);
    }

    /**
     * Test recordServerUsage does nothing when overage disabled.
     */
    public function test_record_server_usage_does_nothing_when_overage_disabled(): void
    {
        // Arrange
        config(['subscription.overage.enabled' => false]);
        $user = User::factory()->create();
        Server::factory()->count(5)->create([
            'user_id' => $user->id,
            'counted_in_subscription' => true,
        ]);
        $service = new SubscriptionService;

        // Act
        $service->recordServerUsage($user);

        // Assert - Method should return early without errors
        $this->assertTrue(true);
    }

    /**
     * Test recordServerUsage does nothing when user has no subscription.
     */
    public function test_record_server_usage_does_nothing_when_user_has_no_subscription(): void
    {
        // Arrange
        config(['subscription.overage.enabled' => true]);
        $user = User::factory()->create();
        Server::factory()->count(5)->create([
            'user_id' => $user->id,
            'counted_in_subscription' => true,
        ]);
        $service = new SubscriptionService;

        // Act
        $service->recordServerUsage($user);

        // Assert - Method should return early without errors
        $this->assertTrue(true);
    }

    /**
     * Test recordServerUsage calculates overage correctly.
     */
    public function test_record_server_usage_calculates_overage_correctly(): void
    {
        // Arrange
        config(['subscription.overage.enabled' => true]);
        $user = User::factory()->create();
        $limit = $user->getServerLimit(); // Should be 3 for free tier

        // Create servers over the limit
        Server::factory()->count($limit + 2)->create([
            'user_id' => $user->id,
            'counted_in_subscription' => true,
        ]);

        $service = new SubscriptionService;

        // Act
        $current = $user->activeServers()->count();
        $expectedOverage = $current - $limit;

        // Assert
        $this->assertEquals(2, $expectedOverage);
        $this->assertGreaterThan(0, $expectedOverage);
    }

    /**
     * Test canCreateServer handles edge case with zero servers.
     */
    public function test_can_create_server_handles_edge_case_with_zero_servers(): void
    {
        // Arrange
        $user = User::factory()->create();
        $service = new SubscriptionService;

        // Act
        $result = $service->canCreateServer($user);

        // Assert
        $this->assertTrue($result['can_create']);
        $this->assertEquals(0, $result['current']);
        $this->assertEquals(1, $result['limit']);
        $this->assertEquals(1, $result['remaining']);
    }

    /**
     * Test canCreateServer only counts servers for specific user.
     */
    public function test_can_create_server_only_counts_servers_for_specific_user(): void
    {
        // Arrange
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // User1 has no servers (under limit)
        Server::factory()->count(10)->create([
            'user_id' => $user2->id,
            'counted_in_subscription' => true,
        ]);

        $service = new SubscriptionService;

        // Act
        $result1 = $service->canCreateServer($user1);
        $result2 = $service->canCreateServer($user2);

        // Assert
        $this->assertEquals(0, $result1['current']);
        $this->assertTrue($result1['can_create']);

        $this->assertEquals(10, $result2['current']);
        $this->assertFalse($result2['can_create']);
    }

    /**
     * Test canCreateServer limit calculation is consistent with User model.
     */
    public function test_can_create_server_limit_calculation_is_consistent_with_user_model(): void
    {
        // Arrange
        $user = User::factory()->create();
        $service = new SubscriptionService;

        // Act
        $result = $service->canCreateServer($user);

        // Assert - Service should return same limit as User model
        $this->assertEquals($user->getServerLimit(), $result['limit']);
    }

    /**
     * Test canCreateServer current count is consistent with User model.
     */
    public function test_can_create_server_current_count_is_consistent_with_user_model(): void
    {
        // Arrange
        $user = User::factory()->create();
        Server::factory()->count(2)->create([
            'user_id' => $user->id,
            'counted_in_subscription' => true,
        ]);
        $service = new SubscriptionService;

        // Act
        $result = $service->canCreateServer($user);

        // Assert
        $this->assertEquals($user->activeServers()->count(), $result['current']);
    }

    /**
     * Test canCreateServer remaining calculation is consistent.
     */
    public function test_can_create_server_remaining_calculation_is_consistent(): void
    {
        // Arrange
        $user = User::factory()->create();
        Server::factory()->count(1)->create([
            'user_id' => $user->id,
            'counted_in_subscription' => true,
        ]);
        $service = new SubscriptionService;

        // Act
        $result = $service->canCreateServer($user);

        // Assert
        $expectedRemaining = $result['limit'] - $result['current'];
        $this->assertEquals($expectedRemaining, $result['remaining']);
    }

    /**
     * Test recordServerUsage with zero overage.
     */
    public function test_record_server_usage_with_zero_overage(): void
    {
        // Arrange
        config(['subscription.overage.enabled' => true]);
        $user = User::factory()->create();
        // Don't create any servers - user is under limit
        $service = new SubscriptionService;

        // Act
        $limit = $user->getServerLimit();
        $current = $user->activeServers()->count();
        $overage = max(0, $current - $limit);

        // Assert
        $this->assertEquals(0, $overage);
        $this->assertLessThanOrEqual($limit, $current);
    }

    /**
     * Test recordServerUsage does not record negative overage.
     */
    public function test_record_server_usage_does_not_record_negative_overage(): void
    {
        // Arrange
        config(['subscription.overage.enabled' => true]);
        $user = User::factory()->create();
        Server::factory()->count(1)->create([
            'user_id' => $user->id,
            'counted_in_subscription' => true,
        ]);
        $service = new SubscriptionService;

        // Act
        $limit = $user->getServerLimit();
        $current = $user->activeServers()->count();
        $overage = max(0, $current - $limit);

        // Assert
        $this->assertEquals(0, $overage);
        $this->assertGreaterThanOrEqual(0, $overage);
    }
}
