<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\ValidateMonitoringToken;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ValidateMonitoringTokenTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test middleware passes when token matches server monitoring token.
     */
    public function test_middleware_passes_when_token_matches(): void
    {
        // Arrange
        $server = Server::factory()->create([
            'monitoring_token' => 'valid-token-123',
        ]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Monitoring-Token', 'valid-token-123');
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $middleware = new ValidateMonitoringToken;
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
     * Test middleware returns 401 when token is missing.
     */
    public function test_middleware_returns_401_when_token_is_missing(): void
    {
        // Arrange
        $server = Server::factory()->create([
            'monitoring_token' => 'valid-token-123',
        ]);

        $request = Request::create('/test', 'GET');
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $middleware = new ValidateMonitoringToken;
        $nextCalled = false;

        // Act
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertFalse($nextCalled);
        $this->assertEquals(401, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Monitoring token required', $responseData['message']);
    }

    /**
     * Test middleware returns 401 when token is empty string.
     */
    public function test_middleware_returns_401_when_token_is_empty_string(): void
    {
        // Arrange
        $server = Server::factory()->create([
            'monitoring_token' => 'valid-token-123',
        ]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Monitoring-Token', '');
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $middleware = new ValidateMonitoringToken;
        $nextCalled = false;

        // Act
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertFalse($nextCalled);
        $this->assertEquals(401, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Monitoring token required', $responseData['message']);
    }

    /**
     * Test middleware returns 404 when server is not found.
     */
    public function test_middleware_returns_404_when_server_is_not_found(): void
    {
        // Arrange
        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Monitoring-Token', 'some-token');
        $request->setRouteResolver(fn () => new class
        {
            public function parameter($name)
            {
                return null;
            }
        });

        $middleware = new ValidateMonitoringToken;
        $nextCalled = false;

        // Act
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertFalse($nextCalled);
        $this->assertEquals(404, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Invalid server', $responseData['message']);
    }

    /**
     * Test middleware returns 404 when server is not a Server instance.
     */
    public function test_middleware_returns_404_when_server_is_not_server_instance(): void
    {
        // Arrange
        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Monitoring-Token', 'some-token');
        $request->setRouteResolver(fn () => new class
        {
            public function parameter($name)
            {
                return 'not-a-server';
            }
        });

        $middleware = new ValidateMonitoringToken;
        $nextCalled = false;

        // Act
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertFalse($nextCalled);
        $this->assertEquals(404, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Invalid server', $responseData['message']);
    }

    /**
     * Test middleware returns 401 when server monitoring_token is null.
     */
    public function test_middleware_returns_401_when_server_monitoring_token_is_null(): void
    {
        // Arrange
        $server = Server::factory()->create([
            'monitoring_token' => null,
        ]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Monitoring-Token', 'some-token');
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $middleware = new ValidateMonitoringToken;
        $nextCalled = false;

        // Act
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertFalse($nextCalled);
        $this->assertEquals(401, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Invalid monitoring token', $responseData['message']);
    }

    /**
     * Test middleware returns 401 when token does not match.
     */
    public function test_middleware_returns_401_when_token_does_not_match(): void
    {
        // Arrange
        $server = Server::factory()->create([
            'monitoring_token' => 'valid-token-123',
        ]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Monitoring-Token', 'wrong-token');
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $middleware = new ValidateMonitoringToken;
        $nextCalled = false;

        // Act
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertFalse($nextCalled);
        $this->assertEquals(401, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Invalid monitoring token', $responseData['message']);
    }

    /**
     * Test middleware passes with various valid token formats.
     */
    public function test_middleware_passes_with_various_valid_token_formats(): void
    {
        // Arrange
        $middleware = new ValidateMonitoringToken;

        $validTokens = [
            'simple-token',
            'token-with-dashes',
            'token_with_underscores',
            'TokenWithCaps',
            'token123',
            '123token',
            'very-long-token-with-many-characters-'.str_repeat('a', 50),
            'token.with.dots',
        ];

        foreach ($validTokens as $token) {
            $server = Server::factory()->create([
                'monitoring_token' => $token,
            ]);

            $request = Request::create('/test', 'GET');
            $request->headers->set('X-Monitoring-Token', $token);
            $request->setRouteResolver(fn () => new class($server)
            {
                public function __construct(private $server) {}

                public function parameter($name)
                {
                    return $name === 'server' ? $this->server : null;
                }
            });

            $nextCalled = false;

            // Act
            $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
                $nextCalled = true;

                return response()->json(['success' => true]);
            });

            // Assert
            $this->assertTrue($nextCalled, "Token '{$token}' should be valid");
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    /**
     * Test middleware is case sensitive for tokens.
     */
    public function test_middleware_is_case_sensitive_for_tokens(): void
    {
        // Arrange
        $server = Server::factory()->create([
            'monitoring_token' => 'CaseSensitiveToken',
        ]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Monitoring-Token', 'casesensitivetoken');
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $middleware = new ValidateMonitoringToken;
        $nextCalled = false;

        // Act
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertFalse($nextCalled);
        $this->assertEquals(401, $response->getStatusCode());
    }

    /**
     * Test middleware handles multiple servers with different tokens.
     */
    public function test_middleware_handles_multiple_servers_with_different_tokens(): void
    {
        // Arrange
        $server1 = Server::factory()->create([
            'monitoring_token' => 'server1-token',
        ]);

        $server2 = Server::factory()->create([
            'monitoring_token' => 'server2-token',
        ]);

        $middleware = new ValidateMonitoringToken;

        // Test server 1
        $request1 = Request::create('/test', 'GET');
        $request1->headers->set('X-Monitoring-Token', 'server1-token');
        $request1->setRouteResolver(fn () => new class($server1)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $nextCalled1 = false;
        $response1 = $middleware->handle($request1, function ($req) use (&$nextCalled1) {
            $nextCalled1 = true;

            return response()->json(['success' => true]);
        });

        // Test server 2
        $request2 = Request::create('/test', 'GET');
        $request2->headers->set('X-Monitoring-Token', 'server2-token');
        $request2->setRouteResolver(fn () => new class($server2)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $nextCalled2 = false;
        $response2 = $middleware->handle($request2, function ($req) use (&$nextCalled2) {
            $nextCalled2 = true;

            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertTrue($nextCalled1);
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertTrue($nextCalled2);
        $this->assertEquals(200, $response2->getStatusCode());
    }

    /**
     * Test middleware rejects wrong token for different server.
     */
    public function test_middleware_rejects_wrong_token_for_different_server(): void
    {
        // Arrange
        Server::factory()->create([
            'monitoring_token' => 'server1-token',
        ]);

        $server2 = Server::factory()->create([
            'monitoring_token' => 'server2-token',
        ]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Monitoring-Token', 'server1-token');
        $request->setRouteResolver(fn () => new class($server2)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $middleware = new ValidateMonitoringToken;
        $nextCalled = false;

        // Act
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        // Assert
        $this->assertFalse($nextCalled);
        $this->assertEquals(401, $response->getStatusCode());
    }
}
