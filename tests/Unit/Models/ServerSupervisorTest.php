<?php

namespace Tests\Unit\Models;

use App\Models\ServerSupervisor;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class ServerSupervisorTest extends TestCase
{
    /**
     * Test server supervisor extends model.
     */
    public function test_extends_model(): void
    {
        // Arrange
        $supervisor = new ServerSupervisor;

        // Assert
        $this->assertInstanceOf(Model::class, $supervisor);
    }

    /**
     * Test server supervisor can be instantiated.
     */
    public function test_can_be_instantiated(): void
    {
        // Act
        $supervisor = new ServerSupervisor;

        // Assert
        $this->assertInstanceOf(ServerSupervisor::class, $supervisor);
    }

    /**
     * Test server supervisor is used for policy authorization.
     */
    public function test_is_conceptual_model_for_policy_authorization(): void
    {
        // Arrange
        $supervisor = new ServerSupervisor;

        // Assert - verify it's a valid model instance
        $this->assertInstanceOf(Model::class, $supervisor);
        $this->assertInstanceOf(ServerSupervisor::class, $supervisor);
    }

    /**
     * Test server supervisor has no table defined.
     */
    public function test_has_no_dedicated_table(): void
    {
        // Arrange
        $supervisor = new ServerSupervisor;

        // Act
        $table = $supervisor->getTable();

        // Assert - should use default pluralized table name convention
        $this->assertEquals('server_supervisors', $table);
    }

    /**
     * Test model attributes can be set.
     */
    public function test_model_attributes_can_be_set(): void
    {
        // Arrange
        $supervisor = new ServerSupervisor;

        // Act
        $supervisor->setAttribute('test_attribute', 'test_value');

        // Assert
        $this->assertEquals('test_value', $supervisor->getAttribute('test_attribute'));
    }

    /**
     * Test model is instance of eloquent model.
     */
    public function test_is_instance_of_eloquent_model(): void
    {
        // Act
        $supervisor = new ServerSupervisor;

        // Assert
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Model::class, $supervisor);
    }

    /**
     * Test model can be checked if it exists.
     */
    public function test_model_can_be_checked_if_it_exists(): void
    {
        // Arrange
        $supervisor = new ServerSupervisor;

        // Assert
        $this->assertFalse($supervisor->exists);
    }

    /**
     * Test model has timestamps by default.
     */
    public function test_has_timestamps_by_default(): void
    {
        // Arrange
        $supervisor = new ServerSupervisor;

        // Assert
        $this->assertTrue($supervisor->usesTimestamps());
    }

    /**
     * Test model connection name can be retrieved.
     */
    public function test_connection_name_can_be_retrieved(): void
    {
        // Arrange
        $supervisor = new ServerSupervisor;

        // Act
        $connection = $supervisor->getConnectionName();

        // Assert - should use default connection
        $this->assertNull($connection);
    }

    /**
     * Test model key name is id by default.
     */
    public function test_key_name_is_id_by_default(): void
    {
        // Arrange
        $supervisor = new ServerSupervisor;

        // Act
        $keyName = $supervisor->getKeyName();

        // Assert
        $this->assertEquals('id', $keyName);
    }
}
