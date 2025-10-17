<?php

namespace Tests\Unit\Http\Requests;

use App\Enums\ScheduleFrequency;
use App\Enums\SchedulerStatus;
use App\Http\Requests\StoreScheduledTaskRequest;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreScheduledTaskRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test validation passes with all valid data.
     */
    public function test_validation_passes_with_all_valid_data(): void
    {
        // Arrange
        $data = [
            'name' => 'Daily Backup',
            'command' => 'php artisan backup:run',
            'frequency' => ScheduleFrequency::Daily->value,
            'send_notifications' => true,
            'timeout' => 300,
        ];

        $request = new StoreScheduledTaskRequest;

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
            'name' => 'Task',
            'command' => 'ls -la',
            'frequency' => ScheduleFrequency::Hourly->value,
        ];

        $request = new StoreScheduledTaskRequest;

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
            'command' => 'php artisan backup:run',
            'frequency' => ScheduleFrequency::Daily->value,
        ];

        $request = new StoreScheduledTaskRequest;

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
            'command' => 'php artisan backup:run',
            'frequency' => ScheduleFrequency::Daily->value,
        ];

        $request = new StoreScheduledTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when command is missing.
     */
    public function test_validation_fails_when_command_is_missing(): void
    {
        // Arrange
        $data = [
            'name' => 'Task',
            'frequency' => ScheduleFrequency::Daily->value,
        ];

        $request = new StoreScheduledTaskRequest;

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
            'frequency' => ScheduleFrequency::Daily->value,
        ];

        $request = new StoreScheduledTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('command', $validator->errors()->toArray());
    }

    /**
     * Test validation fails with dangerous rm command.
     */
    public function test_validation_fails_with_dangerous_rm_command(): void
    {
        // Arrange
        $data = [
            'name' => 'Task',
            'command' => 'rm -rf /',
            'frequency' => ScheduleFrequency::Daily->value,
        ];

        $request = new StoreScheduledTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('command', $validator->errors()->toArray());
    }

    /**
     * Test validation fails with sudo command.
     */
    public function test_validation_fails_with_sudo_command(): void
    {
        // Arrange
        $data = [
            'name' => 'Task',
            'command' => 'sudo systemctl restart nginx',
            'frequency' => ScheduleFrequency::Daily->value,
        ];

        $request = new StoreScheduledTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('command', $validator->errors()->toArray());
    }

    /**
     * Test validation fails with command containing shell injection.
     */
    public function test_validation_fails_with_command_containing_shell_injection(): void
    {
        // Arrange
        $data = [
            'name' => 'Task',
            'command' => 'echo test; rm -rf /tmp',
            'frequency' => ScheduleFrequency::Daily->value,
        ];

        $request = new StoreScheduledTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('command', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with safe commands.
     */
    public function test_validation_passes_with_safe_commands(): void
    {
        // Arrange
        $request = new StoreScheduledTaskRequest;
        $safeCommands = [
            'php artisan backup:run',
            'ls -la',
            'echo "Hello World"',
            'date',
            'whoami',
        ];

        foreach ($safeCommands as $command) {
            $data = [
                'name' => 'Task',
                'command' => $command,
                'frequency' => ScheduleFrequency::Daily->value,
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Command '{$command}' should be valid");
        }
    }

    /**
     * Test validation fails when frequency is missing.
     */
    public function test_validation_fails_when_frequency_is_missing(): void
    {
        // Arrange
        $data = [
            'name' => 'Task',
            'command' => 'php artisan backup:run',
        ];

        $request = new StoreScheduledTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('frequency', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with all valid frequency enum values.
     */
    public function test_validation_passes_with_all_valid_frequency_enum_values(): void
    {
        // Arrange
        $request = new StoreScheduledTaskRequest;
        $validFrequencies = [
            ScheduleFrequency::Minutely->value,
            ScheduleFrequency::Hourly->value,
            ScheduleFrequency::Daily->value,
            ScheduleFrequency::Weekly->value,
            ScheduleFrequency::Monthly->value,
            ScheduleFrequency::Custom->value,
        ];

        foreach ($validFrequencies as $frequency) {
            $data = [
                'name' => 'Task',
                'command' => 'php artisan backup:run',
                'frequency' => $frequency,
            ];

            // Add cron expression for custom frequency
            if ($frequency === ScheduleFrequency::Custom->value) {
                $data['cron_expression'] = '0 0 * * *';
            }

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Frequency '{$frequency}' should be valid");
        }
    }

    /**
     * Test validation fails with invalid frequency value.
     */
    public function test_validation_fails_with_invalid_frequency_value(): void
    {
        // Arrange
        $data = [
            'name' => 'Task',
            'command' => 'php artisan backup:run',
            'frequency' => 'invalid_frequency',
        ];

        $request = new StoreScheduledTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('frequency', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when frequency is custom but cron_expression is missing.
     */
    public function test_validation_fails_when_frequency_is_custom_but_cron_expression_is_missing(): void
    {
        // Arrange
        $data = [
            'name' => 'Task',
            'command' => 'php artisan backup:run',
            'frequency' => ScheduleFrequency::Custom->value,
        ];

        $request = new StoreScheduledTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('cron_expression', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with valid cron expression.
     */
    public function test_validation_passes_with_valid_cron_expression(): void
    {
        // Arrange
        $request = new StoreScheduledTaskRequest;
        $validExpressions = [
            '* * * * *',
            '0 * * * *',
            '0 0 * * *',
            '0 0 * * 0',
            '0 0 1 * *',
            '0 0-6 * * *',
            '0 0 1-15 * *',
        ];

        foreach ($validExpressions as $expression) {
            $data = [
                'name' => 'Task',
                'command' => 'php artisan backup:run',
                'frequency' => ScheduleFrequency::Custom->value,
                'cron_expression' => $expression,
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Cron expression '{$expression}' should be valid");
        }
    }

    /**
     * Test validation fails with invalid cron expression format.
     */
    public function test_validation_fails_with_invalid_cron_expression_format(): void
    {
        // Arrange
        $request = new StoreScheduledTaskRequest;
        $invalidExpressions = [
            '* * * *',
            '* * * * * *',
            'invalid',
            '60 * * * *',
            '* 24 * * *',
            '* * 32 * *',
            '* * * 13 *',
            '* * * * 7',
        ];

        foreach ($invalidExpressions as $expression) {
            $data = [
                'name' => 'Task',
                'command' => 'php artisan backup:run',
                'frequency' => ScheduleFrequency::Custom->value,
                'cron_expression' => $expression,
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertTrue($validator->fails(), "Cron expression '{$expression}' should be invalid");
            $this->assertArrayHasKey('cron_expression', $validator->errors()->toArray());
        }
    }

    /**
     * Test validation passes when send_notifications is boolean.
     */
    public function test_validation_passes_when_send_notifications_is_boolean(): void
    {
        // Arrange
        foreach ([true, false] as $value) {
            $data = [
                'name' => 'Task',
                'command' => 'php artisan backup:run',
                'frequency' => ScheduleFrequency::Daily->value,
                'send_notifications' => $value,
            ];

            $request = new StoreScheduledTaskRequest;

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails());
        }
    }

    /**
     * Test validation passes with valid timeout values.
     */
    public function test_validation_passes_with_valid_timeout_values(): void
    {
        // Arrange
        config(['scheduler.max_timeout' => 3600]);

        $data = [
            'name' => 'Task',
            'command' => 'php artisan backup:run',
            'frequency' => ScheduleFrequency::Daily->value,
            'timeout' => 300,
        ];

        $request = new StoreScheduledTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when timeout is below minimum.
     */
    public function test_validation_fails_when_timeout_is_below_minimum(): void
    {
        // Arrange
        config(['scheduler.max_timeout' => 3600]);

        $data = [
            'name' => 'Task',
            'command' => 'php artisan backup:run',
            'frequency' => ScheduleFrequency::Daily->value,
            'timeout' => 0,
        ];

        $request = new StoreScheduledTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('timeout', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when timeout exceeds configured maximum.
     */
    public function test_validation_fails_when_timeout_exceeds_configured_maximum(): void
    {
        // Arrange
        config(['scheduler.max_timeout' => 3600]);

        $data = [
            'name' => 'Task',
            'command' => 'php artisan backup:run',
            'frequency' => ScheduleFrequency::Daily->value,
            'timeout' => 3601,
        ];

        $request = new StoreScheduledTaskRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('timeout', $validator->errors()->toArray());
    }

    /**
     * Test authorize returns true when server has scheduler active.
     */
    public function test_authorize_returns_true_when_server_has_scheduler_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        $request = new StoreScheduledTaskRequest;
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        // Act
        $result = $request->authorize();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test authorize returns false when server does not have scheduler active.
     */
    public function test_authorize_returns_false_when_server_does_not_have_scheduler_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'scheduler_status' => SchedulerStatus::Uninstalled,
        ]);

        $request = new StoreScheduledTaskRequest;
        $request->setRouteResolver(fn () => new class($server)
        {
            public function __construct(private $server) {}

            public function parameter($name)
            {
                return $name === 'server' ? $this->server : null;
            }
        });

        // Act
        $result = $request->authorize();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test authorize returns false when server is null.
     */
    public function test_authorize_returns_false_when_server_is_null(): void
    {
        // Arrange
        $request = new StoreScheduledTaskRequest;
        $request->setRouteResolver(fn () => new class
        {
            public function parameter($name)
            {
                return null;
            }
        });

        // Act
        $result = $request->authorize();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test custom attributes are defined.
     */
    public function test_custom_attributes_are_defined(): void
    {
        // Arrange
        $request = new StoreScheduledTaskRequest;

        // Act
        $attributes = $request->attributes();

        // Assert
        $this->assertIsArray($attributes);
        $this->assertArrayHasKey('name', $attributes);
        $this->assertArrayHasKey('command', $attributes);
        $this->assertArrayHasKey('frequency', $attributes);
        $this->assertArrayHasKey('cron_expression', $attributes);
        $this->assertArrayHasKey('send_notifications', $attributes);
        $this->assertArrayHasKey('timeout', $attributes);
    }

    /**
     * Test custom error messages are defined.
     */
    public function test_custom_error_messages_are_defined(): void
    {
        // Arrange
        $request = new StoreScheduledTaskRequest;

        // Act
        $messages = $request->messages();

        // Assert
        $this->assertIsArray($messages);
        $this->assertArrayHasKey('cron_expression.required_if', $messages);
        $this->assertArrayHasKey('timeout.max', $messages);
    }
}
