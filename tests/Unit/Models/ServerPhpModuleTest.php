<?php

namespace Tests\Unit\Models;

use App\Models\ServerPhp;
use App\Models\ServerPhpModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ServerPhpModuleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test server php module belongs to server php.
     */
    public function test_belongs_to_server_php(): void
    {
        // Arrange
        Event::fake();
        $serverPhp = ServerPhp::factory()->create();
        $module = ServerPhpModule::factory()->create([
            'server_php_id' => $serverPhp->id,
        ]);

        // Act
        $result = $module->php;

        // Assert
        $this->assertInstanceOf(ServerPhp::class, $result);
        $this->assertEquals($serverPhp->id, $result->id);
    }

    /**
     * Test is enabled is cast to boolean.
     */
    public function test_is_enabled_is_cast_to_boolean(): void
    {
        // Arrange
        Event::fake();
        $module = ServerPhpModule::factory()->create([
            'is_enabled' => 1,
        ]);

        // Act
        $isEnabled = $module->is_enabled;

        // Assert
        $this->assertIsBool($isEnabled);
        $this->assertTrue($isEnabled);
    }

    /**
     * Test is enabled can be false.
     */
    public function test_is_enabled_can_be_false(): void
    {
        // Arrange
        Event::fake();
        $module = ServerPhpModule::factory()->create([
            'is_enabled' => false,
        ]);

        // Act
        $isEnabled = $module->is_enabled;

        // Assert
        $this->assertIsBool($isEnabled);
        $this->assertFalse($isEnabled);
    }

    /**
     * Test is enabled can be updated.
     */
    public function test_is_enabled_can_be_updated(): void
    {
        // Arrange
        Event::fake();
        $module = ServerPhpModule::factory()->create([
            'is_enabled' => true,
        ]);

        // Act
        $module->update(['is_enabled' => false]);

        // Assert
        $this->assertFalse($module->fresh()->is_enabled);
    }

    /**
     * Test factory creates module with correct attributes.
     */
    public function test_factory_creates_module_with_correct_attributes(): void
    {
        // Act
        Event::fake();
        $module = ServerPhpModule::factory()->create();

        // Assert
        $this->assertNotNull($module->server_php_id);
        $this->assertNotNull($module->name);
        $this->assertIsBool($module->is_enabled);
        $this->assertTrue($module->is_enabled);
    }

    /**
     * Test module name can be set to common PHP modules.
     */
    public function test_module_name_can_be_set_to_common_php_modules(): void
    {
        // Arrange & Act
        Event::fake();
        $modules = [
            'gd' => ServerPhpModule::factory()->create(['name' => 'gd']),
            'mbstring' => ServerPhpModule::factory()->create(['name' => 'mbstring']),
            'curl' => ServerPhpModule::factory()->create(['name' => 'curl']),
            'xml' => ServerPhpModule::factory()->create(['name' => 'xml']),
            'zip' => ServerPhpModule::factory()->create(['name' => 'zip']),
            'bcmath' => ServerPhpModule::factory()->create(['name' => 'bcmath']),
            'intl' => ServerPhpModule::factory()->create(['name' => 'intl']),
            'redis' => ServerPhpModule::factory()->create(['name' => 'redis']),
            'memcached' => ServerPhpModule::factory()->create(['name' => 'memcached']),
            'imagick' => ServerPhpModule::factory()->create(['name' => 'imagick']),
        ];

        // Assert
        foreach ($modules as $expectedName => $module) {
            $this->assertEquals($expectedName, $module->name);
        }
    }

    /**
     * Test multiple modules can belong to same server php.
     */
    public function test_multiple_modules_can_belong_to_same_server_php(): void
    {
        // Arrange
        Event::fake();
        $serverPhp = ServerPhp::factory()->create();

        // Act
        $module1 = ServerPhpModule::factory()->create([
            'server_php_id' => $serverPhp->id,
            'name' => 'gd',
        ]);
        $module2 = ServerPhpModule::factory()->create([
            'server_php_id' => $serverPhp->id,
            'name' => 'mbstring',
        ]);
        $module3 = ServerPhpModule::factory()->create([
            'server_php_id' => $serverPhp->id,
            'name' => 'curl',
        ]);

        // Assert
        $this->assertEquals($serverPhp->id, $module1->server_php_id);
        $this->assertEquals($serverPhp->id, $module2->server_php_id);
        $this->assertEquals($serverPhp->id, $module3->server_php_id);

        $modules = $serverPhp->modules;
        $this->assertCount(3, $modules);
        $this->assertTrue($modules->contains($module1));
        $this->assertTrue($modules->contains($module2));
        $this->assertTrue($modules->contains($module3));
    }

    /**
     * Test module can be created with is enabled true by default.
     */
    public function test_module_can_be_created_with_is_enabled_true_by_default(): void
    {
        // Arrange & Act
        Event::fake();
        $serverPhp = ServerPhp::factory()->create();
        $module = ServerPhpModule::factory()->create([
            'server_php_id' => $serverPhp->id,
            'name' => 'gd',
        ]);

        // Assert
        $this->assertTrue($module->is_enabled);
    }

    /**
     * Test module can be created with is enabled false.
     */
    public function test_module_can_be_created_with_is_enabled_false(): void
    {
        // Arrange & Act
        Event::fake();
        $serverPhp = ServerPhp::factory()->create();
        $module = ServerPhpModule::factory()->create([
            'server_php_id' => $serverPhp->id,
            'name' => 'imagick',
            'is_enabled' => false,
        ]);

        // Assert
        $this->assertFalse($module->is_enabled);
    }

    /**
     * Test fillable attributes are mass assignable.
     */
    public function test_fillable_attributes_are_mass_assignable(): void
    {
        // Arrange
        Event::fake();
        $serverPhp = ServerPhp::factory()->create();

        // Act
        $module = ServerPhpModule::create([
            'server_php_id' => $serverPhp->id,
            'name' => 'redis',
            'is_enabled' => true,
        ]);

        // Assert
        $this->assertDatabaseHas('server_php_modules', [
            'server_php_id' => $serverPhp->id,
            'name' => 'redis',
            'is_enabled' => true,
        ]);
    }

    /**
     * Test module can be deleted.
     */
    public function test_module_can_be_deleted(): void
    {
        // Arrange
        Event::fake();
        $module = ServerPhpModule::factory()->create([
            'name' => 'gd',
        ]);
        $moduleId = $module->id;

        // Act
        $module->delete();

        // Assert
        $this->assertDatabaseMissing('server_php_modules', [
            'id' => $moduleId,
        ]);
    }

    /**
     * Test module relationship can be eagerly loaded.
     */
    public function test_module_relationship_can_be_eagerly_loaded(): void
    {
        // Arrange
        Event::fake();
        $serverPhp = ServerPhp::factory()->create();
        ServerPhpModule::factory()->create([
            'server_php_id' => $serverPhp->id,
            'name' => 'gd',
        ]);
        ServerPhpModule::factory()->create([
            'server_php_id' => $serverPhp->id,
            'name' => 'curl',
        ]);
        ServerPhpModule::factory()->create([
            'server_php_id' => $serverPhp->id,
            'name' => 'mbstring',
        ]);

        // Act
        $module = ServerPhpModule::with('php')->first();

        // Assert
        $this->assertTrue($module->relationLoaded('php'));
        $this->assertInstanceOf(ServerPhp::class, $module->php);
    }
}
