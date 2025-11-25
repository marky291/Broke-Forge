<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\DatabaseType;
use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Models\User;
use App\Packages\Services\Database\Redis\RedisInstallerJob;
use App\Packages\Services\Database\Redis\RedisRemoverJob;
use App\Packages\Services\Database\Redis\RedisUpdaterJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerCacheQueueControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test guest cannot access cache-queue page.
     */
    public function test_guest_cannot_access_cache_queue_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test authenticated user can access their server's cache-queue page.
     */
    public function test_user_can_access_their_server_cache_queue_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
    }

    /**
     * Test user cannot access other users server cache-queue page.
     */
    public function test_user_cannot_access_other_users_server_cache_queue_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test cache-queue page renders correct Inertia component.
     */
    public function test_cache_queue_page_renders_correct_inertia_component(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server')
        );
    }

    /**
     * Test cache-queue page includes server data.
     */
    public function test_cache_queue_page_includes_server_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Cache Server',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->where('server.id', $server->id)
            ->where('server.vanity_name', 'Cache Server')
        );
    }

    /**
     * Test cache-queue page includes Redis instances.
     */
    public function test_cache_queue_page_includes_redis_instances(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::Redis,
            'version' => '7.2',
            'port' => 6379,
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 1)
            ->where('server.databases.0.type', 'redis')
        );
    }

    /**
     * Test cache-queue page shows empty state when no services installed.
     */
    public function test_cache_queue_page_shows_empty_state_when_no_services_installed(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 0)
        );
    }

    /**
     * Test cache-queue page includes service status.
     */
    public function test_cache_queue_page_includes_service_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::Redis,
            'status' => TaskStatus::Installing,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('server.databases', 1)
            ->where('server.databases.0.status', TaskStatus::Installing->value)
        );
    }

    /**
     * Test user can install Redis service.
     */
    public function test_user_can_install_redis_service(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'name' => 'redis-cache',
                'type' => 'redis',
                'version' => '7.2',
                'port' => 6379,
                'root_password' => 'securepassword123',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/services");
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('server_databases', [
            'server_id' => $server->id,
            'type' => 'redis',
            'version' => '7.2',
            'port' => 6379,
            'status' => TaskStatus::Pending->value,
        ]);

        Queue::assertPushed(RedisInstallerJob::class);
    }

    /**
     * Test Redis installation requires valid password.
     */
    public function test_redis_installation_requires_valid_password(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'type' => 'redis',
                'version' => '7.2',
                'port' => 6379,
                'root_password' => 'weak', // Too short
            ]);

        // Assert
        $response->assertSessionHasErrors(['root_password']);
        $this->assertDatabaseMissing('server_databases', [
            'server_id' => $server->id,
            'type' => 'redis',
        ]);
    }

    /**
     * Test Redis installation prevents port conflicts.
     */
    public function test_redis_installation_prevents_port_conflicts(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create existing database on port 6379
        ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'port' => 6379,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'type' => 'redis',
                'version' => '7.2',
                'port' => 6379, // Conflicting port
                'root_password' => 'securepassword123',
            ]);

        // Assert
        $response->assertSessionHasErrors(['port']);
    }

    /**
     * Test user can update Redis version.
     */
    public function test_user_can_update_redis_version(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $redis = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::Redis,
            'version' => '7.0',
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/databases/{$redis->id}", [
                'version' => '7.2',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/services");

        $this->assertDatabaseHas('server_databases', [
            'id' => $redis->id,
            'version' => '7.2',
            'status' => TaskStatus::Updating->value,
        ]);

        Queue::assertPushed(RedisUpdaterJob::class);
    }

    /**
     * Test user cannot update Redis while it is installing.
     */
    public function test_user_cannot_update_redis_while_installing(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $redis = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::Redis,
            'status' => TaskStatus::Installing,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/databases/{$redis->id}", [
                'version' => '7.2',
            ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('error');
    }

    /**
     * Test user can uninstall Redis service.
     */
    public function test_user_can_uninstall_redis_service(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $redis = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::Redis,
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/databases/{$redis->id}");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/services");

        $this->assertDatabaseHas('server_databases', [
            'id' => $redis->id,
            'status' => TaskStatus::Pending->value,
        ]);

        Queue::assertPushed(RedisRemoverJob::class);
    }

    /**
     * Test user cannot install Redis on other user's server.
     */
    public function test_user_cannot_install_redis_on_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/databases", [
                'name' => 'redis_cache',
                'type' => 'redis',
                'version' => '7.2',
                'port' => 6379,
                'root_password' => 'securepassword123',
            ]);

        // Assert
        $response->assertStatus(403);
        $this->assertDatabaseMissing('server_databases', [
            'server_id' => $server->id,
            'type' => 'redis',
        ]);
    }

    /**
     * Test user cannot update Redis on other user's server.
     */
    public function test_user_cannot_update_redis_on_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        $redis = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::Redis,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/databases/{$redis->id}", [
                'version' => '7.2',
            ]);

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user cannot delete Redis on other user's server.
     */
    public function test_user_cannot_delete_redis_on_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        $redis = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'type' => DatabaseType::Redis,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/databases/{$redis->id}");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test cache-queue page receives availableCacheQueue prop (not availableDatabases).
     */
    public function test_cache_queue_page_receives_available_cache_queue_prop(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('availableCacheQueue')
        );
    }

    /**
     * Test cache-queue page only includes cache/queue services in available types.
     */
    public function test_cache_queue_page_only_includes_cache_queue_services(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/services");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/services')
            ->has('availableCacheQueue', fn ($availableCacheQueue) => $availableCacheQueue
                ->has('redis')
                ->missing('mysql')
                ->missing('mariadb')
                ->missing('postgresql')
            )
        );
    }
}
