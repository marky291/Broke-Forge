<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\CheckServerLimit;
use App\Models\Server;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

class CheckServerLimitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test middleware allows request when user is not authenticated.
     */
    public function test_middleware_allows_request_when_user_is_not_authenticated(): void
    {
        // Arrange
        $request = Request::create('/servers/create', 'GET');
        $service = new SubscriptionService;
        $middleware = new CheckServerLimit($service);
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
     * Test middleware allows request when user is under limit.
     */
    public function test_middleware_allows_request_when_user_is_under_limit(): void
    {
        // Arrange
        $user = User::factory()->create();
        $request = Request::create('/servers/create', 'GET');
        $request->setUserResolver(fn () => $user);
        $service = new SubscriptionService;
        $middleware = new CheckServerLimit($service);
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
     * Test middleware redirects when user is at limit.
     */
    public function test_middleware_redirects_when_user_is_at_limit(): void
    {
        // Arrange
        $user = User::factory()->create();
        $limit = $user->getServerLimit(); // Should be 1 for free tier

        // Create servers up to the limit
        Server::factory()->count($limit)->create([
            'user_id' => $user->id,
            'counted_in_subscription' => true,
        ]);

        $request = Request::create('/servers/create', 'GET');
        $request->setUserResolver(fn () => $user);
        $service = new SubscriptionService;
        $middleware = new CheckServerLimit($service);

        // Act
        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    /**
     * Test middleware redirects when user is over limit.
     */
    public function test_middleware_redirects_when_user_is_over_limit(): void
    {
        // Arrange
        $user = User::factory()->create();
        $limit = $user->getServerLimit();

        // Create more servers than the limit
        Server::factory()->count($limit + 3)->create([
            'user_id' => $user->id,
            'counted_in_subscription' => true,
        ]);

        $request = Request::create('/servers/create', 'GET');
        $request->setUserResolver(fn () => $user);
        $service = new SubscriptionService;
        $middleware = new CheckServerLimit($service);

        // Act
        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    /**
     * Test middleware error message includes limit number.
     */
    public function test_middleware_error_message_includes_limit_number(): void
    {
        // Arrange
        $user = User::factory()->create();
        $limit = $user->getServerLimit();

        Server::factory()->count($limit)->create([
            'user_id' => $user->id,
            'counted_in_subscription' => true,
        ]);

        $request = Request::create('/servers/create', 'GET');
        $request->setUserResolver(fn () => $user);
        $service = new SubscriptionService;
        $middleware = new CheckServerLimit($service);

        // Act
        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $session = $response->getSession();
        $this->assertNotNull($session);
        $this->assertTrue($session->has('error'));
        $this->assertStringContainsString((string) $limit, $session->get('error'));
        $this->assertStringContainsString('server limit', $session->get('error'));
        $this->assertStringContainsString('upgrade', $session->get('error'));
    }

    /**
     * Test middleware only counts servers with counted_in_subscription flag.
     */
    public function test_middleware_only_counts_servers_with_counted_in_subscription_flag(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Create many servers but not counted in subscription
        Server::factory()->count(10)->create([
            'user_id' => $user->id,
            'counted_in_subscription' => false,
        ]);

        $request = Request::create('/servers/create', 'GET');
        $request->setUserResolver(fn () => $user);
        $service = new SubscriptionService;
        $middleware = new CheckServerLimit($service);
        $nextCalled = false;

        // Act
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        // Assert - Should pass because counted servers = 0
        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test middleware isolates users from each other.
     */
    public function test_middleware_isolates_users_from_each_other(): void
    {
        // Arrange
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // User2 is at limit
        Server::factory()->count(10)->create([
            'user_id' => $user2->id,
            'counted_in_subscription' => true,
        ]);

        // User1 makes request (no servers)
        $request = Request::create('/servers/create', 'GET');
        $request->setUserResolver(fn () => $user1);
        $service = new SubscriptionService;
        $middleware = new CheckServerLimit($service);
        $nextCalled = false;

        // Act
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        // Assert - User1 should not be affected by User2's servers
        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test middleware allows request when exactly one server under limit.
     */
    public function test_middleware_allows_request_when_exactly_one_server_under_limit(): void
    {
        // Arrange
        $user = User::factory()->create();
        $limit = $user->getServerLimit();

        // Create limit - 1 servers
        if ($limit > 1) {
            Server::factory()->count($limit - 1)->create([
                'user_id' => $user->id,
                'counted_in_subscription' => true,
            ]);
        }

        $request = Request::create('/servers/create', 'GET');
        $request->setUserResolver(fn () => $user);
        $service = new SubscriptionService;
        $middleware = new CheckServerLimit($service);
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
     * Test middleware uses SubscriptionService correctly.
     */
    public function test_middleware_uses_subscription_service_correctly(): void
    {
        // Arrange
        $user = User::factory()->create();
        $service = new SubscriptionService;

        // Get expected result from service
        $expected = $service->canCreateServer($user);

        $request = Request::create('/servers/create', 'GET');
        $request->setUserResolver(fn () => $user);
        $middleware = new CheckServerLimit($service);

        // Act
        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        // Assert - If can_create is true, should pass; otherwise redirect
        if ($expected['can_create']) {
            $this->assertEquals(200, $response->getStatusCode());
        } else {
            $this->assertInstanceOf(RedirectResponse::class, $response);
        }
    }

    /**
     * Test middleware works with mix of counted and uncounted servers.
     */
    public function test_middleware_works_with_mix_of_counted_and_uncounted_servers(): void
    {
        // Arrange
        $user = User::factory()->create();
        $limit = $user->getServerLimit();

        // Create servers at limit with counted flag
        Server::factory()->count($limit)->create([
            'user_id' => $user->id,
            'counted_in_subscription' => true,
        ]);

        // Create additional servers without counted flag
        Server::factory()->count(5)->create([
            'user_id' => $user->id,
            'counted_in_subscription' => false,
        ]);

        $request = Request::create('/servers/create', 'GET');
        $request->setUserResolver(fn () => $user);
        $service = new SubscriptionService;
        $middleware = new CheckServerLimit($service);

        // Act
        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        // Assert - Should redirect because counted servers = limit
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }
}
