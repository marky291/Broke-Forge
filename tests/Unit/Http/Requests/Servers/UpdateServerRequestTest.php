<?php

namespace Tests\Unit\Http\Requests\Servers;

use App\Http\Requests\Servers\UpdateServerRequest;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateServerRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test validation passes with all valid data.
     */
    public function test_validation_passes_with_all_valid_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'public_ip' => '192.168.1.100',
            'ssh_port' => 22,
        ]);

        $data = [
            'vanity_name' => 'Updated Server',
            'public_ip' => '192.168.1.101',
            'ssh_port' => 2222,
            'private_ip' => '10.0.0.1',
        ];

        $request = new UpdateServerRequest;
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with minimal required data.
     */
    public function test_validation_passes_with_minimal_required_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
        ]);

        $data = [
            'vanity_name' => 'Server',
            'public_ip' => '192.168.1.1',
            'ssh_port' => 22,
        ];

        $request = new UpdateServerRequest;
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when vanity_name is missing.
     */
    public function test_validation_fails_when_vanity_name_is_missing(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
        ]);

        $data = [
            'public_ip' => '192.168.1.1',
            'ssh_port' => 22,
        ];

        $request = new UpdateServerRequest;
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('vanity_name', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when vanity_name exceeds max length.
     */
    public function test_validation_fails_when_vanity_name_exceeds_max_length(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
        ]);

        $data = [
            'vanity_name' => str_repeat('a', 101),
            'public_ip' => '192.168.1.1',
            'ssh_port' => 22,
        ];

        $request = new UpdateServerRequest;
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('vanity_name', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with maximum valid vanity_name length.
     */
    public function test_validation_passes_with_maximum_valid_vanity_name_length(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
        ]);

        $data = [
            'vanity_name' => str_repeat('a', 100),
            'public_ip' => '192.168.1.1',
            'ssh_port' => 22,
        ];

        $request = new UpdateServerRequest;
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when public_ip is missing.
     */
    public function test_validation_fails_when_public_ip_is_missing(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
        ]);

        $data = [
            'vanity_name' => 'Server',
            'ssh_port' => 22,
        ];

        $request = new UpdateServerRequest;
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('public_ip', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when public_ip is invalid format.
     */
    public function test_validation_fails_when_public_ip_is_invalid_format(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
        ]);

        $data = [
            'vanity_name' => 'Server',
            'public_ip' => 'not-an-ip',
            'ssh_port' => 22,
        ];

        $request = new UpdateServerRequest;
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('public_ip', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with valid IPv4 addresses.
     */
    public function test_validation_passes_with_valid_ipv4_addresses(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
        ]);

        $request = new UpdateServerRequest;
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $validIPs = [
            '192.168.1.1',
            '10.0.0.1',
            '172.16.0.1',
            '8.8.8.8',
        ];

        foreach ($validIPs as $ip) {
            $data = [
                'vanity_name' => 'Server',
                'public_ip' => $ip,
                'ssh_port' => 22,
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "IP '{$ip}' should be valid");
        }
    }

    /**
     * Test validation passes when private_ip is not provided.
     */
    public function test_validation_passes_when_private_ip_is_not_provided(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
        ]);

        $data = [
            'vanity_name' => 'Server',
            'public_ip' => '192.168.1.1',
            'ssh_port' => 22,
        ];

        $request = new UpdateServerRequest;
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when private_ip is invalid format.
     */
    public function test_validation_fails_when_private_ip_is_invalid_format(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
        ]);

        $data = [
            'vanity_name' => 'Server',
            'public_ip' => '192.168.1.1',
            'ssh_port' => 22,
            'private_ip' => 'invalid-ip',
        ];

        $request = new UpdateServerRequest;
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('private_ip', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when ssh_port is missing.
     */
    public function test_validation_fails_when_ssh_port_is_missing(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
        ]);

        $data = [
            'vanity_name' => 'Server',
            'public_ip' => '192.168.1.1',
        ];

        $request = new UpdateServerRequest;
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('ssh_port', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when ssh_port is below minimum.
     */
    public function test_validation_fails_when_ssh_port_is_below_minimum(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
        ]);

        $data = [
            'vanity_name' => 'Server',
            'public_ip' => '192.168.1.1',
            'ssh_port' => 0,
        ];

        $request = new UpdateServerRequest;
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('ssh_port', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when ssh_port exceeds maximum.
     */
    public function test_validation_fails_when_ssh_port_exceeds_maximum(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
        ]);

        $data = [
            'vanity_name' => 'Server',
            'public_ip' => '192.168.1.1',
            'ssh_port' => 65536,
        ];

        $request = new UpdateServerRequest;
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('ssh_port', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with boundary ssh_port values.
     */
    public function test_validation_passes_with_boundary_ssh_port_values(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
        ]);

        $request = new UpdateServerRequest;
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $boundaryPorts = [1, 22, 2222, 65535];

        foreach ($boundaryPorts as $port) {
            $data = [
                'vanity_name' => 'Server',
                'public_ip' => '192.168.1.1',
                'ssh_port' => $port,
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Port {$port} should be valid");
        }
    }

    /**
     * Test validation passes when server keeps same ssh_port and public_ip.
     */
    public function test_validation_passes_when_server_keeps_same_ssh_port_and_public_ip(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'public_ip' => '192.168.1.100',
            'ssh_port' => 22,
        ]);

        $data = [
            'vanity_name' => 'Updated Name',
            'public_ip' => '192.168.1.100',
            'ssh_port' => 22,
        ];

        $request = new UpdateServerRequest;
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes when changing to different public_ip with same ssh_port.
     */
    public function test_validation_passes_when_changing_to_different_public_ip_with_same_ssh_port(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Another server with IP 192.168.1.100 and port 22
        Server::factory()->create([
            'user_id' => $user->id,
            'public_ip' => '192.168.1.100',
            'ssh_port' => 22,
        ]);

        // Current server being updated
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'public_ip' => '192.168.1.200',
            'ssh_port' => 22,
        ]);

        $data = [
            'vanity_name' => 'Server',
            'public_ip' => '192.168.1.201', // Different IP from both existing servers
            'ssh_port' => 22, // Same port as other servers, but different IP
        ];

        $request = new UpdateServerRequest;
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when ssh_port and public_ip combination already exists on another server.
     */
    public function test_validation_fails_when_ssh_port_and_public_ip_combination_already_exists(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Existing server with specific IP/port combination
        Server::factory()->create([
            'user_id' => $user->id,
            'public_ip' => '192.168.1.100',
            'ssh_port' => 2222,
        ]);

        // Current server being updated
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'public_ip' => '192.168.1.200',
            'ssh_port' => 22,
        ]);

        $data = [
            'vanity_name' => 'Server',
            'public_ip' => '192.168.1.100', // Same as existing server
            'ssh_port' => 2222, // Same as existing server
        ];

        $request = new UpdateServerRequest;
        $request->setRouteResolver(function () use ($server) {
            $route = new class($server)
            {
                public function __construct(private $server) {}

                public function parameter($name)
                {
                    return $name === 'server' ? $this->server : null;
                }
            };

            return $route;
        });
        $request->merge($data);

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('ssh_port', $validator->errors()->toArray());
    }

    /**
     * Test authorize returns true when user is authenticated.
     */
    public function test_authorize_returns_true_when_user_is_authenticated(): void
    {
        // Arrange
        $user = User::factory()->create();
        $request = new UpdateServerRequest;
        $request->setUserResolver(fn () => $user);

        // Act
        $result = $request->authorize();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test authorize returns false when user is not authenticated.
     */
    public function test_authorize_returns_false_when_user_is_not_authenticated(): void
    {
        // Arrange
        $request = new UpdateServerRequest;
        $request->setUserResolver(fn () => null);

        // Act
        $result = $request->authorize();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test validation passes with IPv6 addresses.
     */
    public function test_validation_passes_with_ipv6_addresses(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
        ]);

        $data = [
            'vanity_name' => 'Server',
            'public_ip' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
            'private_ip' => '::1',
            'ssh_port' => 22,
        ];

        $request = new UpdateServerRequest;
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }
}
