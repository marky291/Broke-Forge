<?php

namespace Tests\Unit\Http\Requests\Servers;

use App\Http\Requests\Servers\FirewallRuleRequest;
use App\Models\Server;
use App\Models\ServerFirewall;
use App\Models\ServerFirewallRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class FirewallRuleRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test validation passes with valid single port.
     */
    public function test_validation_passes_with_valid_single_port(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        $request = new FirewallRuleRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $data = [
            'name' => 'Allow HTTP',
            'port' => '80',
            'from_ip_address' => '192.168.1.1',
            'rule_type' => 'allow',
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with valid port range.
     */
    public function test_validation_passes_with_valid_port_range(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        $request = new FirewallRuleRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $data = [
            'name' => 'Allow Custom Range',
            'port' => '3000-3005',
            'rule_type' => 'allow',
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when port is invalid format.
     */
    public function test_validation_fails_when_port_is_invalid_format(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        $request = new FirewallRuleRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $data = [
            'name' => 'Test Rule',
            'port' => 'invalid',
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('port', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when port number is too high.
     */
    public function test_validation_fails_when_port_number_is_too_high(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        $request = new FirewallRuleRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $data = [
            'name' => 'Test Rule',
            'port' => '65536',
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('port', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when port range start is greater than or equal to end.
     */
    public function test_validation_fails_when_port_range_start_is_greater_than_or_equal_to_end(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        $request = new FirewallRuleRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $data = [
            'name' => 'Test Rule',
            'port' => '3005-3000',
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('port', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when port range has invalid bounds.
     */
    public function test_validation_fails_when_port_range_has_invalid_bounds(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        $request = new FirewallRuleRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $data = [
            'name' => 'Test Rule',
            'port' => '100-70000',
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('port', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when duplicate port exists.
     */
    public function test_validation_fails_when_duplicate_port_exists(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Create existing rule with port 80
        ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'port' => '80',
        ]);

        $request = new FirewallRuleRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $data = [
            'name' => 'Duplicate Rule',
            'port' => '80',
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('port', $validator->errors()->toArray());
        $this->assertStringContainsString('already exists', $validator->errors()->first('port'));
    }

    /**
     * Test validation passes when port is nullable and not provided.
     */
    public function test_validation_passes_when_port_is_nullable_and_not_provided(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        $request = new FirewallRuleRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $data = [
            'name' => 'Test Rule',
            'rule_type' => 'allow',
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when from_ip_address is invalid.
     */
    public function test_validation_fails_when_from_ip_address_is_invalid(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        $request = new FirewallRuleRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $data = [
            'name' => 'Test Rule',
            'from_ip_address' => 'not-an-ip',
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('from_ip_address', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when rule_type is invalid.
     */
    public function test_validation_fails_when_rule_type_is_invalid(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        $request = new FirewallRuleRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $data = [
            'name' => 'Test Rule',
            'rule_type' => 'invalid',
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('rule_type', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with valid rule_type allow.
     */
    public function test_validation_passes_with_valid_rule_type_allow(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        $request = new FirewallRuleRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $data = [
            'name' => 'Test Rule',
            'rule_type' => 'allow',
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with valid rule_type deny.
     */
    public function test_validation_passes_with_valid_rule_type_deny(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        $request = new FirewallRuleRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $data = [
            'name' => 'Test Rule',
            'rule_type' => 'deny',
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when name is missing.
     */
    public function test_validation_fails_when_name_is_missing(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        $request = new FirewallRuleRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $data = [
            'port' => '80',
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when name exceeds max length.
     */
    public function test_validation_fails_when_name_exceeds_max_length(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        $request = new FirewallRuleRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $data = [
            'name' => str_repeat('a', 256),
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    /**
     * Test authorize returns true.
     */
    public function test_authorize_returns_true(): void
    {
        // Arrange
        $request = new FirewallRuleRequest;

        // Act
        $result = $request->authorize();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test custom error messages are defined.
     */
    public function test_custom_error_messages_are_defined(): void
    {
        // Arrange
        $request = new FirewallRuleRequest;

        // Act
        $messages = $request->messages();

        // Assert
        $this->assertIsArray($messages);
        $this->assertArrayHasKey('name.required', $messages);
        $this->assertArrayHasKey('name.max', $messages);
        $this->assertArrayHasKey('from_ip_address.ip', $messages);
        $this->assertArrayHasKey('rule_type.in', $messages);
    }

    /**
     * Test validation passes with minimum valid port 1.
     */
    public function test_validation_passes_with_minimum_valid_port_1(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        $request = new FirewallRuleRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $data = [
            'name' => 'Min Port',
            'port' => '1',
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with maximum valid port 65535.
     */
    public function test_validation_passes_with_maximum_valid_port_65535(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerFirewall::factory()->create(['server_id' => $server->id]);

        $request = new FirewallRuleRequest;
        $request->setContainer(app());
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        $data = [
            'name' => 'Max Port',
            'port' => '65535',
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }
}
