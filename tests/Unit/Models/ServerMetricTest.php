<?php

namespace Tests\Unit\Models;

use App\Events\ServerUpdated;
use App\Models\Server;
use App\Models\ServerMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ServerMetricTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test server metric belongs to a server.
     */
    public function test_belongs_to_server(): void
    {
        // Arrange
        Event::fake();
        $server = Server::factory()->create();
        $metric = ServerMetric::factory()->create([
            'server_id' => $server->id,
        ]);

        // Act
        $result = $metric->server;

        // Assert
        $this->assertInstanceOf(Server::class, $result);
        $this->assertEquals($server->id, $result->id);
    }

    /**
     * Test cpu usage is cast to decimal.
     */
    public function test_cpu_usage_is_cast_to_decimal(): void
    {
        // Arrange
        Event::fake();
        $metric = ServerMetric::factory()->create([
            'cpu_usage' => 45.67,
        ]);

        // Act
        $cpuUsage = $metric->cpu_usage;

        // Assert
        $this->assertIsString($cpuUsage);
        $this->assertEquals('45.67', $cpuUsage);
    }

    /**
     * Test memory usage percentage is cast to decimal.
     */
    public function test_memory_usage_percentage_is_cast_to_decimal(): void
    {
        // Arrange
        Event::fake();
        $metric = ServerMetric::factory()->create([
            'memory_usage_percentage' => 78.92,
        ]);

        // Act
        $memoryUsage = $metric->memory_usage_percentage;

        // Assert
        $this->assertIsString($memoryUsage);
        $this->assertEquals('78.92', $memoryUsage);
    }

    /**
     * Test storage usage percentage is cast to decimal.
     */
    public function test_storage_usage_percentage_is_cast_to_decimal(): void
    {
        // Arrange
        Event::fake();
        $metric = ServerMetric::factory()->create([
            'storage_usage_percentage' => 23.45,
        ]);

        // Act
        $storageUsage = $metric->storage_usage_percentage;

        // Assert
        $this->assertIsString($storageUsage);
        $this->assertEquals('23.45', $storageUsage);
    }

    /**
     * Test collected at is cast to datetime.
     */
    public function test_collected_at_is_cast_to_datetime(): void
    {
        // Arrange
        Event::fake();
        $metric = ServerMetric::factory()->create([
            'collected_at' => '2025-10-17 12:00:00',
        ]);

        // Act
        $collectedAt = $metric->collected_at;

        // Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $collectedAt);
        $this->assertEquals('2025-10-17 12:00:00', $collectedAt->format('Y-m-d H:i:s'));
    }

    /**
     * Test factory creates metric with correct attributes.
     */
    public function test_factory_creates_metric_with_correct_attributes(): void
    {
        // Act
        Event::fake();
        $metric = ServerMetric::factory()->create();

        // Assert
        $this->assertNotNull($metric->server_id);
        $this->assertNotNull($metric->cpu_usage);
        $this->assertNotNull($metric->memory_total_mb);
        $this->assertNotNull($metric->memory_used_mb);
        $this->assertNotNull($metric->memory_usage_percentage);
        $this->assertNotNull($metric->storage_total_gb);
        $this->assertNotNull($metric->storage_used_gb);
        $this->assertNotNull($metric->storage_usage_percentage);
        $this->assertNotNull($metric->collected_at);
    }

    /**
     * Test factory calculates memory percentage correctly.
     */
    public function test_factory_calculates_memory_percentage_correctly(): void
    {
        // Act
        Event::fake();
        $metric = ServerMetric::factory()->create();

        // Assert
        $expectedPercentage = round(($metric->memory_used_mb / $metric->memory_total_mb) * 100, 2);
        $this->assertEquals($expectedPercentage, (float) $metric->memory_usage_percentage);
    }

    /**
     * Test factory calculates storage percentage correctly.
     */
    public function test_factory_calculates_storage_percentage_correctly(): void
    {
        // Act
        Event::fake();
        $metric = ServerMetric::factory()->create();

        // Assert
        $expectedPercentage = round(($metric->storage_used_gb / $metric->storage_total_gb) * 100, 2);
        $this->assertEquals($expectedPercentage, (float) $metric->storage_usage_percentage);
    }

    /**
     * Test metric can be created with zero cpu usage.
     */
    public function test_metric_can_be_created_with_zero_cpu_usage(): void
    {
        // Arrange & Act
        Event::fake();
        $metric = ServerMetric::factory()->create([
            'cpu_usage' => 0.00,
        ]);

        // Assert
        $this->assertEquals('0.00', $metric->cpu_usage);
    }

    /**
     * Test metric can be created with 100 percent cpu usage.
     */
    public function test_metric_can_be_created_with_100_percent_cpu_usage(): void
    {
        // Arrange & Act
        Event::fake();
        $metric = ServerMetric::factory()->create([
            'cpu_usage' => 100.00,
        ]);

        // Assert
        $this->assertEquals('100.00', $metric->cpu_usage);
    }

    /**
     * Test metric can store large memory values.
     */
    public function test_metric_can_store_large_memory_values(): void
    {
        // Arrange & Act
        Event::fake();
        $metric = ServerMetric::factory()->create([
            'memory_total_mb' => 128000,
            'memory_used_mb' => 64000,
            'memory_usage_percentage' => 50.00,
        ]);

        // Assert
        $this->assertEquals(128000, $metric->memory_total_mb);
        $this->assertEquals(64000, $metric->memory_used_mb);
        $this->assertEquals('50.00', $metric->memory_usage_percentage);
    }

    /**
     * Test metric can store large storage values.
     */
    public function test_metric_can_store_large_storage_values(): void
    {
        // Arrange & Act
        Event::fake();
        $metric = ServerMetric::factory()->create([
            'storage_total_gb' => 2000,
            'storage_used_gb' => 1500,
            'storage_usage_percentage' => 75.00,
        ]);

        // Assert
        $this->assertEquals(2000, $metric->storage_total_gb);
        $this->assertEquals(1500, $metric->storage_used_gb);
        $this->assertEquals('75.00', $metric->storage_usage_percentage);
    }

    /**
     * Test fillable attributes are mass assignable.
     */
    public function test_fillable_attributes_are_mass_assignable(): void
    {
        // Arrange
        Event::fake();
        $server = Server::factory()->create();

        // Act
        $metric = ServerMetric::create([
            'server_id' => $server->id,
            'cpu_usage' => 35.25,
            'memory_total_mb' => 8000,
            'memory_used_mb' => 4000,
            'memory_usage_percentage' => 50.00,
            'storage_total_gb' => 100,
            'storage_used_gb' => 40,
            'storage_usage_percentage' => 40.00,
            'collected_at' => now(),
        ]);

        // Assert
        $this->assertDatabaseHas('server_metrics', [
            'server_id' => $server->id,
            'cpu_usage' => 35.25,
            'memory_total_mb' => 8000,
        ]);
    }

    /**
     * Test created event dispatches ServerUpdated event.
     */
    public function test_created_event_dispatches_server_updated_event(): void
    {
        // Arrange
        Event::fake([ServerUpdated::class]);
        $server = Server::factory()->create();

        // Act
        ServerMetric::factory()->create(['server_id' => $server->id]);

        // Assert
        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    /**
     * Test multiple metrics can belong to same server.
     */
    public function test_multiple_metrics_can_belong_to_same_server(): void
    {
        // Arrange
        Event::fake();
        $server = Server::factory()->create();

        // Act
        $metric1 = ServerMetric::factory()->create([
            'server_id' => $server->id,
            'collected_at' => now()->subHours(2),
        ]);
        $metric2 = ServerMetric::factory()->create([
            'server_id' => $server->id,
            'collected_at' => now()->subHour(),
        ]);
        $metric3 = ServerMetric::factory()->create([
            'server_id' => $server->id,
            'collected_at' => now(),
        ]);

        // Assert
        $this->assertEquals($server->id, $metric1->server_id);
        $this->assertEquals($server->id, $metric2->server_id);
        $this->assertEquals($server->id, $metric3->server_id);
        $this->assertCount(3, ServerMetric::where('server_id', $server->id)->get());
    }

    /**
     * Test metric can be deleted.
     */
    public function test_metric_can_be_deleted(): void
    {
        // Arrange
        Event::fake();
        $metric = ServerMetric::factory()->create();
        $metricId = $metric->id;

        // Act
        $metric->delete();

        // Assert
        $this->assertDatabaseMissing('server_metrics', [
            'id' => $metricId,
        ]);
    }

    /**
     * Test metric relationship can be eagerly loaded.
     */
    public function test_metric_relationship_can_be_eagerly_loaded(): void
    {
        // Arrange
        Event::fake();
        $server = Server::factory()->create();
        ServerMetric::factory()->create(['server_id' => $server->id]);

        // Act
        $metric = ServerMetric::with('server')->first();

        // Assert
        $this->assertTrue($metric->relationLoaded('server'));
        $this->assertInstanceOf(Server::class, $metric->server);
    }

    /**
     * Test metric stores timestamp accurately.
     */
    public function test_metric_stores_timestamp_accurately(): void
    {
        // Arrange
        Event::fake();
        $timestamp = now()->setTime(14, 30, 45);

        // Act
        $metric = ServerMetric::factory()->create([
            'collected_at' => $timestamp,
        ]);

        // Assert
        $this->assertEquals($timestamp->format('Y-m-d H:i:s'), $metric->collected_at->format('Y-m-d H:i:s'));
    }

    /**
     * Test metric handles decimal precision correctly.
     */
    public function test_metric_handles_decimal_precision_correctly(): void
    {
        // Arrange & Act
        Event::fake();
        $metric = ServerMetric::factory()->create([
            'cpu_usage' => 45.678,
            'memory_usage_percentage' => 67.891,
            'storage_usage_percentage' => 23.456,
        ]);

        // Assert - should be rounded to 2 decimal places
        $this->assertEquals('45.68', $metric->cpu_usage);
        $this->assertEquals('67.89', $metric->memory_usage_percentage);
        $this->assertEquals('23.46', $metric->storage_usage_percentage);
    }
}
