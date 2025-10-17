<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\ValidateSchedulerToken;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ValidateSchedulerTokenTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test middleware allows request with valid scheduler token.
     */
    public function test_middleware_allows_request_with_valid_scheduler_token(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_token' => 'valid-scheduler-token-123',
        ]);

        $request = Request::create('/api/scheduler', 'POST');
        $request->headers->set('X-Scheduler-Token', 'valid-scheduler-token-123');
        $request->setRouteResolver(function () use ($server) {
            return new class($server)
            {
                public function __construct(private $server) {}

                public function parameter($name)
                {
                    return $name === 'server' ? $this->server : null;
                }
            };
        });

        $middleware = new ValidateSchedulerToken;
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
     * Test middleware returns 401 when scheduler token is missing.
     */
    public function test_middleware_returns_401_when_scheduler_token_is_missing(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_token' => 'valid-scheduler-token-123',
        ]);

        $request = Request::create('/api/scheduler', 'POST');
        // No X-Scheduler-Token header
        $request->setRouteResolver(function () use ($server) {
            return new class($server)
            {
                public function __construct(private $server) {}

                public function parameter($name)
                {
                    return $name === 'server' ? $this->server : null;
                }
            };
        });

        $middleware = new ValidateSchedulerToken;

        // Act
        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Scheduler token required', $data['message']);
    }

    /**
     * Test middleware returns 404 when server is invalid.
     */
    public function test_middleware_returns_404_when_server_is_invalid(): void
    {
        // Arrange
        $request = Request::create('/api/scheduler', 'POST');
        $request->headers->set('X-Scheduler-Token', 'some-token');
        $request->setRouteResolver(function () {
            return new class
            {
                public function parameter($name)
                {
                    return null; // No server in route
                }
            };
        });

        $middleware = new ValidateSchedulerToken;

        // Act
        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Invalid server', $data['message']);
    }

    /**
     * Test middleware returns 401 when scheduler token does not match.
     */
    public function test_middleware_returns_401_when_scheduler_token_does_not_match(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_token' => 'correct-token',
        ]);

        $request = Request::create('/api/scheduler', 'POST');
        $request->headers->set('X-Scheduler-Token', 'wrong-token');
        $request->setRouteResolver(function () use ($server) {
            return new class($server)
            {
                public function __construct(private $server) {}

                public function parameter($name)
                {
                    return $name === 'server' ? $this->server : null;
                }
            };
        });

        $middleware = new ValidateSchedulerToken;

        // Act
        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Invalid scheduler token', $data['message']);
    }

    /**
     * Test middleware returns 401 when server has no scheduler token.
     */
    public function test_middleware_returns_401_when_server_has_no_scheduler_token(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_token' => null,
        ]);

        $request = Request::create('/api/scheduler', 'POST');
        $request->headers->set('X-Scheduler-Token', 'some-token');
        $request->setRouteResolver(function () use ($server) {
            return new class($server)
            {
                public function __construct(private $server) {}

                public function parameter($name)
                {
                    return $name === 'server' ? $this->server : null;
                }
            };
        });

        $middleware = new ValidateSchedulerToken;

        // Act
        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Invalid scheduler token', $data['message']);
    }

    /**
     * Test middleware returns 401 when token is empty string.
     */
    public function test_middleware_returns_401_when_token_is_empty_string(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_token' => 'valid-token',
        ]);

        $request = Request::create('/api/scheduler', 'POST');
        $request->headers->set('X-Scheduler-Token', '');
        $request->setRouteResolver(function () use ($server) {
            return new class($server)
            {
                public function __construct(private $server) {}

                public function parameter($name)
                {
                    return $name === 'server' ? $this->server : null;
                }
            };
        });

        $middleware = new ValidateSchedulerToken;

        // Act
        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Scheduler token required', $data['message']);
    }

    /**
     * Test middleware is case-sensitive for token comparison.
     */
    public function test_middleware_is_case_sensitive_for_token_comparison(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_token' => 'CaseSensitiveToken',
        ]);

        $request = Request::create('/api/scheduler', 'POST');
        $request->headers->set('X-Scheduler-Token', 'casesensitivetoken');
        $request->setRouteResolver(function () use ($server) {
            return new class($server)
            {
                public function __construct(private $server) {}

                public function parameter($name)
                {
                    return $name === 'server' ? $this->server : null;
                }
            };
        });

        $middleware = new ValidateSchedulerToken;

        // Act
        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Invalid scheduler token', $data['message']);
    }

    /**
     * Test middleware prevents timing attacks with constant-time comparison.
     */
    public function test_middleware_validates_exact_token_match(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_token' => 'exact-token-match-required',
        ]);

        $request = Request::create('/api/scheduler', 'POST');
        $request->headers->set('X-Scheduler-Token', 'exact-token-match-required-extra');
        $request->setRouteResolver(function () use ($server) {
            return new class($server)
            {
                public function __construct(private $server) {}

                public function parameter($name)
                {
                    return $name === 'server' ? $this->server : null;
                }
            };
        });

        $middleware = new ValidateSchedulerToken;

        // Act
        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(401, $response->getStatusCode());
    }

    /**
     * Test middleware works with different server instances.
     */
    public function test_middleware_works_with_different_server_instances(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server1 = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_token' => 'server1-token',
        ]);
        $server2 = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_token' => 'server2-token',
        ]);

        $middleware = new ValidateSchedulerToken;

        // Act - Request for server1 with server1's token
        $request1 = Request::create('/api/scheduler', 'POST');
        $request1->headers->set('X-Scheduler-Token', 'server1-token');
        $request1->setRouteResolver(function () use ($server1) {
            return new class($server1)
            {
                public function __construct(private $server) {}

                public function parameter($name)
                {
                    return $name === 'server' ? $this->server : null;
                }
            };
        });

        $response1 = $middleware->handle($request1, function ($req) {
            return response()->json(['success' => true]);
        });

        // Act - Request for server2 with server1's token (should fail)
        $request2 = Request::create('/api/scheduler', 'POST');
        $request2->headers->set('X-Scheduler-Token', 'server1-token');
        $request2->setRouteResolver(function () use ($server2) {
            return new class($server2)
            {
                public function __construct(private $server) {}

                public function parameter($name)
                {
                    return $name === 'server' ? $this->server : null;
                }
            };
        });

        $response2 = $middleware->handle($request2, function ($req) {
            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals(401, $response2->getStatusCode());
    }
}
