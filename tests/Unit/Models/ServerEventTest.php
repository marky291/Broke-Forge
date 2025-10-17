<?php

namespace Tests\Unit\Models;

use App\Models\Server;
use App\Models\ServerEvent;
use App\Models\ServerSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerEventTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test server event belongs to a server.
     */
    public function test_belongs_to_server(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $event = ServerEvent::factory()->create([
            'server_id' => $server->id,
        ]);

        // Act
        $result = $event->server;

        // Assert
        $this->assertInstanceOf(Server::class, $result);
        $this->assertEquals($server->id, $result->id);
    }

    /**
     * Test server event belongs to a site.
     */
    public function test_belongs_to_site(): void
    {
        // Arrange
        $site = ServerSite::factory()->create();
        $event = ServerEvent::factory()->create([
            'server_site_id' => $site->id,
        ]);

        // Act
        $result = $event->site;

        // Assert
        $this->assertInstanceOf(ServerSite::class, $result);
        $this->assertEquals($site->id, $result->id);
    }

    /**
     * Test details is cast to array.
     */
    public function test_details_is_cast_to_array(): void
    {
        // Arrange
        $event = ServerEvent::factory()->create([
            'details' => ['message' => 'Installing', 'step' => 1],
        ]);

        // Act
        $details = $event->details;

        // Assert
        $this->assertIsArray($details);
        $this->assertEquals('Installing', $details['message']);
        $this->assertEquals(1, $details['step']);
    }

    /**
     * Test current step is cast to integer.
     */
    public function test_current_step_is_cast_to_integer(): void
    {
        // Arrange
        $event = ServerEvent::factory()->create([
            'current_step' => 5,
        ]);

        // Act
        $currentStep = $event->current_step;

        // Assert
        $this->assertIsInt($currentStep);
        $this->assertEquals(5, $currentStep);
    }

    /**
     * Test total steps is cast to integer.
     */
    public function test_total_steps_is_cast_to_integer(): void
    {
        // Arrange
        $event = ServerEvent::factory()->create([
            'total_steps' => 10,
        ]);

        // Act
        $totalSteps = $event->total_steps;

        // Assert
        $this->assertIsInt($totalSteps);
        $this->assertEquals(10, $totalSteps);
    }

    /**
     * Test progress percentage is calculated correctly.
     */
    public function test_progress_percentage_is_calculated_correctly(): void
    {
        // Arrange
        $event = ServerEvent::factory()->create([
            'current_step' => 5,
            'total_steps' => 10,
        ]);

        // Act
        $progress = $event->progressPercentage;

        // Assert
        $this->assertEquals('50', $progress);
    }

    /**
     * Test progress percentage returns zero when total steps is zero.
     */
    public function test_progress_percentage_returns_zero_when_total_steps_is_zero(): void
    {
        // Arrange
        $event = ServerEvent::factory()->create([
            'current_step' => 0,
            'total_steps' => 0,
        ]);

        // Act
        $progress = $event->progressPercentage;

        // Assert
        $this->assertEquals('0', $progress);
    }

    /**
     * Test progress percentage at 100 percent.
     */
    public function test_progress_percentage_at_100_percent(): void
    {
        // Arrange
        $event = ServerEvent::factory()->create([
            'current_step' => 10,
            'total_steps' => 10,
        ]);

        // Act
        $progress = $event->progressPercentage;

        // Assert
        $this->assertEquals('100', $progress);
    }

    /**
     * Test is install returns true for install provision type.
     */
    public function test_is_install_returns_true_for_install_provision_type(): void
    {
        // Arrange
        $event = ServerEvent::factory()->install()->create();

        // Act & Assert
        $this->assertTrue($event->isInstall());
        $this->assertFalse($event->isUninstall());
    }

    /**
     * Test is uninstall returns true for uninstall provision type.
     */
    public function test_is_uninstall_returns_true_for_uninstall_provision_type(): void
    {
        // Arrange
        $event = ServerEvent::factory()->uninstall()->create();

        // Act & Assert
        $this->assertTrue($event->isUninstall());
        $this->assertFalse($event->isInstall());
    }

    /**
     * Test is pending returns true for pending status.
     */
    public function test_is_pending_returns_true_for_pending_status(): void
    {
        // Arrange
        $event = ServerEvent::factory()->pending()->create();

        // Act & Assert
        $this->assertTrue($event->isPending());
        $this->assertFalse($event->isSuccess());
        $this->assertFalse($event->isFailed());
    }

    /**
     * Test is success returns true for success status.
     */
    public function test_is_success_returns_true_for_success_status(): void
    {
        // Arrange
        $event = ServerEvent::factory()->success()->create();

        // Act & Assert
        $this->assertTrue($event->isSuccess());
        $this->assertFalse($event->isPending());
        $this->assertFalse($event->isFailed());
    }

    /**
     * Test is failed returns true for failed status.
     */
    public function test_is_failed_returns_true_for_failed_status(): void
    {
        // Arrange
        $event = ServerEvent::factory()->failed()->create();

        // Act & Assert
        $this->assertTrue($event->isFailed());
        $this->assertFalse($event->isPending());
        $this->assertFalse($event->isSuccess());
    }

    /**
     * Test factory creates event with correct attributes.
     */
    public function test_factory_creates_event_with_correct_attributes(): void
    {
        // Act
        $event = ServerEvent::factory()->create();

        // Assert
        $this->assertNotNull($event->server_id);
        $this->assertNotNull($event->service_type);
        $this->assertNotNull($event->provision_type);
        $this->assertNotNull($event->milestone);
        $this->assertNotNull($event->current_step);
        $this->assertNotNull($event->total_steps);
        $this->assertNotNull($event->details);
        $this->assertNotNull($event->status);
        $this->assertIsArray($event->details);
    }

    /**
     * Test factory install state sets install provision type.
     */
    public function test_factory_install_state_sets_install_provision_type(): void
    {
        // Act
        $event = ServerEvent::factory()->install()->create();

        // Assert
        $this->assertEquals('install', $event->provision_type);
        $this->assertTrue($event->isInstall());
    }

    /**
     * Test factory uninstall state sets uninstall provision type.
     */
    public function test_factory_uninstall_state_sets_uninstall_provision_type(): void
    {
        // Act
        $event = ServerEvent::factory()->uninstall()->create();

        // Assert
        $this->assertEquals('uninstall', $event->provision_type);
        $this->assertTrue($event->isUninstall());
    }

    /**
     * Test factory completed state sets current step equal to total steps.
     */
    public function test_factory_completed_state_sets_current_step_equal_to_total_steps(): void
    {
        // Act
        $event = ServerEvent::factory()->completed()->create();

        // Assert
        $this->assertEquals($event->total_steps, $event->current_step);
        $this->assertEquals('100', $event->progressPercentage);
    }

    /**
     * Test factory started state sets current step to 1.
     */
    public function test_factory_started_state_sets_current_step_to_1(): void
    {
        // Act
        $event = ServerEvent::factory()->started()->create();

        // Assert
        $this->assertEquals(1, $event->current_step);
    }

    /**
     * Test factory with progress sets custom progress.
     */
    public function test_factory_with_progress_sets_custom_progress(): void
    {
        // Act
        $event = ServerEvent::factory()->withProgress(3, 5)->create();

        // Assert
        $this->assertEquals(3, $event->current_step);
        $this->assertEquals(5, $event->total_steps);
        $this->assertEquals('60', $event->progressPercentage);
    }

    /**
     * Test factory pending state sets pending status.
     */
    public function test_factory_pending_state_sets_pending_status(): void
    {
        // Act
        $event = ServerEvent::factory()->pending()->create();

        // Assert
        $this->assertEquals('pending', $event->status);
        $this->assertNull($event->error_log);
        $this->assertTrue($event->isPending());
    }

    /**
     * Test factory success state sets success status.
     */
    public function test_factory_success_state_sets_success_status(): void
    {
        // Act
        $event = ServerEvent::factory()->success()->create();

        // Assert
        $this->assertEquals('success', $event->status);
        $this->assertNull($event->error_log);
        $this->assertTrue($event->isSuccess());
    }

    /**
     * Test factory failed state sets failed status.
     */
    public function test_factory_failed_state_sets_failed_status(): void
    {
        // Act
        $event = ServerEvent::factory()->failed()->create();

        // Assert
        $this->assertEquals('failed', $event->status);
        $this->assertNotNull($event->error_log);
        $this->assertTrue($event->isFailed());
    }

    /**
     * Test factory failed state can set custom error log.
     */
    public function test_factory_failed_state_can_set_custom_error_log(): void
    {
        // Arrange
        $customError = 'Custom error message';

        // Act
        $event = ServerEvent::factory()->failed($customError)->create();

        // Assert
        $this->assertEquals('failed', $event->status);
        $this->assertEquals($customError, $event->error_log);
    }

    /**
     * Test fillable attributes are mass assignable.
     */
    public function test_fillable_attributes_are_mass_assignable(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act
        $event = ServerEvent::create([
            'server_id' => $server->id,
            'service_type' => 'mysql',
            'provision_type' => 'install',
            'milestone' => 'Installing MySQL',
            'current_step' => 3,
            'total_steps' => 5,
            'details' => ['message' => 'Installing packages'],
            'status' => 'pending',
            'error_log' => null,
        ]);

        // Assert
        $this->assertDatabaseHas('server_events', [
            'server_id' => $server->id,
            'service_type' => 'mysql',
            'provision_type' => 'install',
        ]);
    }

    /**
     * Test event can be created with server site id.
     */
    public function test_event_can_be_created_with_server_site_id(): void
    {
        // Arrange
        $site = ServerSite::factory()->create();

        // Act
        $event = ServerEvent::factory()->create([
            'server_site_id' => $site->id,
        ]);

        // Assert
        $this->assertEquals($site->id, $event->server_site_id);
        $this->assertInstanceOf(ServerSite::class, $event->site);
    }

    /**
     * Test multiple events can belong to same server.
     */
    public function test_multiple_events_can_belong_to_same_server(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act
        $event1 = ServerEvent::factory()->create(['server_id' => $server->id]);
        $event2 = ServerEvent::factory()->create(['server_id' => $server->id]);
        $event3 = ServerEvent::factory()->create(['server_id' => $server->id]);

        // Assert
        $this->assertEquals($server->id, $event1->server_id);
        $this->assertEquals($server->id, $event2->server_id);
        $this->assertEquals($server->id, $event3->server_id);
        $this->assertCount(3, ServerEvent::where('server_id', $server->id)->get());
    }

    /**
     * Test event can be deleted.
     */
    public function test_event_can_be_deleted(): void
    {
        // Arrange
        $event = ServerEvent::factory()->create();
        $eventId = $event->id;

        // Act
        $event->delete();

        // Assert
        $this->assertDatabaseMissing('server_events', [
            'id' => $eventId,
        ]);
    }

    /**
     * Test event relationships can be eagerly loaded.
     */
    public function test_event_relationships_can_be_eagerly_loaded(): void
    {
        // Arrange
        $server = Server::factory()->create();
        ServerEvent::factory()->create(['server_id' => $server->id]);

        // Act
        $event = ServerEvent::with('server')->first();

        // Assert
        $this->assertTrue($event->relationLoaded('server'));
        $this->assertInstanceOf(Server::class, $event->server);
    }

    /**
     * Test event stores details as json and retrieves as array.
     */
    public function test_event_stores_details_as_json_and_retrieves_as_array(): void
    {
        // Arrange
        $details = [
            'message' => 'Installing MySQL',
            'command' => 'apt-get install mysql-server',
            'output' => 'Package installed successfully',
        ];

        // Act
        $event = ServerEvent::factory()->create(['details' => $details]);

        // Assert
        $this->assertIsArray($event->details);
        $this->assertEquals($details['message'], $event->details['message']);
        $this->assertEquals($details['command'], $event->details['command']);
        $this->assertEquals($details['output'], $event->details['output']);
    }

    /**
     * Test event can store empty details array.
     */
    public function test_event_can_store_empty_details_array(): void
    {
        // Arrange & Act
        $event = ServerEvent::factory()->create(['details' => []]);

        // Assert
        $this->assertIsArray($event->details);
        $this->assertEmpty($event->details);
    }

    /**
     * Test progress percentage handles partial progress correctly.
     */
    public function test_progress_percentage_handles_partial_progress_correctly(): void
    {
        // Arrange
        $event = ServerEvent::factory()->create([
            'current_step' => 2,
            'total_steps' => 3,
        ]);

        // Act
        $progress = $event->progressPercentage;

        // Assert
        $this->assertStringContainsString('66', $progress);
    }
}
