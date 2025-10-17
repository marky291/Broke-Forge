<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StoreSupervisorTaskRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreSupervisorTaskRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test validation passes with all valid data.
     */
    public function test_validation_passes_with_all_valid_data(): void
    {
        // Arrange
        $data = [
            'name' => 'Laravel Queue Worker',
            'command' => 'php artisan queue:work',
            'working_directory' => '/var/www/html',
            'processes' => 4,
            'user' => 'www-data',
            'auto_restart' => true,
            'autorestart_unexpected' => true,
        ];

        $request = new StoreSupervisorTaskRequest;

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
        $data = [
            'command' => 'php artisan queue:work',
            'working_directory' => '/var/www/html',
            'processes' => 4,
            'user' => 'www-data',
            'auto_restart' => true,
            'autorestart_unexpected' => true,
        ];

        $request = new StoreSupervisorTaskRequest;

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
        $data = [
            'name' => str_repeat('a', 256),
            'command' => 'php artisan queue:work',
            'working_directory' => '/var/www/html',
            'processes' => 4,
            'user' => 'www-data',
            'auto_restart' => true,
            'autorestart_unexpected' => true,
        ];

        $request = new StoreSupervisorTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with maximum valid name length.
     */
    public function test_validation_passes_with_maximum_valid_name_length(): void
    {
        // Arrange
        $data = [
            'name' => str_repeat('a', 255),
            'command' => 'php artisan queue:work',
            'working_directory' => '/var/www/html',
            'processes' => 4,
            'user' => 'www-data',
            'auto_restart' => true,
            'autorestart_unexpected' => true,
        ];

        $request = new StoreSupervisorTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when command is missing.
     */
    public function test_validation_fails_when_command_is_missing(): void
    {
        // Arrange
        $data = [
            'name' => 'Task',
            'working_directory' => '/var/www/html',
            'processes' => 4,
            'user' => 'www-data',
            'auto_restart' => true,
            'autorestart_unexpected' => true,
        ];

        $request = new StoreSupervisorTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('command', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when command exceeds max length.
     */
    public function test_validation_fails_when_command_exceeds_max_length(): void
    {
        // Arrange
        $data = [
            'name' => 'Task',
            'command' => str_repeat('a', 1001),
            'working_directory' => '/var/www/html',
            'processes' => 4,
            'user' => 'www-data',
            'auto_restart' => true,
            'autorestart_unexpected' => true,
        ];

        $request = new StoreSupervisorTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('command', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with maximum valid command length.
     */
    public function test_validation_passes_with_maximum_valid_command_length(): void
    {
        // Arrange
        $data = [
            'name' => 'Task',
            'command' => str_repeat('a', 1000),
            'working_directory' => '/var/www/html',
            'processes' => 4,
            'user' => 'www-data',
            'auto_restart' => true,
            'autorestart_unexpected' => true,
        ];

        $request = new StoreSupervisorTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when working_directory is missing.
     */
    public function test_validation_fails_when_working_directory_is_missing(): void
    {
        // Arrange
        $data = [
            'name' => 'Task',
            'command' => 'php artisan queue:work',
            'processes' => 4,
            'user' => 'www-data',
            'auto_restart' => true,
            'autorestart_unexpected' => true,
        ];

        $request = new StoreSupervisorTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('working_directory', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when working_directory exceeds max length.
     */
    public function test_validation_fails_when_working_directory_exceeds_max_length(): void
    {
        // Arrange
        $data = [
            'name' => 'Task',
            'command' => 'php artisan queue:work',
            'working_directory' => str_repeat('a', 501),
            'processes' => 4,
            'user' => 'www-data',
            'auto_restart' => true,
            'autorestart_unexpected' => true,
        ];

        $request = new StoreSupervisorTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('working_directory', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with maximum valid working_directory length.
     */
    public function test_validation_passes_with_maximum_valid_working_directory_length(): void
    {
        // Arrange
        $data = [
            'name' => 'Task',
            'command' => 'php artisan queue:work',
            'working_directory' => str_repeat('a', 500),
            'processes' => 4,
            'user' => 'www-data',
            'auto_restart' => true,
            'autorestart_unexpected' => true,
        ];

        $request = new StoreSupervisorTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when processes is missing.
     */
    public function test_validation_fails_when_processes_is_missing(): void
    {
        // Arrange
        $data = [
            'name' => 'Task',
            'command' => 'php artisan queue:work',
            'working_directory' => '/var/www/html',
            'user' => 'www-data',
            'auto_restart' => true,
            'autorestart_unexpected' => true,
        ];

        $request = new StoreSupervisorTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('processes', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when processes is below minimum.
     */
    public function test_validation_fails_when_processes_is_below_minimum(): void
    {
        // Arrange
        $data = [
            'name' => 'Task',
            'command' => 'php artisan queue:work',
            'working_directory' => '/var/www/html',
            'processes' => 0,
            'user' => 'www-data',
            'auto_restart' => true,
            'autorestart_unexpected' => true,
        ];

        $request = new StoreSupervisorTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('processes', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when processes exceeds maximum.
     */
    public function test_validation_fails_when_processes_exceeds_maximum(): void
    {
        // Arrange
        $data = [
            'name' => 'Task',
            'command' => 'php artisan queue:work',
            'working_directory' => '/var/www/html',
            'processes' => 21,
            'user' => 'www-data',
            'auto_restart' => true,
            'autorestart_unexpected' => true,
        ];

        $request = new StoreSupervisorTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('processes', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with minimum valid processes.
     */
    public function test_validation_passes_with_minimum_valid_processes(): void
    {
        // Arrange
        $data = [
            'name' => 'Task',
            'command' => 'php artisan queue:work',
            'working_directory' => '/var/www/html',
            'processes' => 1,
            'user' => 'www-data',
            'auto_restart' => true,
            'autorestart_unexpected' => true,
        ];

        $request = new StoreSupervisorTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with maximum valid processes.
     */
    public function test_validation_passes_with_maximum_valid_processes(): void
    {
        // Arrange
        $data = [
            'name' => 'Task',
            'command' => 'php artisan queue:work',
            'working_directory' => '/var/www/html',
            'processes' => 20,
            'user' => 'www-data',
            'auto_restart' => true,
            'autorestart_unexpected' => true,
        ];

        $request = new StoreSupervisorTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with various valid process counts.
     */
    public function test_validation_passes_with_various_valid_process_counts(): void
    {
        // Arrange
        $request = new StoreSupervisorTaskRequest;
        $validCounts = [1, 2, 4, 8, 10, 15, 20];

        foreach ($validCounts as $count) {
            $data = [
                'name' => 'Task',
                'command' => 'php artisan queue:work',
                'working_directory' => '/var/www/html',
                'processes' => $count,
                'user' => 'www-data',
                'auto_restart' => true,
                'autorestart_unexpected' => true,
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Process count {$count} should be valid");
        }
    }

    /**
     * Test validation fails when user is missing.
     */
    public function test_validation_fails_when_user_is_missing(): void
    {
        // Arrange
        $data = [
            'name' => 'Task',
            'command' => 'php artisan queue:work',
            'working_directory' => '/var/www/html',
            'processes' => 4,
            'auto_restart' => true,
            'autorestart_unexpected' => true,
        ];

        $request = new StoreSupervisorTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('user', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when user exceeds max length.
     */
    public function test_validation_fails_when_user_exceeds_max_length(): void
    {
        // Arrange
        $data = [
            'name' => 'Task',
            'command' => 'php artisan queue:work',
            'working_directory' => '/var/www/html',
            'processes' => 4,
            'user' => str_repeat('a', 256),
            'auto_restart' => true,
            'autorestart_unexpected' => true,
        ];

        $request = new StoreSupervisorTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('user', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with common user names.
     */
    public function test_validation_passes_with_common_user_names(): void
    {
        // Arrange
        $request = new StoreSupervisorTaskRequest;
        $commonUsers = ['www-data', 'root', 'ubuntu', 'brokeforge', 'nginx'];

        foreach ($commonUsers as $user) {
            $data = [
                'name' => 'Task',
                'command' => 'php artisan queue:work',
                'working_directory' => '/var/www/html',
                'processes' => 4,
                'user' => $user,
                'auto_restart' => true,
                'autorestart_unexpected' => true,
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "User {$user} should be valid");
        }
    }

    /**
     * Test validation fails when auto_restart is missing.
     */
    public function test_validation_fails_when_auto_restart_is_missing(): void
    {
        // Arrange
        $data = [
            'name' => 'Task',
            'command' => 'php artisan queue:work',
            'working_directory' => '/var/www/html',
            'processes' => 4,
            'user' => 'www-data',
            'autorestart_unexpected' => true,
        ];

        $request = new StoreSupervisorTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('auto_restart', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with auto_restart as true or false.
     */
    public function test_validation_passes_with_auto_restart_as_true_or_false(): void
    {
        // Arrange
        $request = new StoreSupervisorTaskRequest;

        foreach ([true, false] as $value) {
            $data = [
                'name' => 'Task',
                'command' => 'php artisan queue:work',
                'working_directory' => '/var/www/html',
                'processes' => 4,
                'user' => 'www-data',
                'auto_restart' => $value,
                'autorestart_unexpected' => true,
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails());
        }
    }

    /**
     * Test validation fails when autorestart_unexpected is missing.
     */
    public function test_validation_fails_when_autorestart_unexpected_is_missing(): void
    {
        // Arrange
        $data = [
            'name' => 'Task',
            'command' => 'php artisan queue:work',
            'working_directory' => '/var/www/html',
            'processes' => 4,
            'user' => 'www-data',
            'auto_restart' => true,
        ];

        $request = new StoreSupervisorTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('autorestart_unexpected', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with autorestart_unexpected as true or false.
     */
    public function test_validation_passes_with_autorestart_unexpected_as_true_or_false(): void
    {
        // Arrange
        $request = new StoreSupervisorTaskRequest;

        foreach ([true, false] as $value) {
            $data = [
                'name' => 'Task',
                'command' => 'php artisan queue:work',
                'working_directory' => '/var/www/html',
                'processes' => 4,
                'user' => 'www-data',
                'auto_restart' => true,
                'autorestart_unexpected' => $value,
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails());
        }
    }

    /**
     * Test validation passes with all boolean combinations.
     */
    public function test_validation_passes_with_all_boolean_combinations(): void
    {
        // Arrange
        $request = new StoreSupervisorTaskRequest;
        $combinations = [
            ['auto_restart' => true, 'autorestart_unexpected' => true],
            ['auto_restart' => true, 'autorestart_unexpected' => false],
            ['auto_restart' => false, 'autorestart_unexpected' => true],
            ['auto_restart' => false, 'autorestart_unexpected' => false],
        ];

        foreach ($combinations as $combo) {
            $data = [
                'name' => 'Task',
                'command' => 'php artisan queue:work',
                'working_directory' => '/var/www/html',
                'processes' => 4,
                'user' => 'www-data',
                'auto_restart' => $combo['auto_restart'],
                'autorestart_unexpected' => $combo['autorestart_unexpected'],
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), 'Boolean combination should be valid');
        }
    }

    /**
     * Test authorize returns true.
     */
    public function test_authorize_returns_true(): void
    {
        // Arrange
        $request = new StoreSupervisorTaskRequest;

        // Act
        $result = $request->authorize();

        // Assert
        $this->assertTrue($result);
    }
}
