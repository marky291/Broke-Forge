<?php

namespace Tests\Unit\Http\Requests\Servers;

use App\Enums\DatabaseType;
use App\Http\Requests\Servers\InstallDatabaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class InstallDatabaseRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test validation passes with all valid data.
     */
    public function test_validation_passes_with_all_valid_data(): void
    {
        // Arrange
        $data = [
            'name' => 'my_database',
            'type' => DatabaseType::MySQL->value,
            'version' => '8.0',
            'root_password' => 'SecureP@ssw0rd123',
            'port' => 3306,
        ];

        $request = new InstallDatabaseRequest;

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
            'type' => DatabaseType::PostgreSQL->value,
            'version' => '16',
            'root_password' => 'password123',
        ];

        $request = new InstallDatabaseRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when type is missing.
     */
    public function test_validation_fails_when_type_is_missing(): void
    {
        // Arrange
        $data = [
            'version' => '8.0',
            'root_password' => 'password123',
        ];

        $request = new InstallDatabaseRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('type', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with all valid database types.
     */
    public function test_validation_passes_with_all_valid_database_types(): void
    {
        // Arrange
        $request = new InstallDatabaseRequest;
        $validTypes = [
            DatabaseType::MySQL->value,
            DatabaseType::MariaDB->value,
            DatabaseType::PostgreSQL->value,
            DatabaseType::MongoDB->value,
            DatabaseType::Redis->value,
        ];

        foreach ($validTypes as $type) {
            $data = [
                'type' => $type,
                'version' => '1.0',
                'root_password' => 'password123',
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Database type {$type} should be valid");
        }
    }

    /**
     * Test validation fails with invalid database type.
     */
    public function test_validation_fails_with_invalid_database_type(): void
    {
        // Arrange
        $data = [
            'type' => 'invalid_db_type',
            'version' => '8.0',
            'root_password' => 'password123',
        ];

        $request = new InstallDatabaseRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('type', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when version is missing.
     */
    public function test_validation_fails_when_version_is_missing(): void
    {
        // Arrange
        $data = [
            'type' => DatabaseType::MySQL->value,
            'root_password' => 'password123',
        ];

        $request = new InstallDatabaseRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('version', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when version exceeds max length.
     */
    public function test_validation_fails_when_version_exceeds_max_length(): void
    {
        // Arrange
        $data = [
            'type' => DatabaseType::MySQL->value,
            'version' => str_repeat('1', 17),
            'root_password' => 'password123',
        ];

        $request = new InstallDatabaseRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('version', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with various version formats.
     */
    public function test_validation_passes_with_various_version_formats(): void
    {
        // Arrange
        $request = new InstallDatabaseRequest;
        $validVersions = [
            '8.0',
            '5.7',
            '11.6',
            '16',
            '10.11',
        ];

        foreach ($validVersions as $version) {
            $data = [
                'type' => DatabaseType::MySQL->value,
                'version' => $version,
                'root_password' => 'password123',
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Version {$version} should be valid");
        }
    }

    /**
     * Test validation fails when root_password is missing.
     */
    public function test_validation_fails_when_root_password_is_missing(): void
    {
        // Arrange
        $data = [
            'type' => DatabaseType::MySQL->value,
            'version' => '8.0',
        ];

        $request = new InstallDatabaseRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('root_password', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when root_password is too short.
     */
    public function test_validation_fails_when_root_password_is_too_short(): void
    {
        // Arrange
        $data = [
            'type' => DatabaseType::MySQL->value,
            'version' => '8.0',
            'root_password' => 'pass',
        ];

        $request = new InstallDatabaseRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('root_password', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with minimum valid root_password length.
     */
    public function test_validation_passes_with_minimum_valid_root_password_length(): void
    {
        // Arrange
        $data = [
            'type' => DatabaseType::MySQL->value,
            'version' => '8.0',
            'root_password' => 'password',
        ];

        $request = new InstallDatabaseRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when root_password exceeds max length.
     */
    public function test_validation_fails_when_root_password_exceeds_max_length(): void
    {
        // Arrange
        $data = [
            'type' => DatabaseType::MySQL->value,
            'version' => '8.0',
            'root_password' => str_repeat('a', 129),
        ];

        $request = new InstallDatabaseRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('root_password', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with maximum valid root_password length.
     */
    public function test_validation_passes_with_maximum_valid_root_password_length(): void
    {
        // Arrange
        $data = [
            'type' => DatabaseType::MySQL->value,
            'version' => '8.0',
            'root_password' => str_repeat('a', 128),
        ];

        $request = new InstallDatabaseRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes when name is not provided.
     */
    public function test_validation_passes_when_name_is_not_provided(): void
    {
        // Arrange
        $data = [
            'type' => DatabaseType::MySQL->value,
            'version' => '8.0',
            'root_password' => 'password123',
        ];

        $request = new InstallDatabaseRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with valid name.
     */
    public function test_validation_passes_with_valid_name(): void
    {
        // Arrange
        $data = [
            'name' => 'my_database',
            'type' => DatabaseType::MySQL->value,
            'version' => '8.0',
            'root_password' => 'password123',
        ];

        $request = new InstallDatabaseRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when name exceeds max length.
     */
    public function test_validation_fails_when_name_exceeds_max_length(): void
    {
        // Arrange
        $data = [
            'name' => str_repeat('a', 65),
            'type' => DatabaseType::MySQL->value,
            'version' => '8.0',
            'root_password' => 'password123',
        ];

        $request = new InstallDatabaseRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    /**
     * Test validation passes when port is not provided.
     */
    public function test_validation_passes_when_port_is_not_provided(): void
    {
        // Arrange
        $data = [
            'type' => DatabaseType::MySQL->value,
            'version' => '8.0',
            'root_password' => 'password123',
        ];

        $request = new InstallDatabaseRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with valid port.
     */
    public function test_validation_passes_with_valid_port(): void
    {
        // Arrange
        $data = [
            'type' => DatabaseType::MySQL->value,
            'version' => '8.0',
            'root_password' => 'password123',
            'port' => 3306,
        ];

        $request = new InstallDatabaseRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when port is below minimum.
     */
    public function test_validation_fails_when_port_is_below_minimum(): void
    {
        // Arrange
        $data = [
            'type' => DatabaseType::MySQL->value,
            'version' => '8.0',
            'root_password' => 'password123',
            'port' => 0,
        ];

        $request = new InstallDatabaseRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('port', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when port exceeds maximum.
     */
    public function test_validation_fails_when_port_exceeds_maximum(): void
    {
        // Arrange
        $data = [
            'type' => DatabaseType::MySQL->value,
            'version' => '8.0',
            'root_password' => 'password123',
            'port' => 65536,
        ];

        $request = new InstallDatabaseRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('port', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with minimum valid port.
     */
    public function test_validation_passes_with_minimum_valid_port(): void
    {
        // Arrange
        $data = [
            'type' => DatabaseType::MySQL->value,
            'version' => '8.0',
            'root_password' => 'password123',
            'port' => 1,
        ];

        $request = new InstallDatabaseRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with maximum valid port.
     */
    public function test_validation_passes_with_maximum_valid_port(): void
    {
        // Arrange
        $data = [
            'type' => DatabaseType::MySQL->value,
            'version' => '8.0',
            'root_password' => 'password123',
            'port' => 65535,
        ];

        $request = new InstallDatabaseRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with common database ports.
     */
    public function test_validation_passes_with_common_database_ports(): void
    {
        // Arrange
        $request = new InstallDatabaseRequest;
        $commonPorts = [
            3306,  // MySQL
            5432,  // PostgreSQL
            27017, // MongoDB
            6379,  // Redis
        ];

        foreach ($commonPorts as $port) {
            $data = [
                'type' => DatabaseType::MySQL->value,
                'version' => '8.0',
                'root_password' => 'password123',
                'port' => $port,
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Port {$port} should be valid");
        }
    }

    /**
     * Test authorize returns true when user is authenticated.
     */
    public function test_authorize_returns_true_when_user_is_authenticated(): void
    {
        // Arrange
        $user = User::factory()->create();
        $request = new InstallDatabaseRequest;
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
        $request = new InstallDatabaseRequest;
        $request->setUserResolver(fn () => null);

        // Act
        $result = $request->authorize();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test custom error messages are defined.
     */
    public function test_custom_error_messages_are_defined(): void
    {
        // Arrange
        $request = new InstallDatabaseRequest;

        // Act
        $messages = $request->messages();

        // Assert
        $this->assertIsArray($messages);
        $this->assertArrayHasKey('type.required', $messages);
        $this->assertArrayHasKey('version.required', $messages);
        $this->assertArrayHasKey('root_password.required', $messages);
        $this->assertArrayHasKey('root_password.min', $messages);
        $this->assertArrayHasKey('port.min', $messages);
        $this->assertArrayHasKey('port.max', $messages);
    }
}
