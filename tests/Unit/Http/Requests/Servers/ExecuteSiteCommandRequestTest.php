<?php

namespace Tests\Unit\Http\Requests\Servers;

use App\Http\Requests\Servers\ExecuteSiteCommandRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ExecuteSiteCommandRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test validation passes with valid command.
     */
    public function test_validation_passes_with_valid_command(): void
    {
        // Arrange
        $data = [
            'command' => 'php artisan cache:clear',
        ];

        $request = new ExecuteSiteCommandRequest;

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
        $data = [];

        $request = new ExecuteSiteCommandRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('command', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when command is empty string.
     */
    public function test_validation_fails_when_command_is_empty_string(): void
    {
        // Arrange
        $data = [
            'command' => '',
        ];

        $request = new ExecuteSiteCommandRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('command', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with short command.
     */
    public function test_validation_passes_with_short_command(): void
    {
        // Arrange
        $data = [
            'command' => 'ls',
        ];

        $request = new ExecuteSiteCommandRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with maximum valid command length.
     */
    public function test_validation_passes_with_maximum_valid_command_length(): void
    {
        // Arrange
        $data = [
            'command' => str_repeat('a', 2000),
        ];

        $request = new ExecuteSiteCommandRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when command exceeds max length.
     */
    public function test_validation_fails_when_command_exceeds_max_length(): void
    {
        // Arrange
        $data = [
            'command' => str_repeat('a', 2001),
        ];

        $request = new ExecuteSiteCommandRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('command', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with various valid commands.
     */
    public function test_validation_passes_with_various_valid_commands(): void
    {
        // Arrange
        $request = new ExecuteSiteCommandRequest;

        $validCommands = [
            'php artisan migrate',
            'npm run build',
            'composer install',
            'git pull origin main',
            'echo "Hello World"',
            'cd /var/www && php artisan queue:work',
        ];

        foreach ($validCommands as $command) {
            $data = ['command' => $command];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Command '{$command}' should be valid");
        }
    }

    /**
     * Test validation passes with command containing special characters.
     */
    public function test_validation_passes_with_command_containing_special_characters(): void
    {
        // Arrange
        $data = [
            'command' => 'find . -name "*.log" -delete',
        ];

        $request = new ExecuteSiteCommandRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with multiline command.
     */
    public function test_validation_passes_with_multiline_command(): void
    {
        // Arrange
        $data = [
            'command' => "php artisan down\nphp artisan migrate\nphp artisan up",
        ];

        $request = new ExecuteSiteCommandRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test custom error message for required field.
     */
    public function test_custom_error_log_for_required_field(): void
    {
        // Arrange
        $data = [];

        $request = new ExecuteSiteCommandRequest;

        // Act
        $validator = Validator::make($data, $request->rules(), $request->messages());
        $errors = $validator->errors();

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertEquals('Please provide a command to run.', $errors->first('command'));
    }

    /**
     * Test authorize returns true.
     */
    public function test_authorize_returns_true(): void
    {
        // Arrange
        $request = new ExecuteSiteCommandRequest;

        // Act
        $result = $request->authorize();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test messages method returns array with custom messages.
     */
    public function test_messages_method_returns_array_with_custom_messages(): void
    {
        // Arrange
        $request = new ExecuteSiteCommandRequest;

        // Act
        $messages = $request->messages();

        // Assert
        $this->assertIsArray($messages);
        $this->assertArrayHasKey('command.required', $messages);
        $this->assertEquals('Please provide a command to run.', $messages['command.required']);
    }

    /**
     * Test validation passes with realistic deployment command.
     */
    public function test_validation_passes_with_realistic_deployment_command(): void
    {
        // Arrange
        $data = [
            'command' => 'php artisan migrate --force && php artisan cache:clear && php artisan config:clear',
        ];

        $request = new ExecuteSiteCommandRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with command containing paths.
     */
    public function test_validation_passes_with_command_containing_paths(): void
    {
        // Arrange
        $data = [
            'command' => 'cp /var/www/html/.env.example /var/www/html/.env',
        ];

        $request = new ExecuteSiteCommandRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }
}
