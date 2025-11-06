<?php

namespace Tests\Unit\Models;

use App\Events\ServerSiteUpdated;
use App\Models\Server;
use App\Models\ServerDeployment;
use App\Models\ServerSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ServerDeploymentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that isPending returns true when status is pending.
     */
    public function test_is_pending_returns_true_when_status_is_pending(): void
    {
        // Arrange - inline setup
        Event::fake();
        $deployment = ServerDeployment::factory()->pending()->create();

        // Act & Assert
        $this->assertTrue($deployment->isPending());
    }

    /**
     * Test that isPending returns false when status is not pending.
     */
    public function test_is_pending_returns_false_when_status_is_not_pending(): void
    {
        // Arrange
        Event::fake();
        $deployment = ServerDeployment::factory()->create(['status' => 'success']);

        // Act & Assert
        $this->assertFalse($deployment->isPending());
    }

    /**
     * Test that isRunning returns true when status is running.
     */
    public function test_is_running_returns_true_when_status_is_running(): void
    {
        // Arrange
        Event::fake();
        $deployment = ServerDeployment::factory()->running()->create();

        // Act & Assert
        $this->assertTrue($deployment->isRunning());
    }

    /**
     * Test that isRunning returns false when status is not running.
     */
    public function test_is_running_returns_false_when_status_is_not_running(): void
    {
        // Arrange
        Event::fake();
        $deployment = ServerDeployment::factory()->create(['status' => 'success']);

        // Act & Assert
        $this->assertFalse($deployment->isRunning());
    }

    /**
     * Test that isSuccess returns true when status is success.
     */
    public function test_is_success_returns_true_when_status_is_success(): void
    {
        // Arrange - default factory state is success
        Event::fake();
        $deployment = ServerDeployment::factory()->create();

        // Act & Assert
        $this->assertTrue($deployment->isSuccess());
    }

    /**
     * Test that isSuccess returns false when status is not success.
     */
    public function test_is_success_returns_false_when_status_is_not_success(): void
    {
        // Arrange
        Event::fake();
        $deployment = ServerDeployment::factory()->failed()->create();

        // Act & Assert
        $this->assertFalse($deployment->isSuccess());
    }

    /**
     * Test that isFailed returns true when status is failed.
     */
    public function test_is_failed_returns_true_when_status_is_failed(): void
    {
        // Arrange
        Event::fake();
        $deployment = ServerDeployment::factory()->failed()->create();

        // Act & Assert
        $this->assertTrue($deployment->isFailed());
    }

    /**
     * Test that isFailed returns false when status is not failed.
     */
    public function test_is_failed_returns_false_when_status_is_not_failed(): void
    {
        // Arrange
        Event::fake();
        $deployment = ServerDeployment::factory()->create(['status' => 'success']);

        // Act & Assert
        $this->assertFalse($deployment->isFailed());
    }

    /**
     * Test that getDurationSeconds converts milliseconds to seconds correctly.
     */
    public function test_get_duration_seconds_converts_milliseconds_to_seconds(): void
    {
        // Arrange - 5000ms should be 5.0 seconds
        Event::fake();
        $deployment = ServerDeployment::factory()->create(['duration_ms' => 5000]);

        // Act
        $seconds = $deployment->getDurationSeconds();

        // Assert
        $this->assertEquals(5.0, $seconds);
    }

    /**
     * Test that getDurationSeconds rounds to 2 decimal places.
     */
    public function test_get_duration_seconds_rounds_to_two_decimal_places(): void
    {
        // Arrange - 1234ms should be 1.23 seconds (rounded)
        Event::fake();
        $deployment = ServerDeployment::factory()->create(['duration_ms' => 1234]);

        // Act
        $seconds = $deployment->getDurationSeconds();

        // Assert
        $this->assertEquals(1.23, $seconds);
    }

    /**
     * Test that getDurationSeconds returns null when duration_ms is null.
     */
    public function test_get_duration_seconds_returns_null_when_duration_is_null(): void
    {
        // Arrange - pending deployment has null duration
        Event::fake();
        $deployment = ServerDeployment::factory()->pending()->create();

        // Act
        $seconds = $deployment->getDurationSeconds();

        // Assert
        $this->assertNull($seconds);
    }

    /**
     * Test that deployment belongs to a server.
     */
    public function test_deployment_belongs_to_server(): void
    {
        // Arrange
        Event::fake();
        $server = Server::factory()->create(['vanity_name' => 'test-server']);
        $deployment = ServerDeployment::factory()->create(['server_id' => $server->id]);

        // Act
        $relatedServer = $deployment->server;

        // Assert
        $this->assertInstanceOf(Server::class, $relatedServer);
        $this->assertEquals($server->id, $relatedServer->id);
        $this->assertEquals('test-server', $relatedServer->vanity_name);
    }

    /**
     * Test that deployment belongs to a site.
     */
    public function test_deployment_belongs_to_site(): void
    {
        // Arrange
        Event::fake();
        $site = ServerSite::factory()->create(['domain' => 'example.com']);
        $deployment = ServerDeployment::factory()->create(['server_site_id' => $site->id]);

        // Act
        $relatedSite = $deployment->site;

        // Assert
        $this->assertInstanceOf(ServerSite::class, $relatedSite);
        $this->assertEquals($site->id, $relatedSite->id);
        $this->assertEquals('example.com', $relatedSite->domain);
    }

    /**
     * Test that ServerSiteUpdated event is dispatched when deployment is created.
     */
    public function test_server_site_updated_event_dispatched_on_deployment_created(): void
    {
        // Arrange - only fake the specific event, allow model events to fire
        Event::fake([ServerSiteUpdated::class]);
        $site = ServerSite::factory()->create();

        // Act
        ServerDeployment::factory()->create(['server_site_id' => $site->id]);

        // Assert
        Event::assertDispatched(ServerSiteUpdated::class, function ($event) use ($site) {
            return $event->siteId === $site->id;
        });
    }

    /**
     * Test that ServerSiteUpdated event is dispatched when deployment is updated.
     */
    public function test_server_site_updated_event_dispatched_on_deployment_updated(): void
    {
        // Arrange - create deployment first with events faked, then fake specific event to test update
        Event::fake([ServerSiteUpdated::class]);
        $deployment = ServerDeployment::factory()->running()->create();

        // Act - update deployment status
        $deployment->update(['status' => 'success']);

        // Assert - should have dispatched twice (once for create, once for update)
        Event::assertDispatched(ServerSiteUpdated::class, 2);
    }

    /**
     * Test that started_at is cast to datetime.
     */
    public function test_started_at_is_cast_to_datetime(): void
    {
        // Arrange
        Event::fake();
        $deployment = ServerDeployment::factory()->running()->create();

        // Act & Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $deployment->started_at);
    }

    /**
     * Test that completed_at is cast to datetime.
     */
    public function test_completed_at_is_cast_to_datetime(): void
    {
        // Arrange
        Event::fake();
        $deployment = ServerDeployment::factory()->create();

        // Act & Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $deployment->completed_at);
    }

    /**
     * Test that exit_code is cast to integer.
     */
    public function test_exit_code_is_cast_to_integer(): void
    {
        // Arrange
        Event::fake();
        $deployment = ServerDeployment::factory()->create(['exit_code' => '0']);

        // Act
        $exitCode = $deployment->exit_code;

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
        Event::fake();
        $deployment = ServerDeployment::factory()->create(['duration_ms' => '5000']);

        // Act
        $duration = $deployment->duration_ms;

        // Assert
        $this->assertIsInt($duration);
        $this->assertEquals(5000, $duration);
    }

    /**
     * Test that all status check methods work correctly for each state.
     */
    public function test_all_status_checks_are_mutually_exclusive(): void
    {
        // Arrange & Act - pending deployment
        Event::fake();
        $pending = ServerDeployment::factory()->pending()->create();

        // Assert
        $this->assertTrue($pending->isPending());
        $this->assertFalse($pending->isRunning());
        $this->assertFalse($pending->isSuccess());
        $this->assertFalse($pending->isFailed());

        // Arrange & Act - running deployment
        $running = ServerDeployment::factory()->running()->create();

        // Assert
        $this->assertFalse($running->isPending());
        $this->assertTrue($running->isRunning());
        $this->assertFalse($running->isSuccess());
        $this->assertFalse($running->isFailed());

        // Arrange & Act - success deployment
        $success = ServerDeployment::factory()->create(['status' => 'success']);

        // Assert
        $this->assertFalse($success->isPending());
        $this->assertFalse($success->isRunning());
        $this->assertTrue($success->isSuccess());
        $this->assertFalse($success->isFailed());

        // Arrange & Act - failed deployment
        $failed = ServerDeployment::factory()->failed()->create();

        // Assert
        $this->assertFalse($failed->isPending());
        $this->assertFalse($failed->isRunning());
        $this->assertFalse($failed->isSuccess());
        $this->assertTrue($failed->isFailed());
    }

    /**
     * Test edge case where duration is zero.
     */
    public function test_get_duration_seconds_handles_zero_milliseconds(): void
    {
        // Arrange
        Event::fake();
        $deployment = ServerDeployment::factory()->create(['duration_ms' => 0]);

        // Act
        $seconds = $deployment->getDurationSeconds();

        // Assert
        $this->assertEquals(0.0, $seconds);
    }

    /**
     * Test large duration values are converted correctly.
     */
    public function test_get_duration_seconds_handles_large_values(): void
    {
        // Arrange - 3600000ms = 1 hour = 3600 seconds
        Event::fake();
        $deployment = ServerDeployment::factory()->create(['duration_ms' => 3600000]);

        // Act
        $seconds = $deployment->getDurationSeconds();

        // Assert
        $this->assertEquals(3600.0, $seconds);
    }

    /**
     * Test that status is cast to TaskStatus enum.
     */
    public function test_status_is_cast_to_task_status_enum(): void
    {
        // Arrange
        Event::fake();
        $deployment = ServerDeployment::factory()->create(['status' => 'success']);

        // Act
        $status = $deployment->status;

        // Assert
        $this->assertInstanceOf(\App\Enums\TaskStatus::class, $status);
        $this->assertEquals(\App\Enums\TaskStatus::Success, $status);
    }

    /**
     * Test that deployment can be created with TaskStatus enum.
     */
    public function test_deployment_can_be_created_with_task_status_enum(): void
    {
        // Arrange
        Event::fake();

        // Act - create deployment using TaskStatus enum directly
        $deployment = ServerDeployment::factory()->create([
            'status' => \App\Enums\TaskStatus::Updating,
        ]);

        // Assert - should save correctly and read back as enum
        $this->assertInstanceOf(\App\Enums\TaskStatus::class, $deployment->status);
        $this->assertEquals(\App\Enums\TaskStatus::Updating, $deployment->status);
        $this->assertTrue($deployment->isRunning());

        // Verify it's stored correctly in database
        $this->assertDatabaseHas('server_deployments', [
            'id' => $deployment->id,
            'status' => 'updating',
        ]);
    }

    /**
     * Test that deployment status can be updated using TaskStatus enum.
     */
    public function test_deployment_status_can_be_updated_with_task_status_enum(): void
    {
        // Arrange
        Event::fake([ServerSiteUpdated::class]);
        $deployment = ServerDeployment::factory()->pending()->create();

        // Act - update status using TaskStatus enum
        $deployment->update(['status' => \App\Enums\TaskStatus::Updating]);

        // Assert - should update correctly
        $deployment->refresh();
        $this->assertEquals(\App\Enums\TaskStatus::Updating, $deployment->status);
        $this->assertTrue($deployment->isRunning());

        // Verify it's stored correctly in database
        $this->assertDatabaseHas('server_deployments', [
            'id' => $deployment->id,
            'status' => 'updating',
        ]);
    }

    /**
     * Test deployment status lifecycle using TaskStatus enum.
     */
    public function test_deployment_status_lifecycle_with_task_status_enum(): void
    {
        // Arrange
        Event::fake([ServerSiteUpdated::class]);

        // Act & Assert - pending
        $deployment = ServerDeployment::factory()->create([
            'status' => \App\Enums\TaskStatus::Pending,
        ]);
        $this->assertEquals(\App\Enums\TaskStatus::Pending, $deployment->status);
        $this->assertTrue($deployment->isPending());

        // Act & Assert - updating
        $deployment->update(['status' => \App\Enums\TaskStatus::Updating]);
        $deployment->refresh();
        $this->assertEquals(\App\Enums\TaskStatus::Updating, $deployment->status);
        $this->assertTrue($deployment->isRunning());

        // Act & Assert - success
        $deployment->update(['status' => \App\Enums\TaskStatus::Success]);
        $deployment->refresh();
        $this->assertEquals(\App\Enums\TaskStatus::Success, $deployment->status);
        $this->assertTrue($deployment->isSuccess());
    }
}
