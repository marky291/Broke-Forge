<?php

namespace Tests\Unit\Models;

use App\Models\BillingEvent;
use App\Models\PaymentMethod;
use App\Models\Server;
use App\Models\SourceProvider;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Subscription;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user has many servers.
     */
    public function test_has_many_servers(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server1 = Server::factory()->create(['user_id' => $user->id]);
        $server2 = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $servers = $user->servers;

        // Assert
        $this->assertCount(2, $servers);
        $this->assertTrue($servers->contains($server1));
        $this->assertTrue($servers->contains($server2));
    }

    /**
     * Test user has many source providers.
     */
    public function test_has_many_source_providers(): void
    {
        // Arrange
        $user = User::factory()->create();
        $provider1 = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
        ]);
        $provider2 = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'gitlab',
        ]);

        // Act
        $providers = $user->sourceProviders;

        // Assert
        $this->assertCount(2, $providers);
        $this->assertTrue($providers->contains($provider1));
        $this->assertTrue($providers->contains($provider2));
    }

    /**
     * Test user has many payment methods.
     */
    public function test_has_many_payment_methods(): void
    {
        // Arrange
        $user = User::factory()->create();
        $paymentMethod1 = PaymentMethod::factory()->create(['user_id' => $user->id]);
        $paymentMethod2 = PaymentMethod::factory()->create(['user_id' => $user->id]);

        // Act
        $paymentMethods = $user->paymentMethods;

        // Assert
        $this->assertCount(2, $paymentMethods);
        $this->assertTrue($paymentMethods->contains($paymentMethod1));
        $this->assertTrue($paymentMethods->contains($paymentMethod2));
    }

    /**
     * Test user has many billing events.
     */
    public function test_has_many_billing_events(): void
    {
        // Arrange
        $user = User::factory()->create();
        $event1 = BillingEvent::factory()->create(['user_id' => $user->id]);
        $event2 = BillingEvent::factory()->create(['user_id' => $user->id]);

        // Act
        $events = $user->billingEvents;

        // Assert
        $this->assertCount(2, $events);
        $this->assertTrue($events->contains($event1));
        $this->assertTrue($events->contains($event2));
    }

    /**
     * Test active servers returns only servers counted in subscription.
     */
    public function test_active_servers_returns_only_counted_servers(): void
    {
        // Arrange
        $user = User::factory()->create();
        $activeServer1 = Server::factory()->create([
            'user_id' => $user->id,
            'counted_in_subscription' => true,
        ]);
        $activeServer2 = Server::factory()->create([
            'user_id' => $user->id,
            'counted_in_subscription' => true,
        ]);
        Server::factory()->create([
            'user_id' => $user->id,
            'counted_in_subscription' => false,
        ]);

        // Act
        $activeServers = $user->activeServers;

        // Assert
        $this->assertCount(2, $activeServers);
        $this->assertTrue($activeServers->contains($activeServer1));
        $this->assertTrue($activeServers->contains($activeServer2));
    }

    /**
     * Test github provider returns github source provider.
     */
    public function test_github_provider_returns_github_source_provider(): void
    {
        // Arrange
        $user = User::factory()->create();
        $githubProvider = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
        ]);
        SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'gitlab',
        ]);

        // Act
        $result = $user->githubProvider();

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($githubProvider->id, $result->id);
        $this->assertEquals('github', $result->provider);
    }

    /**
     * Test github provider returns null when not connected.
     */
    public function test_github_provider_returns_null_when_not_connected(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $result = $user->githubProvider();

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test has github connected returns true when connected.
     */
    public function test_has_github_connected_returns_true_when_connected(): void
    {
        // Arrange
        $user = User::factory()->create();
        SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
        ]);

        // Act
        $result = $user->hasGitHubConnected();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test has github connected returns false when not connected.
     */
    public function test_has_github_connected_returns_false_when_not_connected(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $result = $user->hasGitHubConnected();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test can create server returns true when under limit.
     */
    public function test_can_create_server_returns_true_when_under_limit(): void
    {
        // Arrange
        config(['subscription.plans.free.server_limit' => 2]);
        $user = User::factory()->create();
        Server::factory()->create([
            'user_id' => $user->id,
            'counted_in_subscription' => true,
        ]);

        // Act
        $result = $user->canCreateServer();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test can create server returns false when at limit.
     */
    public function test_can_create_server_returns_false_when_at_limit(): void
    {
        // Arrange
        config(['subscription.plans.free.server_limit' => 1]);
        $user = User::factory()->create();
        Server::factory()->create([
            'user_id' => $user->id,
            'counted_in_subscription' => true,
        ]);

        // Act
        $result = $user->canCreateServer();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test get server limit returns free limit when not subscribed.
     */
    public function test_get_server_limit_returns_free_limit_when_not_subscribed(): void
    {
        // Arrange
        config(['subscription.plans.free.server_limit' => 1]);
        $user = User::factory()->create();

        // Act
        $limit = $user->getServerLimit();

        // Assert
        $this->assertEquals(1, $limit);
    }

    /**
     * Test get remaining server slots calculates correctly.
     */
    public function test_get_remaining_server_slots_calculates_correctly(): void
    {
        // Arrange
        config(['subscription.plans.free.server_limit' => 3]);
        $user = User::factory()->create();
        Server::factory()->create([
            'user_id' => $user->id,
            'counted_in_subscription' => true,
        ]);

        // Act
        $remaining = $user->getRemainingServerSlots();

        // Assert
        $this->assertEquals(2, $remaining);
    }

    /**
     * Test get remaining server slots returns zero when over limit.
     */
    public function test_get_remaining_server_slots_returns_zero_when_over_limit(): void
    {
        // Arrange
        config(['subscription.plans.free.server_limit' => 1]);
        $user = User::factory()->create();
        Server::factory()->count(2)->create([
            'user_id' => $user->id,
            'counted_in_subscription' => true,
        ]);

        // Act
        $remaining = $user->getRemainingServerSlots();

        // Assert
        $this->assertEquals(0, $remaining);
    }

    /**
     * Test get current plan slug returns free when not subscribed.
     */
    public function test_get_current_plan_slug_returns_free_when_not_subscribed(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $slug = $user->getCurrentPlanSlug();

        // Assert
        $this->assertEquals('free', $slug);
    }

    /**
     * Test get subscription status returns free when not subscribed.
     */
    public function test_get_subscription_status_returns_free_when_not_subscribed(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $status = $user->getSubscriptionStatus();

        // Assert
        $this->assertEquals('free', $status);
    }

    /**
     * Test get current plan name returns free when not subscribed.
     */
    public function test_get_current_plan_name_returns_free_when_not_subscribed(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $planName = $user->getCurrentPlanName();

        // Assert
        $this->assertEquals('Free', $planName);
    }

    /**
     * Test email verified at is cast to datetime.
     */
    public function test_email_verified_at_is_cast_to_datetime(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email_verified_at' => '2024-01-15 10:00:00',
        ]);

        // Act
        $emailVerifiedAt = $user->email_verified_at;

        // Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $emailVerifiedAt);
    }

    /**
     * Test password is cast to hashed.
     */
    public function test_password_is_hashed(): void
    {
        // Arrange
        $plainPassword = 'my-secret-password';
        $user = User::factory()->create([
            'password' => $plainPassword,
        ]);

        // Act
        $hashedPassword = $user->password;

        // Assert
        $this->assertNotEquals($plainPassword, $hashedPassword);
        $this->assertTrue(\Hash::check($plainPassword, $hashedPassword));
    }

    /**
     * Test factory creates user with correct attributes.
     */
    public function test_factory_creates_user_with_correct_attributes(): void
    {
        // Act
        $user = User::factory()->create();

        // Assert
        $this->assertNotNull($user->name);
        $this->assertNotNull($user->email);
        $this->assertNotNull($user->password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->remember_token);
    }

    /**
     * Test factory unverified state creates unverified user.
     */
    public function test_factory_unverified_state(): void
    {
        // Act
        $user = User::factory()->unverified()->create();

        // Assert
        $this->assertNull($user->email_verified_at);
    }

    /**
     * Test password is hidden from serialization.
     */
    public function test_password_is_hidden_from_serialization(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $array = $user->toArray();

        // Assert
        $this->assertArrayNotHasKey('password', $array);
    }

    /**
     * Test remember token is hidden from serialization.
     */
    public function test_remember_token_is_hidden_from_serialization(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $array = $user->toArray();

        // Assert
        $this->assertArrayNotHasKey('remember_token', $array);
    }

    /**
     * Test user can be created with all fillable attributes.
     */
    public function test_can_create_with_all_fillable_attributes(): void
    {
        // Arrange
        $attributes = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        // Act
        $user = User::create($attributes);

        // Assert
        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        $this->assertEquals($attributes['name'], $user->name);
        $this->assertEquals($attributes['email'], $user->email);
    }

    /**
     * Test active servers only counts servers with counted in subscription true.
     */
    public function test_active_servers_only_counts_servers_with_flag(): void
    {
        // Arrange
        $user = User::factory()->create();
        Server::factory()->count(3)->create([
            'user_id' => $user->id,
            'counted_in_subscription' => true,
        ]);
        Server::factory()->count(2)->create([
            'user_id' => $user->id,
            'counted_in_subscription' => false,
        ]);

        // Act
        $count = $user->activeServers()->count();

        // Assert
        $this->assertEquals(3, $count);
    }

    /**
     * Test get current plan name returns plan name when subscribed.
     */
    public function test_get_current_plan_name_returns_plan_name_when_subscribed(): void
    {
        // Arrange
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create([
            'name' => 'Professional',
            'stripe_price_id' => 'price_test_123',
        ]);

        // Create mock subscription
        $subscription = new Subscription;
        $subscription->stripe_price = 'price_test_123';
        $subscription->type = 'default';
        $subscription->stripe_id = 'sub_'.fake()->uuid();
        $subscription->stripe_status = 'active';
        $subscription->user_id = $user->id;
        $subscription->save();

        // Act
        $planName = $user->getCurrentPlanName();

        // Assert
        $this->assertEquals('Professional', $planName);
    }
}
