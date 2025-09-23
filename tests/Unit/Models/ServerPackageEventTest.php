<?php

namespace Tests\Unit\Models;

use App\Models\Server;
use App\Models\ServerPackageEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerPackageEventTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that ServerPackageEvent can be created with valid attributes.
     */
    public function test_can_create_server_package_event(): void
    {
        $event = ServerPackageEvent::factory()->create();

        $this->assertDatabaseHas('server_package_events', [
            'id' => $event->id,
            'server_id' => $event->server_id,
            'service_type' => $event->service_type,
            'provision_type' => $event->provision_type,
        ]);
    }

    /**
     * Test fillable attributes are mass assignable.
     */
    public function test_fillable_attributes_are_mass_assignable(): void
    {
        $server = Server::factory()->create();
        $data = [
            'server_id' => $server->id,
            'service_type' => 'mysql',
            'provision_type' => 'install',
            'milestone' => 'Installing MySQL',
            'current_step' => 2,
            'total_steps' => 5,
            'details' => ['message' => 'Installing packages'],
            'status' => 'pending',
            'error_log' => null,
        ];

        $event = ServerPackageEvent::create($data);

        $this->assertEquals($server->id, $event->server_id);
        $this->assertEquals('mysql', $event->service_type);
        $this->assertEquals('install', $event->provision_type);
        $this->assertEquals('Installing MySQL', $event->milestone);
        $this->assertEquals(2, $event->current_step);
        $this->assertEquals(5, $event->total_steps);
        $this->assertEquals(['message' => 'Installing packages'], $event->details);
        $this->assertEquals('pending', $event->status);
        $this->assertNull($event->error_log);
    }

    /**
     * Test that details are cast to array.
     */
    public function test_details_are_cast_to_array(): void
    {
        $event = ServerPackageEvent::factory()->create([
            'details' => ['key' => 'value'],
        ]);

        $this->assertIsArray($event->details);
        $this->assertEquals(['key' => 'value'], $event->details);
    }

    /**
     * Test that current_step and total_steps are cast to integers.
     */
    public function test_steps_are_cast_to_integers(): void
    {
        $event = ServerPackageEvent::factory()->create([
            'current_step' => '3',
            'total_steps' => '10',
        ]);

        $this->assertIsInt($event->current_step);
        $this->assertIsInt($event->total_steps);
        $this->assertEquals(3, $event->current_step);
        $this->assertEquals(10, $event->total_steps);
    }

    /**
     * Test server relationship.
     */
    public function test_belongs_to_server(): void
    {
        $server = Server::factory()->create();
        $event = ServerPackageEvent::factory()->create([
            'server_id' => $server->id,
        ]);

        $this->assertInstanceOf(Server::class, $event->server);
        $this->assertEquals($server->id, $event->server->id);
    }

    /**
     * Test progress percentage calculation.
     */
    public function test_progress_percentage_calculation(): void
    {
        $event = ServerPackageEvent::factory()->create([
            'current_step' => 3,
            'total_steps' => 10,
        ]);

        $this->assertEquals('30', $event->progress_percentage);
    }

    /**
     * Test progress percentage returns 0 when total_steps is 0.
     */
    public function test_progress_percentage_returns_zero_when_total_steps_is_zero(): void
    {
        $event = ServerPackageEvent::factory()->create([
            'current_step' => 5,
            'total_steps' => 0,
        ]);

        $this->assertEquals('0', $event->progress_percentage);
    }

    /**
     * Test progress percentage returns 100 when completed.
     */
    public function test_progress_percentage_returns_100_when_completed(): void
    {
        $event = ServerPackageEvent::factory()->create([
            'current_step' => 10,
            'total_steps' => 10,
        ]);

        $this->assertEquals('100', $event->progress_percentage);
    }

    /**
     * Test progress percentage rounds to 2 decimal places.
     */
    public function test_progress_percentage_rounds_to_two_decimal_places(): void
    {
        $event = ServerPackageEvent::factory()->create([
            'current_step' => 1,
            'total_steps' => 3,
        ]);

        $this->assertEquals('33.333333333333', $event->progress_percentage);
    }

    /**
     * Test isInstall method.
     */
    public function test_is_install_method(): void
    {
        $installEvent = ServerPackageEvent::factory()->install()->create();
        $uninstallEvent = ServerPackageEvent::factory()->uninstall()->create();

        $this->assertTrue($installEvent->isInstall());
        $this->assertFalse($uninstallEvent->isInstall());
    }

    /**
     * Test isUninstall method.
     */
    public function test_is_uninstall_method(): void
    {
        $installEvent = ServerPackageEvent::factory()->install()->create();
        $uninstallEvent = ServerPackageEvent::factory()->uninstall()->create();

        $this->assertFalse($installEvent->isUninstall());
        $this->assertTrue($uninstallEvent->isUninstall());
    }

    /**
     * Test factory states.
     */
    public function test_factory_install_state(): void
    {
        $event = ServerPackageEvent::factory()->install()->create();

        $this->assertEquals('install', $event->provision_type);
    }

    /**
     * Test factory uninstall state.
     */
    public function test_factory_uninstall_state(): void
    {
        $event = ServerPackageEvent::factory()->uninstall()->create();

        $this->assertEquals('uninstall', $event->provision_type);
    }

    /**
     * Test factory completed state.
     */
    public function test_factory_completed_state(): void
    {
        $event = ServerPackageEvent::factory()
            ->completed()
            ->create();

        $this->assertEquals($event->total_steps, $event->current_step);
        $this->assertEquals('100', $event->progress_percentage);

        // Test with specific total_steps
        $event2 = ServerPackageEvent::factory()
            ->state(['total_steps' => 8])
            ->completed()
            ->create();

        $this->assertEquals(8, $event2->current_step);
        $this->assertEquals(8, $event2->total_steps);
        $this->assertEquals('100', $event2->progress_percentage);
    }

    /**
     * Test factory started state.
     */
    public function test_factory_started_state(): void
    {
        $event = ServerPackageEvent::factory()
            ->started()
            ->create(['total_steps' => 5]);

        $this->assertEquals(1, $event->current_step);
        $this->assertEquals(5, $event->total_steps);
        $this->assertEquals('20', $event->progress_percentage);
    }

    /**
     * Test factory withProgress state.
     */
    public function test_factory_with_progress_state(): void
    {
        $event = ServerPackageEvent::factory()
            ->withProgress(7, 10)
            ->create();

        $this->assertEquals(7, $event->current_step);
        $this->assertEquals(10, $event->total_steps);
        $this->assertEquals('70', $event->progress_percentage);
    }

    /**
     * Test multiple events can be created for the same server.
     */
    public function test_multiple_events_for_same_server(): void
    {
        $server = Server::factory()->create();

        $event1 = ServerPackageEvent::factory()->create([
            'server_id' => $server->id,
            'service_type' => 'mysql',
        ]);

        $event2 = ServerPackageEvent::factory()->create([
            'server_id' => $server->id,
            'service_type' => 'nginx',
        ]);

        $this->assertEquals($server->id, $event1->server_id);
        $this->assertEquals($server->id, $event2->server_id);
        $this->assertNotEquals($event1->id, $event2->id);
    }

    /**
     * Test that details can be null.
     */
    public function test_details_can_be_null(): void
    {
        $event = ServerPackageEvent::factory()->create([
            'details' => null,
        ]);

        $this->assertNull($event->details);
    }

    /**
     * Test that milestone can contain special characters.
     */
    public function test_milestone_can_contain_special_characters(): void
    {
        $milestone = 'Installing MySQL 8.0 [Step 1/5] - Configuring repositories...';
        $event = ServerPackageEvent::factory()->create([
            'milestone' => $milestone,
        ]);

        $this->assertEquals($milestone, $event->milestone);
    }

    /**
     * Test status helper methods.
     */
    public function test_status_helper_methods(): void
    {
        $pendingEvent = ServerPackageEvent::factory()->pending()->create();
        $successEvent = ServerPackageEvent::factory()->success()->create();
        $failedEvent = ServerPackageEvent::factory()->failed()->create();

        // Test isPending()
        $this->assertTrue($pendingEvent->isPending());
        $this->assertFalse($successEvent->isPending());
        $this->assertFalse($failedEvent->isPending());

        // Test isSuccess()
        $this->assertFalse($pendingEvent->isSuccess());
        $this->assertTrue($successEvent->isSuccess());
        $this->assertFalse($failedEvent->isSuccess());

        // Test isFailed()
        $this->assertFalse($pendingEvent->isFailed());
        $this->assertFalse($successEvent->isFailed());
        $this->assertTrue($failedEvent->isFailed());
    }

    /**
     * Test status defaults to pending.
     */
    public function test_status_defaults_to_pending(): void
    {
        $server = Server::factory()->create();
        $event = ServerPackageEvent::create([
            'server_id' => $server->id,
            'service_type' => 'mysql',
            'provision_type' => 'install',
            'milestone' => 'Starting installation',
            'current_step' => 1,
            'total_steps' => 5,
        ]);

        // Refresh to get database default value
        $event->refresh();

        $this->assertEquals('pending', $event->status);
        $this->assertTrue($event->isPending());
    }

    /**
     * Test failed status with error log.
     */
    public function test_failed_status_with_error_log(): void
    {
        $errorMessage = 'Maximum execution time of 30 seconds exceeded';
        $event = ServerPackageEvent::factory()->failed($errorMessage)->create();

        $this->assertEquals('failed', $event->status);
        $this->assertEquals($errorMessage, $event->error_log);
        $this->assertTrue($event->isFailed());
    }

    /**
     * Test success status has null error log.
     */
    public function test_success_status_has_null_error_log(): void
    {
        $event = ServerPackageEvent::factory()->success()->create();

        $this->assertEquals('success', $event->status);
        $this->assertNull($event->error_log);
        $this->assertTrue($event->isSuccess());
    }

    /**
     * Test factory states for status.
     */
    public function test_factory_status_states(): void
    {
        $pendingEvent = ServerPackageEvent::factory()->pending()->create();
        $this->assertEquals('pending', $pendingEvent->status);
        $this->assertNull($pendingEvent->error_log);

        $successEvent = ServerPackageEvent::factory()->success()->create();
        $this->assertEquals('success', $successEvent->status);
        $this->assertNull($successEvent->error_log);

        $failedEvent = ServerPackageEvent::factory()->failed('Custom error message')->create();
        $this->assertEquals('failed', $failedEvent->status);
        $this->assertEquals('Custom error message', $failedEvent->error_log);
    }
}
