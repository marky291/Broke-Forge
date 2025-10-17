<?php

namespace Tests\Unit\Models;

use App\Events\ServerUpdated;
use App\Models\Server;
use App\Models\ServerScheduledTask;
use App\Models\ServerScheduledTaskRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ServerScheduledTaskRunTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that wasSuccessful accessor returns true when exit_code is 0.
     */
    public function test_was_successful_accessor_returns_true_when_exit_code_is_zero(): void
    {
        // Arrange
        $run = ServerScheduledTaskRun::factory()->successful()->create();

        // Act & Assert
        $this->assertTrue($run->was_successful);
    }

    /**
     * Test that wasSuccessful accessor returns false when exit_code is non-zero.
     */
    public function test_was_successful_accessor_returns_false_when_exit_code_is_non_zero(): void
    {
        // Arrange
        $run = ServerScheduledTaskRun::factory()->failed()->create();

        // Act & Assert
        $this->assertFalse($run->was_successful);
    }

    /**
     * Test that wasSuccessful accessor returns false when exit_code is null.
     */
    public function test_was_successful_accessor_returns_false_when_exit_code_is_null(): void
    {
        // Arrange
        $run = ServerScheduledTaskRun::factory()->running()->create();

        // Act & Assert
        $this->assertFalse($run->was_successful);
    }

    /**
     * Test that failed method returns false when exit_code is 0.
     */
    public function test_failed_returns_false_when_exit_code_is_zero(): void
    {
        // Arrange
        $run = ServerScheduledTaskRun::factory()->successful()->create();

        // Act & Assert
        $this->assertFalse($run->failed());
    }

    /**
     * Test that failed method returns true when exit_code is non-zero.
     */
    public function test_failed_returns_true_when_exit_code_is_non_zero(): void
    {
        // Arrange
        $run = ServerScheduledTaskRun::factory()->create(['exit_code' => 1]);

        // Act & Assert
        $this->assertTrue($run->failed());
    }

    /**
     * Test that failed method returns true when exit_code is other non-zero values.
     */
    public function test_failed_returns_true_for_various_non_zero_exit_codes(): void
    {
        // Test various exit codes
        foreach ([1, 2, 126, 127, 130, 255] as $exitCode) {
            // Arrange
            $run = ServerScheduledTaskRun::factory()->create(['exit_code' => $exitCode]);

            // Act & Assert
            $this->assertTrue($run->failed(), "Exit code {$exitCode} should indicate failure");
        }
    }

    /**
     * Test that failed method handles null exit_code.
     */
    public function test_failed_returns_true_when_exit_code_is_null(): void
    {
        // Arrange - running task with null exit_code
        $run = ServerScheduledTaskRun::factory()->running()->create();

        // Act & Assert
        $this->assertTrue($run->failed());
    }

    /**
     * Test that run belongs to a server.
     */
    public function test_run_belongs_to_server(): void
    {
        // Arrange
        $server = Server::factory()->create(['vanity_name' => 'test-server']);
        $run = ServerScheduledTaskRun::factory()->create(['server_id' => $server->id]);

        // Act
        $relatedServer = $run->server;

        // Assert
        $this->assertInstanceOf(Server::class, $relatedServer);
        $this->assertEquals($server->id, $relatedServer->id);
        $this->assertEquals('test-server', $relatedServer->vanity_name);
    }

    /**
     * Test that run belongs to a task.
     */
    public function test_run_belongs_to_task(): void
    {
        // Arrange
        $task = ServerScheduledTask::factory()->create(['name' => 'Backup Database']);
        $run = ServerScheduledTaskRun::factory()->create(['server_scheduled_task_id' => $task->id]);

        // Act
        $relatedTask = $run->task;

        // Assert
        $this->assertInstanceOf(ServerScheduledTask::class, $relatedTask);
        $this->assertEquals($task->id, $relatedTask->id);
        $this->assertEquals('Backup Database', $relatedTask->name);
    }

    /**
     * Test that started_at is cast to datetime.
     */
    public function test_started_at_is_cast_to_datetime(): void
    {
        // Arrange
        $run = ServerScheduledTaskRun::factory()->create(['started_at' => now()]);

        // Act & Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $run->started_at);
    }

    /**
     * Test that completed_at is cast to datetime.
     */
    public function test_completed_at_is_cast_to_datetime(): void
    {
        // Arrange
        $run = ServerScheduledTaskRun::factory()->create(['completed_at' => now()]);

        // Act & Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $run->completed_at);
    }

    /**
     * Test that exit_code is cast to integer.
     */
    public function test_exit_code_is_cast_to_integer(): void
    {
        // Arrange
        $run = ServerScheduledTaskRun::factory()->create(['exit_code' => '0']);

        // Act
        $exitCode = $run->exit_code;

        // Assert
        $this->assertIsInt($exitCode);
        $this->assertEquals(0, $exitCode);
    }

    /**
     * Test that duration_ms is cast to integer.
     */
    public function test_duration_ms_is_cast_to_integer(): void
    {
        // Arrange
        $run = ServerScheduledTaskRun::factory()->create(['duration_ms' => '5000']);

        // Act
        $duration = $run->duration_ms;

        // Assert
        $this->assertIsInt($duration);
        $this->assertEquals(5000, $duration);
    }

    /**
     * Test that was_successful is appended to array output.
     */
    public function test_was_successful_is_appended_to_array_output(): void
    {
        // Arrange
        $run = ServerScheduledTaskRun::factory()->successful()->create();

        // Act
        $array = $run->toArray();

        // Assert
        $this->assertArrayHasKey('was_successful', $array);
        $this->assertTrue($array['was_successful']);
    }

    /**
     * Test that was_successful is appended as false for failed runs.
     */
    public function test_was_successful_is_appended_as_false_for_failed_runs(): void
    {
        // Arrange
        $run = ServerScheduledTaskRun::factory()->failed()->create();

        // Act
        $array = $run->toArray();

        // Assert
        $this->assertArrayHasKey('was_successful', $array);
        $this->assertFalse($array['was_successful']);
    }

    /**
     * Test that ServerUpdated event is dispatched when run is created.
     */
    public function test_server_updated_event_dispatched_on_run_created(): void
    {
        // Arrange
        Event::fake([ServerUpdated::class]);
        $server = Server::factory()->create();

        // Act
        ServerScheduledTaskRun::factory()->create(['server_id' => $server->id]);

        // Assert
        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    /**
     * Test that ServerUpdated event is dispatched when run is updated.
     */
    public function test_server_updated_event_dispatched_on_run_updated(): void
    {
        // Arrange
        $run = ServerScheduledTaskRun::factory()->running()->create();
        Event::fake([ServerUpdated::class]);

        // Act
        $run->update(['exit_code' => 0, 'completed_at' => now()]);

        // Assert
        Event::assertDispatched(ServerUpdated::class, function ($event) use ($run) {
            return $event->serverId === $run->server_id;
        });
    }

    /**
     * Test that factory creates valid run with all required fields.
     */
    public function test_factory_creates_valid_run(): void
    {
        // Act
        $run = ServerScheduledTaskRun::factory()->create();

        // Assert
        $this->assertInstanceOf(ServerScheduledTaskRun::class, $run);
        $this->assertNotNull($run->server_id);
        $this->assertNotNull($run->server_scheduled_task_id);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->exit_code);
    }

    /**
     * Test that factory successful state creates successful run.
     */
    public function test_factory_successful_state_creates_successful_run(): void
    {
        // Act
        $run = ServerScheduledTaskRun::factory()->successful()->create();

        // Assert
        $this->assertEquals(0, $run->exit_code);
        $this->assertTrue($run->was_successful);
        $this->assertFalse($run->failed());
        $this->assertNotNull($run->output);
        $this->assertNull($run->error_output);
    }

    /**
     * Test that factory failed state creates failed run.
     */
    public function test_factory_failed_state_creates_failed_run(): void
    {
        // Act
        $run = ServerScheduledTaskRun::factory()->failed()->create();

        // Assert
        $this->assertNotEquals(0, $run->exit_code);
        $this->assertFalse($run->was_successful);
        $this->assertTrue($run->failed());
        $this->assertNull($run->output);
        $this->assertNotNull($run->error_output);
    }

    /**
     * Test that factory running state creates running run.
     */
    public function test_factory_running_state_creates_running_run(): void
    {
        // Act
        $run = ServerScheduledTaskRun::factory()->running()->create();

        // Assert
        $this->assertNull($run->exit_code);
        $this->assertNull($run->completed_at);
        $this->assertNull($run->output);
        $this->assertNull($run->error_output);
        $this->assertNull($run->duration_ms);
        $this->assertNotNull($run->started_at);
    }

    /**
     * Test that successful and failed methods are mutually exclusive.
     */
    public function test_successful_and_failed_are_mutually_exclusive(): void
    {
        // Arrange & Act - successful run
        $successfulRun = ServerScheduledTaskRun::factory()->successful()->create();

        // Assert
        $this->assertTrue($successfulRun->was_successful);
        $this->assertFalse($successfulRun->failed());

        // Arrange & Act - failed run
        $failedRun = ServerScheduledTaskRun::factory()->failed()->create();

        // Assert
        $this->assertFalse($failedRun->was_successful);
        $this->assertTrue($failedRun->failed());
    }
}
