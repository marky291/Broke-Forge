<?php

namespace Tests\Unit\Http\Requests\Servers;

use App\Enums\ServerProvider;
use App\Http\Requests\Servers\StoreServerRequest;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreServerRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test validation passes with all valid data.
     */
    public function test_validation_passes_with_all_valid_data(): void
    {
        // Arrange
        $data = [
            'vanity_name' => 'My Server',
            'provider' => ServerProvider::DigitalOcean->value,
            'public_ip' => '192.168.1.100',
            'private_ip' => '10.0.0.1',
            'ssh_port' => 22,
            'php_version' => '8.3',
            'add_ssh_key_to_github' => true,
        ];

        $request = new StoreServerRequest;

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
        $data = [
            'vanity_name' => 'Server',
            'public_ip' => '192.168.1.1',
            'ssh_port' => 22,
            'php_version' => '8.4',
        ];

        $request = new StoreServerRequest;

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
        $data = [
            'public_ip' => '192.168.1.1',
            'ssh_port' => 22,
            'php_version' => '8.3',
        ];

        $request = new StoreServerRequest;

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
        $data = [
            'vanity_name' => str_repeat('a', 101),
            'public_ip' => '192.168.1.1',
            'ssh_port' => 22,
            'php_version' => '8.3',
        ];

        $request = new StoreServerRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('vanity_name', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when public_ip is missing.
     */
    public function test_validation_fails_when_public_ip_is_missing(): void
    {
        // Arrange
        $data = [
            'vanity_name' => 'Server',
            'ssh_port' => 22,
            'php_version' => '8.3',
        ];

        $request = new StoreServerRequest;

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
        $data = [
            'vanity_name' => 'Server',
            'public_ip' => 'not-an-ip',
            'ssh_port' => 22,
            'php_version' => '8.3',
        ];

        $request = new StoreServerRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('public_ip', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when public_ip is duplicate.
     */
    public function test_validation_fails_when_public_ip_is_duplicate(): void
    {
        // Arrange
        $user = User::factory()->create();
        Server::factory()->create([
            'user_id' => $user->id,
            'public_ip' => '192.168.1.100',
        ]);

        $data = [
            'vanity_name' => 'Server',
            'public_ip' => '192.168.1.100',
            'ssh_port' => 22,
            'php_version' => '8.3',
        ];

        $request = new StoreServerRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('public_ip', $validator->errors()->toArray());
    }

    /**
     * Test validation passes when private_ip is not provided.
     */
    public function test_validation_passes_when_private_ip_is_not_provided(): void
    {
        // Arrange
        $data = [
            'vanity_name' => 'Server',
            'public_ip' => '192.168.1.1',
            'ssh_port' => 22,
            'php_version' => '8.3',
        ];

        $request = new StoreServerRequest;

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
        $data = [
            'vanity_name' => 'Server',
            'public_ip' => '192.168.1.1',
            'private_ip' => 'invalid-ip',
            'ssh_port' => 22,
            'php_version' => '8.3',
        ];

        $request = new StoreServerRequest;

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
        $data = [
            'vanity_name' => 'Server',
            'public_ip' => '192.168.1.1',
            'php_version' => '8.3',
        ];

        $request = new StoreServerRequest;

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
        $data = [
            'vanity_name' => 'Server',
            'public_ip' => '192.168.1.1',
            'ssh_port' => 0,
            'php_version' => '8.3',
        ];

        $request = new StoreServerRequest;

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
        $data = [
            'vanity_name' => 'Server',
            'public_ip' => '192.168.1.1',
            'ssh_port' => 65536,
            'php_version' => '8.3',
        ];

        $request = new StoreServerRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('ssh_port', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with minimum ssh_port.
     */
    public function test_validation_passes_with_minimum_ssh_port(): void
    {
        // Arrange
        $data = [
            'vanity_name' => 'Server',
            'public_ip' => '192.168.1.1',
            'ssh_port' => 1,
            'php_version' => '8.3',
        ];

        $request = new StoreServerRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with maximum ssh_port.
     */
    public function test_validation_passes_with_maximum_ssh_port(): void
    {
        // Arrange
        $data = [
            'vanity_name' => 'Server',
            'public_ip' => '192.168.1.1',
            'ssh_port' => 65535,
            'php_version' => '8.3',
        ];

        $request = new StoreServerRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when php_version is missing.
     */
    public function test_validation_fails_when_php_version_is_missing(): void
    {
        // Arrange
        $data = [
            'vanity_name' => 'Server',
            'public_ip' => '192.168.1.1',
            'ssh_port' => 22,
        ];

        $request = new StoreServerRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('php_version', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when php_version is invalid.
     */
    public function test_validation_fails_when_php_version_is_invalid(): void
    {
        // Arrange
        $data = [
            'vanity_name' => 'Server',
            'public_ip' => '192.168.1.1',
            'ssh_port' => 22,
            'php_version' => '7.3',
        ];

        $request = new StoreServerRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('php_version', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with all valid php_versions.
     */
    public function test_validation_passes_with_all_valid_php_versions(): void
    {
        // Arrange
        $request = new StoreServerRequest;
        $validVersions = ['8.1', '8.2', '8.3', '8.4'];
        $counter = 10;

        foreach ($validVersions as $version) {
            $data = [
                'vanity_name' => 'Server',
                'public_ip' => "192.168.1.{$counter}",
                'ssh_port' => 22,
                'php_version' => $version,
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "PHP version {$version} should be valid");

            $counter++;
        }
    }

    /**
     * Test validation passes with valid provider enum.
     */
    public function test_validation_passes_with_valid_provider_enum(): void
    {
        // Arrange
        $data = [
            'vanity_name' => 'Server',
            'provider' => ServerProvider::DigitalOcean->value,
            'public_ip' => '192.168.1.1',
            'ssh_port' => 22,
            'php_version' => '8.3',
        ];

        $request = new StoreServerRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails with invalid provider value.
     */
    public function test_validation_fails_with_invalid_provider_value(): void
    {
        // Arrange
        $data = [
            'vanity_name' => 'Server',
            'provider' => 'invalid_provider',
            'public_ip' => '192.168.1.1',
            'ssh_port' => 22,
            'php_version' => '8.3',
        ];

        $request = new StoreServerRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('provider', $validator->errors()->toArray());
    }

    /**
     * Test validation passes when provider is not provided.
     */
    public function test_validation_passes_when_provider_is_not_provided(): void
    {
        // Arrange
        $data = [
            'vanity_name' => 'Server',
            'public_ip' => '192.168.1.1',
            'ssh_port' => 22,
            'php_version' => '8.3',
        ];

        $request = new StoreServerRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with add_ssh_key_to_github as boolean.
     */
    public function test_validation_passes_with_add_ssh_key_to_github_as_boolean(): void
    {
        // Arrange
        $data = [
            'vanity_name' => 'Server',
            'public_ip' => '192.168.1.1',
            'ssh_port' => 22,
            'php_version' => '8.3',
            'add_ssh_key_to_github' => true,
        ];

        $request = new StoreServerRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes when add_ssh_key_to_github is not provided.
     */
    public function test_validation_passes_when_add_ssh_key_to_github_is_not_provided(): void
    {
        // Arrange
        $data = [
            'vanity_name' => 'Server',
            'public_ip' => '192.168.1.1',
            'ssh_port' => 22,
            'php_version' => '8.3',
        ];

        $request = new StoreServerRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test authorize returns true when user is authenticated.
     */
    public function test_authorize_returns_true_when_user_is_authenticated(): void
    {
        // Arrange
        $user = User::factory()->create();
        $request = new StoreServerRequest;
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
        $request = new StoreServerRequest;
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
        $data = [
            'vanity_name' => 'Server',
            'public_ip' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
            'private_ip' => '::1',
            'ssh_port' => 22,
            'php_version' => '8.3',
        ];

        $request = new StoreServerRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }
}
