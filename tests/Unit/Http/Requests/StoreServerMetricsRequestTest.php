<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StoreServerMetricsRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreServerMetricsRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test validation passes with all valid data.
     */
    public function test_validation_passes_with_all_valid_data(): void
    {
        // Arrange
        $data = [
            'cpu_usage' => 45.5,
            'memory_total_mb' => 8192,
            'memory_used_mb' => 4096,
            'memory_usage_percentage' => 50.0,
            'storage_total_gb' => 100,
            'storage_used_gb' => 50,
            'storage_usage_percentage' => 50.0,
            'collected_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreServerMetricsRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when cpu_usage is missing.
     */
    public function test_validation_fails_when_cpu_usage_is_missing(): void
    {
        // Arrange
        $data = [
            'memory_total_mb' => 8192,
            'memory_used_mb' => 4096,
            'memory_usage_percentage' => 50.0,
            'storage_total_gb' => 100,
            'storage_used_gb' => 50,
            'storage_usage_percentage' => 50.0,
            'collected_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreServerMetricsRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('cpu_usage', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when cpu_usage is below minimum.
     */
    public function test_validation_fails_when_cpu_usage_is_below_minimum(): void
    {
        // Arrange
        $data = [
            'cpu_usage' => -1,
            'memory_total_mb' => 8192,
            'memory_used_mb' => 4096,
            'memory_usage_percentage' => 50.0,
            'storage_total_gb' => 100,
            'storage_used_gb' => 50,
            'storage_usage_percentage' => 50.0,
            'collected_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreServerMetricsRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('cpu_usage', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when cpu_usage exceeds maximum.
     */
    public function test_validation_fails_when_cpu_usage_exceeds_maximum(): void
    {
        // Arrange
        $data = [
            'cpu_usage' => 101,
            'memory_total_mb' => 8192,
            'memory_used_mb' => 4096,
            'memory_usage_percentage' => 50.0,
            'storage_total_gb' => 100,
            'storage_used_gb' => 50,
            'storage_usage_percentage' => 50.0,
            'collected_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreServerMetricsRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('cpu_usage', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with cpu_usage at boundary values.
     */
    public function test_validation_passes_with_cpu_usage_at_boundary_values(): void
    {
        // Arrange
        $request = new StoreServerMetricsRequest;
        $boundaryValues = [0, 0.0, 50.5, 100, 100.0];

        foreach ($boundaryValues as $value) {
            $data = [
                'cpu_usage' => $value,
                'memory_total_mb' => 8192,
                'memory_used_mb' => 4096,
                'memory_usage_percentage' => 50.0,
                'storage_total_gb' => 100,
                'storage_used_gb' => 50,
                'storage_usage_percentage' => 50.0,
                'collected_at' => '2025-01-15 12:00:00',
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "CPU usage {$value} should be valid");
        }
    }

    /**
     * Test validation fails when memory_total_mb is missing.
     */
    public function test_validation_fails_when_memory_total_mb_is_missing(): void
    {
        // Arrange
        $data = [
            'cpu_usage' => 45.5,
            'memory_used_mb' => 4096,
            'memory_usage_percentage' => 50.0,
            'storage_total_gb' => 100,
            'storage_used_gb' => 50,
            'storage_usage_percentage' => 50.0,
            'collected_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreServerMetricsRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('memory_total_mb', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when memory_total_mb is below minimum.
     */
    public function test_validation_fails_when_memory_total_mb_is_below_minimum(): void
    {
        // Arrange
        $data = [
            'cpu_usage' => 45.5,
            'memory_total_mb' => -1,
            'memory_used_mb' => 4096,
            'memory_usage_percentage' => 50.0,
            'storage_total_gb' => 100,
            'storage_used_gb' => 50,
            'storage_usage_percentage' => 50.0,
            'collected_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreServerMetricsRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('memory_total_mb', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with memory_total_mb at zero.
     */
    public function test_validation_passes_with_memory_total_mb_at_zero(): void
    {
        // Arrange
        $data = [
            'cpu_usage' => 45.5,
            'memory_total_mb' => 0,
            'memory_used_mb' => 0,
            'memory_usage_percentage' => 50.0,
            'storage_total_gb' => 100,
            'storage_used_gb' => 50,
            'storage_usage_percentage' => 50.0,
            'collected_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreServerMetricsRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->fails());
    }

    /**
     * Test validation fails when memory_used_mb is missing.
     */
    public function test_validation_fails_when_memory_used_mb_is_missing(): void
    {
        // Arrange
        $data = [
            'cpu_usage' => 45.5,
            'memory_total_mb' => 8192,
            'memory_usage_percentage' => 50.0,
            'storage_total_gb' => 100,
            'storage_used_gb' => 50,
            'storage_usage_percentage' => 50.0,
            'collected_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreServerMetricsRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('memory_used_mb', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when memory_used_mb is below minimum.
     */
    public function test_validation_fails_when_memory_used_mb_is_below_minimum(): void
    {
        // Arrange
        $data = [
            'cpu_usage' => 45.5,
            'memory_total_mb' => 8192,
            'memory_used_mb' => -1,
            'memory_usage_percentage' => 50.0,
            'storage_total_gb' => 100,
            'storage_used_gb' => 50,
            'storage_usage_percentage' => 50.0,
            'collected_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreServerMetricsRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('memory_used_mb', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when memory_usage_percentage is missing.
     */
    public function test_validation_fails_when_memory_usage_percentage_is_missing(): void
    {
        // Arrange
        $data = [
            'cpu_usage' => 45.5,
            'memory_total_mb' => 8192,
            'memory_used_mb' => 4096,
            'storage_total_gb' => 100,
            'storage_used_gb' => 50,
            'storage_usage_percentage' => 50.0,
            'collected_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreServerMetricsRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('memory_usage_percentage', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when memory_usage_percentage exceeds maximum.
     */
    public function test_validation_fails_when_memory_usage_percentage_exceeds_maximum(): void
    {
        // Arrange
        $data = [
            'cpu_usage' => 45.5,
            'memory_total_mb' => 8192,
            'memory_used_mb' => 4096,
            'memory_usage_percentage' => 101,
            'storage_total_gb' => 100,
            'storage_used_gb' => 50,
            'storage_usage_percentage' => 50.0,
            'collected_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreServerMetricsRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('memory_usage_percentage', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with memory_usage_percentage at boundary values.
     */
    public function test_validation_passes_with_memory_usage_percentage_at_boundary_values(): void
    {
        // Arrange
        $request = new StoreServerMetricsRequest;
        $boundaryValues = [0, 0.0, 50.5, 100, 100.0];

        foreach ($boundaryValues as $value) {
            $data = [
                'cpu_usage' => 45.5,
                'memory_total_mb' => 8192,
                'memory_used_mb' => 4096,
                'memory_usage_percentage' => $value,
                'storage_total_gb' => 100,
                'storage_used_gb' => 50,
                'storage_usage_percentage' => 50.0,
                'collected_at' => '2025-01-15 12:00:00',
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Memory usage percentage {$value} should be valid");
        }
    }

    /**
     * Test validation fails when storage_total_gb is missing.
     */
    public function test_validation_fails_when_storage_total_gb_is_missing(): void
    {
        // Arrange
        $data = [
            'cpu_usage' => 45.5,
            'memory_total_mb' => 8192,
            'memory_used_mb' => 4096,
            'memory_usage_percentage' => 50.0,
            'storage_used_gb' => 50,
            'storage_usage_percentage' => 50.0,
            'collected_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreServerMetricsRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('storage_total_gb', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when storage_total_gb is below minimum.
     */
    public function test_validation_fails_when_storage_total_gb_is_below_minimum(): void
    {
        // Arrange
        $data = [
            'cpu_usage' => 45.5,
            'memory_total_mb' => 8192,
            'memory_used_mb' => 4096,
            'memory_usage_percentage' => 50.0,
            'storage_total_gb' => -1,
            'storage_used_gb' => 50,
            'storage_usage_percentage' => 50.0,
            'collected_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreServerMetricsRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('storage_total_gb', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when storage_used_gb is missing.
     */
    public function test_validation_fails_when_storage_used_gb_is_missing(): void
    {
        // Arrange
        $data = [
            'cpu_usage' => 45.5,
            'memory_total_mb' => 8192,
            'memory_used_mb' => 4096,
            'memory_usage_percentage' => 50.0,
            'storage_total_gb' => 100,
            'storage_usage_percentage' => 50.0,
            'collected_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreServerMetricsRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('storage_used_gb', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when storage_used_gb is below minimum.
     */
    public function test_validation_fails_when_storage_used_gb_is_below_minimum(): void
    {
        // Arrange
        $data = [
            'cpu_usage' => 45.5,
            'memory_total_mb' => 8192,
            'memory_used_mb' => 4096,
            'memory_usage_percentage' => 50.0,
            'storage_total_gb' => 100,
            'storage_used_gb' => -1,
            'storage_usage_percentage' => 50.0,
            'collected_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreServerMetricsRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('storage_used_gb', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when storage_usage_percentage is missing.
     */
    public function test_validation_fails_when_storage_usage_percentage_is_missing(): void
    {
        // Arrange
        $data = [
            'cpu_usage' => 45.5,
            'memory_total_mb' => 8192,
            'memory_used_mb' => 4096,
            'memory_usage_percentage' => 50.0,
            'storage_total_gb' => 100,
            'storage_used_gb' => 50,
            'collected_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreServerMetricsRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('storage_usage_percentage', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when storage_usage_percentage exceeds maximum.
     */
    public function test_validation_fails_when_storage_usage_percentage_exceeds_maximum(): void
    {
        // Arrange
        $data = [
            'cpu_usage' => 45.5,
            'memory_total_mb' => 8192,
            'memory_used_mb' => 4096,
            'memory_usage_percentage' => 50.0,
            'storage_total_gb' => 100,
            'storage_used_gb' => 50,
            'storage_usage_percentage' => 101,
            'collected_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreServerMetricsRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('storage_usage_percentage', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with storage_usage_percentage at boundary values.
     */
    public function test_validation_passes_with_storage_usage_percentage_at_boundary_values(): void
    {
        // Arrange
        $request = new StoreServerMetricsRequest;
        $boundaryValues = [0, 0.0, 50.5, 100, 100.0];

        foreach ($boundaryValues as $value) {
            $data = [
                'cpu_usage' => 45.5,
                'memory_total_mb' => 8192,
                'memory_used_mb' => 4096,
                'memory_usage_percentage' => 50.0,
                'storage_total_gb' => 100,
                'storage_used_gb' => 50,
                'storage_usage_percentage' => $value,
                'collected_at' => '2025-01-15 12:00:00',
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Storage usage percentage {$value} should be valid");
        }
    }

    /**
     * Test validation fails when collected_at is missing.
     */
    public function test_validation_fails_when_collected_at_is_missing(): void
    {
        // Arrange
        $data = [
            'cpu_usage' => 45.5,
            'memory_total_mb' => 8192,
            'memory_used_mb' => 4096,
            'memory_usage_percentage' => 50.0,
            'storage_total_gb' => 100,
            'storage_used_gb' => 50,
            'storage_usage_percentage' => 50.0,
        ];

        $request = new StoreServerMetricsRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('collected_at', $validator->errors()->toArray());
    }

    /**
     * Test validation fails when collected_at is invalid date.
     */
    public function test_validation_fails_when_collected_at_is_invalid_date(): void
    {
        // Arrange
        $data = [
            'cpu_usage' => 45.5,
            'memory_total_mb' => 8192,
            'memory_used_mb' => 4096,
            'memory_usage_percentage' => 50.0,
            'storage_total_gb' => 100,
            'storage_used_gb' => 50,
            'storage_usage_percentage' => 50.0,
            'collected_at' => 'invalid-date',
        ];

        $request = new StoreServerMetricsRequest;

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('collected_at', $validator->errors()->toArray());
    }

    /**
     * Test validation passes with various valid date formats.
     */
    public function test_validation_passes_with_various_valid_date_formats(): void
    {
        // Arrange
        $request = new StoreServerMetricsRequest;
        $validDates = [
            '2025-01-15 12:00:00',
            '2025-01-15T12:00:00Z',
            '2025-01-15',
            '2025-01-15 00:00:00',
        ];

        foreach ($validDates as $date) {
            $data = [
                'cpu_usage' => 45.5,
                'memory_total_mb' => 8192,
                'memory_used_mb' => 4096,
                'memory_usage_percentage' => 50.0,
                'storage_total_gb' => 100,
                'storage_used_gb' => 50,
                'storage_usage_percentage' => 50.0,
                'collected_at' => $date,
            ];

            // Act
            $validator = Validator::make($data, $request->rules());

            // Assert
            $this->assertFalse($validator->fails(), "Date format '{$date}' should be valid");
        }
    }

    /**
     * Test validation passes with large metric values.
     */
    public function test_validation_passes_with_large_metric_values(): void
    {
        // Arrange
        $data = [
            'cpu_usage' => 99.9,
            'memory_total_mb' => 131072, // 128 GB
            'memory_used_mb' => 65536, // 64 GB
            'memory_usage_percentage' => 50.0,
            'storage_total_gb' => 10000, // 10 TB
            'storage_used_gb' => 5000, // 5 TB
            'storage_usage_percentage' => 50.0,
            'collected_at' => '2025-01-15 12:00:00',
        ];

        $request = new StoreServerMetricsRequest;

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
        $request = new StoreServerMetricsRequest;

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
        $request = new StoreServerMetricsRequest;

        // Act
        $attributes = $request->attributes();

        // Assert
        $this->assertIsArray($attributes);
        $this->assertArrayHasKey('cpu_usage', $attributes);
        $this->assertArrayHasKey('memory_total_mb', $attributes);
        $this->assertArrayHasKey('memory_used_mb', $attributes);
        $this->assertArrayHasKey('memory_usage_percentage', $attributes);
        $this->assertArrayHasKey('storage_total_gb', $attributes);
        $this->assertArrayHasKey('storage_used_gb', $attributes);
        $this->assertArrayHasKey('storage_usage_percentage', $attributes);
        $this->assertArrayHasKey('collected_at', $attributes);
    }

    /**
     * Test custom error messages are defined.
     */
    public function test_custom_error_messages_are_defined(): void
    {
        // Arrange
        $request = new StoreServerMetricsRequest;

        // Act
        $messages = $request->messages();

        // Assert
        $this->assertIsArray($messages);
        $this->assertArrayHasKey('cpu_usage.max', $messages);
        $this->assertArrayHasKey('memory_usage_percentage.max', $messages);
        $this->assertArrayHasKey('storage_usage_percentage.max', $messages);
    }
}
