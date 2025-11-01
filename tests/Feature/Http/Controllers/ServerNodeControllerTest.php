<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerNode;
use App\Models\User;
use App\Packages\Services\Node\ComposerUpdaterJob;
use App\Packages\Services\Node\NodeInstallerJob;
use App\Packages\Services\Node\NodeRemoverJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerNodeControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can view node page for their server.
     */
    public function test_user_can_view_node_page(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/node");

        // Assert
        $response->assertStatus(200);
    }

    /**
     * Test user cannot view node page for another users server.
     */
    public function test_user_cannot_view_node_page_for_another_users_server(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $otherUser = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        // Act
        $response = $this->actingAs($user)->get("/servers/{$server->id}/node");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test guest cannot view node page.
     */
    public function test_guest_cannot_view_node_page(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->get("/servers/{$server->id}/node");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test user can install node version on their server.
     */
    public function test_user_can_install_node_version(): void
    {
        Queue::fake();

        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)->post("/servers/{$server->id}/node/install", [
            'version' => '22',
        ]);

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/node");
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('server_nodes', [
            'server_id' => $server->id,
            'version' => '22',
            'status' => TaskStatus::Pending->value,
            'is_default' => true, // First node is default
        ]);

        Queue::assertPushed(NodeInstallerJob::class);
    }

    /**
     * Test first node installation sets as default.
     */
    public function test_first_node_installation_sets_as_default(): void
    {
        Queue::fake();

        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)->post("/servers/{$server->id}/node/install", [
            'version' => '20',
        ]);

        // Assert
        $response->assertStatus(302);
        $this->assertDatabaseHas('server_nodes', [
            'server_id' => $server->id,
            'version' => '20',
            'is_default' => true,
        ]);
    }

    /**
     * Test subsequent node installations do not set as default.
     */
    public function test_subsequent_node_installations_do_not_set_as_default(): void
    {
        Queue::fake();

        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerNode::factory()->create([
            'server_id' => $server->id,
            'version' => '22',
            'is_default' => true,
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)->post("/servers/{$server->id}/node/install", [
            'version' => '20',
        ]);

        // Assert
        $response->assertStatus(302);
        $this->assertDatabaseHas('server_nodes', [
            'server_id' => $server->id,
            'version' => '20',
            'is_default' => false,
        ]);
    }

    /**
     * Test cannot install duplicate node version.
     */
    public function test_cannot_install_duplicate_node_version(): void
    {
        Queue::fake();

        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create(['user_id' => $user->id]);
        ServerNode::factory()->create([
            'server_id' => $server->id,
            'version' => '22',
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)->post("/servers/{$server->id}/node/install", [
            'version' => '22',
        ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('error');

        Queue::assertNothingPushed();
    }

    /**
     * Test node installation validates required version field.
     */
    public function test_node_installation_validates_required_version_field(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)->post("/servers/{$server->id}/node/install", []);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['version']);
    }

    /**
     * Test node installation validates version is in allowed list.
     */
    public function test_node_installation_validates_version_is_in_allowed_list(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)->post("/servers/{$server->id}/node/install", [
            'version' => '99',
        ]);

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['version']);
    }

    /**
     * Test user cannot install node on another users server.
     */
    public function test_user_cannot_install_node_on_another_users_server(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $otherUser = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        // Act
        $response = $this->actingAs($user)->post("/servers/{$server->id}/node/install", [
            'version' => '22',
        ]);

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user can set default node version.
     */
    public function test_user_can_set_default_node_version(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create(['user_id' => $user->id]);
        $node1 = ServerNode::factory()->create([
            'server_id' => $server->id,
            'version' => '22',
            'is_default' => true,
            'status' => TaskStatus::Active,
        ]);
        $node2 = ServerNode::factory()->create([
            'server_id' => $server->id,
            'version' => '20',
            'is_default' => false,
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)->patch("/servers/{$server->id}/node/{$node2->id}/set-default");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('server_nodes', [
            'id' => $node1->id,
            'is_default' => false,
        ]);
        $this->assertDatabaseHas('server_nodes', [
            'id' => $node2->id,
            'is_default' => true,
        ]);
    }

    /**
     * Test user cannot set default for another users server.
     */
    public function test_user_cannot_set_default_for_another_users_server(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $otherUser = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create(['user_id' => $otherUser->id]);
        $node = ServerNode::factory()->create([
            'server_id' => $server->id,
            'version' => '22',
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)->patch("/servers/{$server->id}/node/{$node->id}/set-default");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user can remove non-default node version.
     */
    public function test_user_can_remove_non_default_node_version(): void
    {
        Queue::fake();

        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create(['user_id' => $user->id]);
        $defaultNode = ServerNode::factory()->create([
            'server_id' => $server->id,
            'version' => '22',
            'is_default' => true,
            'status' => TaskStatus::Active,
        ]);
        $node = ServerNode::factory()->create([
            'server_id' => $server->id,
            'version' => '20',
            'is_default' => false,
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)->delete("/servers/{$server->id}/node/{$node->id}");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('server_nodes', [
            'id' => $node->id,
            'status' => TaskStatus::Pending->value,
        ]);

        Queue::assertPushed(NodeRemoverJob::class);
    }

    /**
     * Test cannot remove default node version.
     */
    public function test_cannot_remove_default_node_version(): void
    {
        Queue::fake();

        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create(['user_id' => $user->id]);
        $node = ServerNode::factory()->create([
            'server_id' => $server->id,
            'version' => '22',
            'is_default' => true,
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)->delete("/servers/{$server->id}/node/{$node->id}");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('server_nodes', [
            'id' => $node->id,
            'status' => TaskStatus::Active->value,
        ]);

        Queue::assertNothingPushed();
    }

    /**
     * Test user cannot remove node from another users server.
     */
    public function test_user_cannot_remove_node_from_another_users_server(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $otherUser = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create(['user_id' => $otherUser->id]);
        $node = ServerNode::factory()->create([
            'server_id' => $server->id,
            'version' => '22',
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)->delete("/servers/{$server->id}/node/{$node->id}");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user can retry failed node installation.
     */
    public function test_user_can_retry_failed_node_installation(): void
    {
        Queue::fake();

        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create(['user_id' => $user->id]);
        $node = ServerNode::factory()->create([
            'server_id' => $server->id,
            'version' => '22',
            'status' => TaskStatus::Failed,
            'error_log' => 'Installation failed',
        ]);

        // Act
        $response = $this->actingAs($user)->post("/servers/{$server->id}/node/{$node->id}/retry");

        // Assert
        $response->assertStatus(302);

        $this->assertDatabaseHas('server_nodes', [
            'id' => $node->id,
            'status' => TaskStatus::Pending->value,
            'error_log' => null,
        ]);

        Queue::assertPushed(NodeInstallerJob::class);
    }

    /**
     * Test cannot retry non-failed node installation.
     */
    public function test_cannot_retry_non_failed_node_installation(): void
    {
        Queue::fake();

        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create(['user_id' => $user->id]);
        $node = ServerNode::factory()->create([
            'server_id' => $server->id,
            'version' => '22',
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)->post("/servers/{$server->id}/node/{$node->id}/retry");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('error');

        Queue::assertNothingPushed();
    }

    /**
     * Test user can update composer to latest version.
     */
    public function test_user_can_update_composer(): void
    {
        Queue::fake();

        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'composer_version' => '2.5.0',
            'composer_status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)->post("/servers/{$server->id}/composer/update");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'composer_status' => TaskStatus::Installing->value,
            'composer_error_log' => null,
        ]);

        Queue::assertPushed(ComposerUpdaterJob::class);
    }

    /**
     * Test cannot update composer if not installed.
     */
    public function test_cannot_update_composer_if_not_installed(): void
    {
        Queue::fake();

        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'composer_version' => null,
        ]);

        // Act
        $response = $this->actingAs($user)->post("/servers/{$server->id}/composer/update");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('error');

        Queue::assertNothingPushed();
    }

    /**
     * Test user can retry failed composer update.
     */
    public function test_user_can_retry_failed_composer_update(): void
    {
        Queue::fake();

        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'composer_version' => '2.5.0',
            'composer_status' => TaskStatus::Failed,
            'composer_error_log' => 'Update failed',
        ]);

        // Act
        $response = $this->actingAs($user)->post("/servers/{$server->id}/composer/retry");

        // Assert
        $response->assertStatus(302);

        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'composer_status' => TaskStatus::Installing->value,
            'composer_error_log' => null,
        ]);

        Queue::assertPushed(ComposerUpdaterJob::class);
    }

    /**
     * Test cannot retry non-failed composer update.
     */
    public function test_cannot_retry_non_failed_composer_update(): void
    {
        Queue::fake();

        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'composer_version' => '2.6.0',
            'composer_status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)->post("/servers/{$server->id}/composer/retry");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('error');

        Queue::assertNothingPushed();
    }
}
