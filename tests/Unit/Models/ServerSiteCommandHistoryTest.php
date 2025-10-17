<?php

namespace Tests\Unit\Models;

use App\Events\ServerSiteUpdated;
use App\Models\Server;
use App\Models\ServerSite;
use App\Models\ServerSiteCommandHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ServerSiteCommandHistoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test command history belongs to a server.
     */
    public function test_belongs_to_server(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);
        $commandHistory = ServerSiteCommandHistory::factory()->create([
            'server_id' => $server->id,
            'server_site_id' => $site->id,
        ]);

        // Act
        $result = $commandHistory->server;

        // Assert
        $this->assertInstanceOf(Server::class, $result);
        $this->assertEquals($server->id, $result->id);
    }

    /**
     * Test command history belongs to a site.
     */
    public function test_belongs_to_site(): void
    {
        // Arrange
        $site = ServerSite::factory()->create();
        $commandHistory = ServerSiteCommandHistory::factory()->create([
            'server_site_id' => $site->id,
        ]);

        // Act
        $result = $commandHistory->site;

        // Assert
        $this->assertInstanceOf(ServerSite::class, $result);
        $this->assertEquals($site->id, $result->id);
    }

    /**
     * Test success is cast to boolean.
     */
    public function test_success_is_cast_to_boolean(): void
    {
        // Arrange
        $commandHistory = ServerSiteCommandHistory::factory()->create([
            'success' => 1,
        ]);

        // Act
        $success = $commandHistory->success;

        // Assert
        $this->assertIsBool($success);
        $this->assertTrue($success);
    }

    /**
     * Test success false is cast to boolean.
     */
    public function test_success_false_is_cast_to_boolean(): void
    {
        // Arrange
        $commandHistory = ServerSiteCommandHistory::factory()->failed()->create();

        // Act
        $success = $commandHistory->success;

        // Assert
        $this->assertIsBool($success);
        $this->assertFalse($success);
    }

    /**
     * Test exit code is cast to integer.
     */
    public function test_exit_code_is_cast_to_integer(): void
    {
        // Arrange
        $commandHistory = ServerSiteCommandHistory::factory()->create([
            'exit_code' => '0',
        ]);

        // Act
        $exitCode = $commandHistory->exit_code;

        // Assert
        $this->assertIsInt($exitCode);
        $this->assertEquals(0, $exitCode);
    }

    /**
     * Test exit code for failed command is cast to integer.
     */
    public function test_exit_code_for_failed_command_is_cast_to_integer(): void
    {
        // Arrange
        $commandHistory = ServerSiteCommandHistory::factory()->create([
            'exit_code' => '1',
        ]);

        // Act
        $exitCode = $commandHistory->exit_code;

        // Assert
        $this->assertIsInt($exitCode);
        $this->assertEquals(1, $exitCode);
    }

    /**
     * Test duration ms is cast to integer.
     */
    public function test_duration_ms_is_cast_to_integer(): void
    {
        // Arrange
        $commandHistory = ServerSiteCommandHistory::factory()->create([
            'duration_ms' => '1234',
        ]);

        // Act
        $durationMs = $commandHistory->duration_ms;

        // Assert
        $this->assertIsInt($durationMs);
        $this->assertEquals(1234, $durationMs);
    }

    /**
     * Test custom table name is correct.
     */
    public function test_uses_custom_table_name(): void
    {
        // Arrange
        $commandHistory = new ServerSiteCommandHistory;

        // Act
        $tableName = $commandHistory->getTable();

        // Assert
        $this->assertEquals('server_site_command_history', $tableName);
    }

    /**
     * Test model dispatches ServerSiteUpdated event on creation.
     */
    public function test_dispatches_server_site_updated_event_on_creation(): void
    {
        // Arrange
        Event::fake([ServerSiteUpdated::class]);
        $site = ServerSite::factory()->create();

        // Act
        ServerSiteCommandHistory::factory()->create([
            'server_site_id' => $site->id,
        ]);

        // Assert
        Event::assertDispatched(ServerSiteUpdated::class, function ($event) use ($site) {
            return $event->siteId === $site->id;
        });
    }

    /**
     * Test factory creates command history with correct attributes.
     */
    public function test_factory_creates_command_history_with_correct_attributes(): void
    {
        // Act
        $commandHistory = ServerSiteCommandHistory::factory()->create();

        // Assert
        $this->assertNotNull($commandHistory->server_id);
        $this->assertNotNull($commandHistory->server_site_id);
        $this->assertNotNull($commandHistory->command);
        $this->assertNotNull($commandHistory->output);
        $this->assertNull($commandHistory->error_output);
        $this->assertEquals(0, $commandHistory->exit_code);
        $this->assertNotNull($commandHistory->duration_ms);
        $this->assertTrue($commandHistory->success);
    }

    /**
     * Test factory failed state creates failed command.
     */
    public function test_factory_failed_state(): void
    {
        // Act
        $commandHistory = ServerSiteCommandHistory::factory()->failed()->create();

        // Assert
        $this->assertFalse($commandHistory->success);
        $this->assertNotNull($commandHistory->error_output);
        $this->assertGreaterThan(0, $commandHistory->exit_code);
    }

    /**
     * Test factory no output state creates command with no output.
     */
    public function test_factory_no_output_state(): void
    {
        // Act
        $commandHistory = ServerSiteCommandHistory::factory()->noOutput()->create();

        // Assert
        $this->assertEquals('', $commandHistory->output);
        $this->assertNull($commandHistory->error_output);
        $this->assertTrue($commandHistory->success);
        $this->assertEquals(0, $commandHistory->exit_code);
    }

    /**
     * Test factory slow state creates slow command.
     */
    public function test_factory_slow_state(): void
    {
        // Act
        $commandHistory = ServerSiteCommandHistory::factory()->slow()->create();

        // Assert
        $this->assertGreaterThanOrEqual(30000, $commandHistory->duration_ms);
    }

    /**
     * Test command history can store command output.
     */
    public function test_can_store_command_output(): void
    {
        // Arrange
        $output = "Migration ran successfully\nAll tables created";
        $commandHistory = ServerSiteCommandHistory::factory()->create([
            'output' => $output,
        ]);

        // Act
        $storedOutput = $commandHistory->output;

        // Assert
        $this->assertEquals($output, $storedOutput);
    }

    /**
     * Test command history can store error output.
     */
    public function test_can_store_error_output(): void
    {
        // Arrange
        $errorOutput = 'Error: Command failed with exception';
        $commandHistory = ServerSiteCommandHistory::factory()->create([
            'error_output' => $errorOutput,
        ]);

        // Act
        $storedErrorOutput = $commandHistory->error_output;

        // Assert
        $this->assertEquals($errorOutput, $storedErrorOutput);
    }

    /**
     * Test successful command has exit code 0.
     */
    public function test_successful_command_has_exit_code_zero(): void
    {
        // Arrange
        $commandHistory = ServerSiteCommandHistory::factory()->create([
            'success' => true,
            'exit_code' => 0,
        ]);

        // Act & Assert
        $this->assertTrue($commandHistory->success);
        $this->assertEquals(0, $commandHistory->exit_code);
    }

    /**
     * Test failed command has non-zero exit code.
     */
    public function test_failed_command_has_non_zero_exit_code(): void
    {
        // Arrange
        $commandHistory = ServerSiteCommandHistory::factory()->create([
            'success' => false,
            'exit_code' => 1,
        ]);

        // Act & Assert
        $this->assertFalse($commandHistory->success);
        $this->assertGreaterThan(0, $commandHistory->exit_code);
    }

    /**
     * Test duration ms tracks execution time.
     */
    public function test_duration_ms_tracks_execution_time(): void
    {
        // Arrange
        $durationMs = 3500; // 3.5 seconds
        $commandHistory = ServerSiteCommandHistory::factory()->create([
            'duration_ms' => $durationMs,
        ]);

        // Act
        $storedDuration = $commandHistory->duration_ms;

        // Assert
        $this->assertEquals($durationMs, $storedDuration);
        $this->assertIsInt($storedDuration);
    }

    /**
     * Test command history stores different command types.
     */
    public function test_stores_different_command_types(): void
    {
        // Arrange
        $commands = [
            'php artisan migrate',
            'composer install',
            'npm install',
            'git pull origin main',
        ];

        // Act & Assert
        foreach ($commands as $command) {
            $commandHistory = ServerSiteCommandHistory::factory()->create([
                'command' => $command,
            ]);

            $this->assertEquals($command, $commandHistory->command);
        }
    }

    /**
     * Test command history can be created with all fillable attributes.
     */
    public function test_can_create_with_all_fillable_attributes(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);
        $attributes = [
            'server_id' => $server->id,
            'server_site_id' => $site->id,
            'command' => 'php artisan test',
            'output' => 'Tests passed',
            'error_output' => null,
            'exit_code' => 0,
            'duration_ms' => 2500,
            'success' => true,
        ];

        // Act
        $commandHistory = ServerSiteCommandHistory::create($attributes);

        // Assert
        $this->assertDatabaseHas('server_site_command_history', $attributes);
        $this->assertEquals($attributes['command'], $commandHistory->command);
        $this->assertEquals($attributes['output'], $commandHistory->output);
        $this->assertEquals($attributes['duration_ms'], $commandHistory->duration_ms);
    }

    /**
     * Test event is dispatched with correct server site id.
     */
    public function test_event_dispatched_with_correct_server_site_id(): void
    {
        // Arrange
        Event::fake([ServerSiteUpdated::class]);
        $site = ServerSite::factory()->create();
        $otherSite = ServerSite::factory()->create();

        // Act
        ServerSiteCommandHistory::factory()->create([
            'server_site_id' => $site->id,
        ]);

        // Assert
        Event::assertDispatched(ServerSiteUpdated::class, function ($event) use ($site) {
            return $event->siteId === $site->id;
        });

        // Assert event not dispatched for other site
        Event::assertNotDispatched(ServerSiteUpdated::class, function ($event) use ($otherSite) {
            return $event->siteId === $otherSite->id;
        });
    }
}
