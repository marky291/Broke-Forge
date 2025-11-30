<?php

namespace Tests\Unit\Models;

use App\Enums\TaskStatus;
use App\Events\ServerUpdated;
use App\Models\Server;
use App\Models\ServerPhp;
use App\Models\ServerPhpModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ServerPhpTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test server php belongs to a server.
     */
    public function test_belongs_to_server(): void
    {
        // Arrange
        Event::fake();
        $server = Server::factory()->create();
        $serverPhp = ServerPhp::factory()->create([
            'server_id' => $server->id,
        ]);

        // Act
        $result = $serverPhp->server;

        // Assert
        $this->assertInstanceOf(Server::class, $result);
        $this->assertEquals($server->id, $result->id);
    }

    /**
     * Test server php has many modules.
     */
    public function test_has_many_modules(): void
    {
        // Arrange
        Event::fake();
        $serverPhp = ServerPhp::factory()->create();
        $module1 = ServerPhpModule::factory()->create([
            'server_php_id' => $serverPhp->id,
            'name' => 'gd',
        ]);
        $module2 = ServerPhpModule::factory()->create([
            'server_php_id' => $serverPhp->id,
            'name' => 'curl',
        ]);

        // Act
        $modules = $serverPhp->modules;

        // Assert
        $this->assertCount(2, $modules);
        $this->assertTrue($modules->contains($module1));
        $this->assertTrue($modules->contains($module2));
    }

    /**
     * Test is cli default is cast to boolean.
     */
    public function test_is_cli_default_is_cast_to_boolean(): void
    {
        // Arrange
        Event::fake();
        $serverPhp = ServerPhp::factory()->create([
            'is_cli_default' => 1,
        ]);

        // Act
        $isCliDefault = $serverPhp->is_cli_default;

        // Assert
        $this->assertIsBool($isCliDefault);
        $this->assertTrue($isCliDefault);
    }

    /**
     * Test is site default is cast to boolean.
     */
    public function test_is_site_default_is_cast_to_boolean(): void
    {
        // Arrange
        Event::fake();
        $serverPhp = ServerPhp::factory()->create([
            'is_site_default' => 1,
        ]);

        // Act
        $isSiteDefault = $serverPhp->is_site_default;

        // Assert
        $this->assertIsBool($isSiteDefault);
        $this->assertTrue($isSiteDefault);
    }

    /**
     * Test status is cast to PhpStatus enum.
     */
    public function test_status_is_cast_to_php_status_enum(): void
    {
        // Arrange
        Event::fake();
        $serverPhp = ServerPhp::factory()->create([
            'status' => TaskStatus::Active,
        ]);

        // Act
        $status = $serverPhp->status;

        // Assert
        $this->assertInstanceOf(TaskStatus::class, $status);
        $this->assertEquals(TaskStatus::Active, $status);
    }

    /**
     * Test status can be set to different enum values.
     */
    public function test_status_can_be_set_to_different_enum_values(): void
    {
        // Arrange & Act
        Event::fake();
        $serverPhp = ServerPhp::factory()->create([
            'status' => TaskStatus::Pending,
        ]);

        // Assert
        $this->assertEquals(TaskStatus::Pending, $serverPhp->status);

        // Act - update status
        $serverPhp->update(['status' => TaskStatus::Installing]);

        // Assert
        $this->assertEquals(TaskStatus::Installing, $serverPhp->fresh()->status);
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
        ServerPhp::factory()->create(['server_id' => $server->id]);

        // Assert
        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    /**
     * Test updated event dispatches ServerUpdated when status changes.
     */
    public function test_updated_event_dispatches_server_updated_when_status_changes(): void
    {
        // Arrange
        Event::fake([ServerUpdated::class]);
        $serverPhp = ServerPhp::factory()->create(['status' => TaskStatus::Pending]);

        // Act
        $serverPhp->update(['status' => TaskStatus::Active]);

        // Assert - should have dispatched twice (once for create, once for update)
        Event::assertDispatched(ServerUpdated::class, 2);
    }

    /**
     * Test updated event dispatches ServerUpdated when is cli default changes.
     */
    public function test_updated_event_dispatches_server_updated_when_is_cli_default_changes(): void
    {
        // Arrange
        Event::fake([ServerUpdated::class]);
        $serverPhp = ServerPhp::factory()->create(['is_cli_default' => false]);

        // Act
        $serverPhp->update(['is_cli_default' => true]);

        // Assert - should have dispatched twice (once for create, once for update)
        Event::assertDispatched(ServerUpdated::class, 2);
    }

    /**
     * Test updated event dispatches ServerUpdated when is site default changes.
     */
    public function test_updated_event_dispatches_server_updated_when_is_site_default_changes(): void
    {
        // Arrange
        Event::fake([ServerUpdated::class]);
        $serverPhp = ServerPhp::factory()->create(['is_site_default' => false]);

        // Act
        $serverPhp->update(['is_site_default' => true]);

        // Assert - should have dispatched twice (once for create, once for update)
        Event::assertDispatched(ServerUpdated::class, 2);
    }

    /**
     * Test updated event dispatches ServerUpdated when version changes.
     */
    public function test_updated_event_dispatches_server_updated_when_version_changes(): void
    {
        // Arrange
        Event::fake([ServerUpdated::class]);
        $serverPhp = ServerPhp::factory()->create(['version' => '8.2']);

        // Act
        $serverPhp->update(['version' => '8.3']);

        // Assert - should have dispatched twice (once for create, once for update)
        Event::assertDispatched(ServerUpdated::class, 2);
    }

    /**
     * Test updated event does not dispatch when non broadcast fields change.
     */
    public function test_updated_event_does_not_dispatch_when_non_broadcast_fields_change(): void
    {
        // Arrange
        Event::fake([ServerUpdated::class]);
        $serverPhp = ServerPhp::factory()->create();

        // Act - touch the model (updates updated_at but not broadcast fields)
        $serverPhp->touch();

        // Assert - should only have dispatched once (for create, not for touch)
        Event::assertDispatched(ServerUpdated::class, 1);
    }

    /**
     * Test deleted event dispatches ServerUpdated event.
     */
    public function test_deleted_event_dispatches_server_updated_event(): void
    {
        // Arrange
        Event::fake([ServerUpdated::class]);
        $serverPhp = ServerPhp::factory()->create();
        $serverId = $serverPhp->server_id;

        // Act
        $serverPhp->delete();

        // Assert - should have dispatched twice (once for create, once for delete)
        Event::assertDispatched(ServerUpdated::class, 2);
    }

    /**
     * Test factory creates server php with correct attributes.
     */
    public function test_factory_creates_server_php_with_correct_attributes(): void
    {
        // Act
        Event::fake();
        $serverPhp = ServerPhp::factory()->create();

        // Assert
        $this->assertNotNull($serverPhp->server_id);
        $this->assertNotNull($serverPhp->version);
        $this->assertIsBool($serverPhp->is_cli_default);
        $this->assertIsBool($serverPhp->is_site_default);
        $this->assertInstanceOf(TaskStatus::class, $serverPhp->status);
        $this->assertEquals(TaskStatus::Active, $serverPhp->status);
    }

    /**
     * Test version can store different PHP version formats.
     */
    public function test_version_can_store_different_formats(): void
    {
        // Arrange & Act
        Event::fake();
        $php1 = ServerPhp::factory()->create(['version' => '8.3']);
        $php2 = ServerPhp::factory()->create(['version' => '8.1']);
        $php3 = ServerPhp::factory()->create(['version' => '8.2.15']);

        // Assert
        $this->assertEquals('8.3', $php1->version);
        $this->assertEquals('8.1', $php2->version);
        $this->assertEquals('8.2.15', $php3->version);
    }

    /**
     * Test can have both cli and site defaults false.
     */
    public function test_can_have_both_defaults_false(): void
    {
        // Arrange & Act
        Event::fake();
        $serverPhp = ServerPhp::factory()->create([
            'is_cli_default' => false,
            'is_site_default' => false,
        ]);

        // Assert
        $this->assertFalse($serverPhp->is_cli_default);
        $this->assertFalse($serverPhp->is_site_default);
    }

    /**
     * Test can have both cli and site defaults true.
     */
    public function test_can_have_both_defaults_true(): void
    {
        // Arrange & Act
        Event::fake();
        $serverPhp = ServerPhp::factory()->create([
            'is_cli_default' => true,
            'is_site_default' => true,
        ]);

        // Assert
        $this->assertTrue($serverPhp->is_cli_default);
        $this->assertTrue($serverPhp->is_site_default);
    }
}
