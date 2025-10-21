<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StoreTaskRunRequest;
use App\Models\ServerScheduledTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreTaskRunRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test validation passes with all valid data.
     */
    public function test_validation_passes_with_all_valid_data(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create();

        $data = [
            'task_id' => $task->id,
            'started_at' => '2025-01-15 12:00:00',
            'completed_at' => '2025-01-15 12:05:00',
            'exit_code' => 0,
            'output' => 'Task completed successfully',
            'error_output' => '',
            'duration_ms' => 300000,
        ];

        $request = new StoreTaskRunRequest;

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
        $task = ServerScheduledTask::factory()->create();

        $data = [
            'task_id' => $task->id,
            'started_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreTaskRunRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when task_id is missing.
     */
    public function test_validation_fails_when_task_id_is_missing(): void
    {
        // Arrange
        $data = [
            'started_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreTaskRunRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('task_id', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when task_id does not exist.
     */
    public function test_validation_fails_when_task_id_does_not_exist(): void
    {
        // Arrange
        $data = [
            'task_id' => 999999,
            'started_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreTaskRunRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('task_id', $validator->errors()->toArray());
    }

    /**
     * Test validation passes when task_id exists.
     */
    public function test_validation_passes_when_task_id_exists(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create();

        $data = [
            'task_id' => $task->id,
            'started_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreTaskRunRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when started_at is missing.
     */
    public function test_validation_fails_when_started_at_is_missing(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create();

        $data = [
            'task_id' => $task->id,
        ];

        $request = new StoreTaskRunRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('started_at', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when started_at is invalid date.
     */
    public function test_validation_fails_when_started_at_is_invalid_date(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create();

        $data = [
            'task_id' => $task->id,
            'started_at' => 'invalid-date',
        ];

        $request = new StoreTaskRunRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('started_at', $validator->errors()->toArray());
    }

    /**
     * Test validation passes when completed_at is not provided.
     */
    public function test_validation_passes_when_completed_at_is_not_provided(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create();

        $data = [
            'task_id' => $task->id,
            'started_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreTaskRunRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes when completed_at is after started_at.
     */
    public function test_validation_passes_when_completed_at_is_after_started_at(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create();

        $data = [
            'task_id' => $task->id,
            'started_at' => '2025-01-15 12:00:00',
            'completed_at' => '2025-01-15 12:05:00',
        ];

        $request = new StoreTaskRunRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes when completed_at equals started_at.
     */
    public function test_validation_passes_when_completed_at_equals_started_at(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create();

        $data = [
            'task_id' => $task->id,
            'started_at' => '2025-01-15 12:00:00',
            'completed_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreTaskRunRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when completed_at is before started_at.
     */
    public function test_validation_fails_when_completed_at_is_before_started_at(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create();

        $data = [
            'task_id' => $task->id,
            'started_at' => '2025-01-15 12:00:00',
            'completed_at' => '2025-01-15 11:59:00',
        ];

        $request = new StoreTaskRunRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('completed_at', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when completed_at is invalid date.
     */
    public function test_validation_fails_when_completed_at_is_invalid_date(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create();

        $data = [
            'task_id' => $task->id,
            'started_at' => '2025-01-15 12:00:00',
            'completed_at' => 'invalid-date',
        ];

        $request = new StoreTaskRunRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('completed_at', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with various exit codes.
     */
    public function test_validation_passes_with_various_exit_codes(): void
    {
        // Arrange
        $request = new StoreTaskRunRequest;
        $task = ServerScheduledTask::factory()->create();
        $exitCodes = [0, 1, 2, 127, 255, -1];

        foreach ($exitCodes as $exitCode) {
            $data = [
                'task_id' => $task->id,
                'started_at' => '2025-01-15 12:00:00',
                'exit_code' => $exitCode,
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Exit code {$exitCode} should be valid");
        }
    }

    /**
     * Test validation passes when exit_code is not provided.
     */
    public function test_validation_passes_when_exit_code_is_not_provided(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create();

        $data = [
            'task_id' => $task->id,
            'started_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreTaskRunRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with output.
     */
    public function test_validation_passes_with_output(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create();

        $data = [
            'task_id' => $task->id,
            'started_at' => '2025-01-15 12:00:00',
            'output' => 'Task completed successfully',
        ];

        $request = new StoreTaskRunRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with error_output.
     */
    public function test_validation_passes_with_error_output(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create();

        $data = [
            'task_id' => $task->id,
            'started_at' => '2025-01-15 12:00:00',
            'error_output' => 'Error: Connection timeout',
        ];

        $request = new StoreTaskRunRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with long output strings.
     */
    public function test_validation_passes_with_long_output_strings(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create();

        $data = [
            'task_id' => $task->id,
            'started_at' => '2025-01-15 12:00:00',
            'output' => str_repeat('Output line\n', 1000),
            'error_output' => str_repeat('Error line\n', 1000),
        ];

        $request = new StoreTaskRunRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes when duration_ms is zero.
     */
    public function test_validation_passes_when_duration_ms_is_zero(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create();

        $data = [
            'task_id' => $task->id,
            'started_at' => '2025-01-15 12:00:00',
            'duration_ms' => 0,
        ];

        $request = new StoreTaskRunRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when duration_ms is below minimum.
     */
    public function test_validation_fails_when_duration_ms_is_below_minimum(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create();

        $data = [
            'task_id' => $task->id,
            'started_at' => '2025-01-15 12:00:00',
            'duration_ms' => -1,
        ];

        $request = new StoreTaskRunRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('duration_ms', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with various duration_ms values.
     */
    public function test_validation_passes_with_various_duration_ms_values(): void
    {
        // Arrange
        $request = new StoreTaskRunRequest;
        $task = ServerScheduledTask::factory()->create();
        $durations = [0, 1, 1000, 60000, 3600000, 86400000];

        foreach ($durations as $duration) {
            $data = [
                'task_id' => $task->id,
                'started_at' => '2025-01-15 12:00:00',
                'duration_ms' => $duration,
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Duration {$duration} ms should be valid");
        }
    }

    /**
     * Test validation passes when duration_ms is not provided.
     */
    public function test_validation_passes_when_duration_ms_is_not_provided(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create();

        $data = [
            'task_id' => $task->id,
            'started_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreTaskRunRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with realistic task run data.
     */
    public function test_validation_passes_with_realistic_task_run_data(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create();

        $data = [
            'task_id' => $task->id,
            'started_at' => '2025-01-15 12:00:00',
            'completed_at' => '2025-01-15 12:00:05',
            'exit_code' => 0,
            'output' => "Starting backup...\nBacking up database...\nBackup completed successfully",
            'error_output' => '',
            'duration_ms' => 5432,
        ];

        $request = new StoreTaskRunRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation passes with failed task run data.
     */
    public function test_validation_passes_with_failed_task_run_data(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create();

        $data = [
            'task_id' => $task->id,
            'started_at' => '2025-01-15 12:00:00',
            'completed_at' => '2025-01-15 12:00:02',
            'exit_code' => 1,
            'output' => 'Starting backup...',
            'error_output' => 'Error: Permission denied on /var/backups',
            'duration_ms' => 2100,
        ];

        $request = new StoreTaskRunRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test authorize returns true.
     */
    public function test_authorize_returns_true(): void
    {
        // Arrange
        $request = new StoreTaskRunRequest;

        // Act
        $result = $request->authorize();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test custom attributes are defined.
     */
    public function test_custom_attributes_are_defined(): void
    {
        // Arrange
        $request = new StoreTaskRunRequest;

        // Act
        $attributes = $request->attributes();

        // Assert
        $this->assertIsArray($attributes);
        $this->assertArrayHasKey('task_id', $attributes);
        $this->assertArrayHasKey('started_at', $attributes);
        $this->assertArrayHasKey('completed_at', $attributes);
        $this->assertArrayHasKey('exit_code', $attributes);
        $this->assertArrayHasKey('output', $attributes);
        $this->assertArrayHasKey('error_output', $attributes);
        $this->assertArrayHasKey('duration_ms', $attributes);
    }

    /**
     * Test custom error messages are defined.
     */
    public function test_custom_error_logs_are_defined(): void
    {
        // Arrange
        $request = new StoreTaskRunRequest;

        // Act
        $messages = $request->messages();

        // Assert
        $this->assertIsArray($messages);
        $this->assertArrayHasKey('completed_at.after_or_equal', $messages);
        $this->assertArrayHasKey('task_id.exists', $messages);
    }
}
