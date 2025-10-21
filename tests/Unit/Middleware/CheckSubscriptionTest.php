<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\CheckSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Cashier\Subscription;
use Tests\TestCase;

class CheckSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test middleware allows request when user is not authenticated.
     */
    public function test_middleware_allows_request_when_user_is_not_authenticated(): void
    {
        // Arrange
        $request = Request::create('/dashboard', 'GET');
        $middleware = new CheckSubscription;
        $nextCalled = false;

        // Act
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test middleware allows request when user has no subscription.
     */
    public function test_middleware_allows_request_when_user_has_no_subscription(): void
    {
        // Arrange
        $user = User::factory()->create();
        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);
        $middleware = new CheckSubscription;
        $nextCalled = false;

        // Act
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test middleware allows request when subscription is active.
     */
    public function test_middleware_allows_request_when_subscription_is_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        Subscription::create([
            'user_id' => $user->id,
            'type' => 'default',
            'stripe_id' => 'sub_'.bin2hex(random_bytes(10)),
            'stripe_status' => 'active',
            'stripe_price' => 'price_test',
            'quantity' => 1,
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);
        $middleware = new CheckSubscription;
        $nextCalled = false;

        // Act
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test middleware allows request when subscription is trialing.
     */
    public function test_middleware_allows_request_when_subscription_is_trialing(): void
    {
        // Arrange
        $user = User::factory()->create();
        Subscription::create([
            'user_id' => $user->id,
            'type' => 'default',
            'stripe_id' => 'sub_'.bin2hex(random_bytes(10)),
            'stripe_status' => 'trialing',
            'stripe_price' => 'price_test',
            'quantity' => 1,
            'trial_ends_at' => now()->addDays(7),
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);
        $middleware = new CheckSubscription;
        $nextCalled = false;

        // Act
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test middleware redirects when subscription is past due.
     */
    public function test_middleware_redirects_when_subscription_is_past_due(): void
    {
        // Arrange
        $user = User::factory()->create();
        Subscription::create([
            'user_id' => $user->id,
            'type' => 'default',
            'stripe_id' => 'sub_'.bin2hex(random_bytes(10)),
            'stripe_status' => 'past_due',
            'stripe_price' => 'price_test',
            'quantity' => 1,
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);
        $middleware = new CheckSubscription;

        // Act
        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('billing.index'), $response->getTargetUrl());
    }

    /**
     * Test middleware redirect includes error message.
     */
    public function test_middleware_redirect_includes_error_log(): void
    {
        // Arrange
        $user = User::factory()->create();
        Subscription::create([
            'user_id' => $user->id,
            'type' => 'default',
            'stripe_id' => 'sub_'.bin2hex(random_bytes(10)),
            'stripe_status' => 'past_due',
            'stripe_price' => 'price_test',
            'quantity' => 1,
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);
        $middleware = new CheckSubscription;

        // Act
        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $session = $response->getSession();
        $this->assertNotNull($session);
        $this->assertTrue($session->has('error'));
        $this->assertStringContainsString('past due', $session->get('error'));
        $this->assertStringContainsString('payment method', $session->get('error'));
    }

    /**
     * Test middleware allows request when subscription is cancelled but not past due.
     */
    public function test_middleware_allows_request_when_subscription_is_cancelled_but_not_past_due(): void
    {
        // Arrange
        $user = User::factory()->create();
        Subscription::create([
            'user_id' => $user->id,
            'type' => 'default',
            'stripe_id' => 'sub_'.bin2hex(random_bytes(10)),
            'stripe_status' => 'canceled',
            'stripe_price' => 'price_test',
            'quantity' => 1,
            'ends_at' => now()->addDays(7),
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);
        $middleware = new CheckSubscription;
        $nextCalled = false;

        // Act
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test middleware only checks default subscription.
     */
    public function test_middleware_only_checks_default_subscription(): void
    {
        // Arrange
        $user = User::factory()->create();
        // Create a past_due subscription with different type (not 'default')
        Subscription::create([
            'user_id' => $user->id,
            'type' => 'premium',
            'stripe_id' => 'sub_'.bin2hex(random_bytes(10)),
            'stripe_status' => 'past_due',
            'stripe_price' => 'price_test',
            'quantity' => 1,
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);
        $middleware = new CheckSubscription;
        $nextCalled = false;

        // Act
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        // Assert - Should pass because 'default' subscription doesn't exist
        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test middleware handles multiple subscriptions correctly.
     */
    public function test_middleware_handles_multiple_subscriptions_correctly(): void
    {
        // Arrange
        $user = User::factory()->create();
        // Create past_due default subscription
        Subscription::create([
            'user_id' => $user->id,
            'type' => 'default',
            'stripe_id' => 'sub_'.bin2hex(random_bytes(10)),
            'stripe_status' => 'past_due',
            'stripe_price' => 'price_test',
            'quantity' => 1,
        ]);
        // Create active premium subscription
        Subscription::create([
            'user_id' => $user->id,
            'type' => 'premium',
            'stripe_id' => 'sub_'.bin2hex(random_bytes(10)),
            'stripe_status' => 'active',
            'stripe_price' => 'price_test',
            'quantity' => 1,
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);
        $middleware = new CheckSubscription;

        // Act
        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        // Assert - Should redirect because default subscription is past_due
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('billing.index'), $response->getTargetUrl());
    }

    /**
     * Test middleware does not affect other users.
     */
    public function test_middleware_does_not_affect_other_users(): void
    {
        // Arrange
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // User2 has past_due subscription
        Subscription::create([
            'user_id' => $user2->id,
            'type' => 'default',
            'stripe_id' => 'sub_'.bin2hex(random_bytes(10)),
            'stripe_status' => 'past_due',
            'stripe_price' => 'price_test',
            'quantity' => 1,
        ]);

        // User1 makes request (no subscription)
        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user1);
        $middleware = new CheckSubscription;
        $nextCalled = false;

        // Act
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        // Assert - User1 should not be affected by User2's past_due subscription
        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
