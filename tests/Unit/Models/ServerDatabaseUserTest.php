<?php

namespace Tests\Unit\Models;

use App\Enums\TaskStatus;
use App\Models\ServerDatabase;
use App\Models\ServerDatabaseUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerDatabaseUserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test is_root is fillable.
     */
    public function test_is_root_is_fillable(): void
    {
        // Arrange
        $database = ServerDatabase::factory()->create();

        // Act
        $user = ServerDatabaseUser::create([
            'server_database_id' => $database->id,
            'is_root' => true,
            'username' => 'root',
            'password' => 'password123',
            'host' => 'localhost',
            'privileges' => 'all',
            'status' => TaskStatus::Active,
        ]);

        // Assert
        $this->assertTrue($user->is_root);
        $this->assertDatabaseHas('server_database_users', [
            'id' => $user->id,
            'is_root' => true,
        ]);
    }

    /**
     * Test is_root defaults to false when explicitly set.
     */
    public function test_is_root_defaults_to_false(): void
    {
        // Arrange
        $database = ServerDatabase::factory()->create();

        // Act
        $user = ServerDatabaseUser::create([
            'server_database_id' => $database->id,
            'is_root' => false,
            'username' => 'testuser',
            'password' => 'password123',
            'host' => 'localhost',
            'privileges' => 'read_write',
            'status' => TaskStatus::Active,
        ]);

        // Assert
        $this->assertFalse($user->is_root);
        $this->assertDatabaseHas('server_database_users', [
            'id' => $user->id,
            'is_root' => false,
        ]);
    }

    /**
     * Test is_root is cast to boolean.
     */
    public function test_is_root_is_cast_to_boolean(): void
    {
        // Arrange
        $database = ServerDatabase::factory()->create();

        // Act
        $user = ServerDatabaseUser::create([
            'server_database_id' => $database->id,
            'is_root' => 1,
            'username' => 'root',
            'password' => 'password123',
            'host' => 'localhost',
            'privileges' => 'all',
            'status' => TaskStatus::Active,
        ]);

        // Assert - verify it's a boolean, not integer
        $this->assertIsBool($user->is_root);
        $this->assertTrue($user->is_root);
    }

    /**
     * Test root user can be created.
     */
    public function test_root_user_can_be_created(): void
    {
        // Arrange
        $database = ServerDatabase::factory()->create();

        // Act
        $user = ServerDatabaseUser::create([
            'server_database_id' => $database->id,
            'is_root' => true,
            'username' => 'root',
            'password' => 'rootpassword',
            'host' => 'localhost',
            'privileges' => 'all',
            'status' => TaskStatus::Active,
        ]);

        // Assert
        $this->assertInstanceOf(ServerDatabaseUser::class, $user);
        $this->assertTrue($user->is_root);
        $this->assertEquals('root', $user->username);
        $this->assertEquals('localhost', $user->host);
    }

    /**
     * Test non-root user can be created.
     */
    public function test_non_root_user_can_be_created(): void
    {
        // Arrange
        $database = ServerDatabase::factory()->create();

        // Act
        $user = ServerDatabaseUser::create([
            'server_database_id' => $database->id,
            'is_root' => false,
            'username' => 'appuser',
            'password' => 'password123',
            'host' => '%',
            'privileges' => 'read_write',
            'status' => TaskStatus::Active,
        ]);

        // Assert
        $this->assertInstanceOf(ServerDatabaseUser::class, $user);
        $this->assertFalse($user->is_root);
        $this->assertEquals('appuser', $user->username);
    }
}
