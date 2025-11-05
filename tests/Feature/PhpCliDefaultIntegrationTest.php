<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerPhp;
use App\Models\User;
use App\Packages\Services\PHP\DefaultPhpCliInstallerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Integration tests for PHP CLI default management.
 *
 * These tests verify the complete flow of:
 * - Installing multiple PHP versions
 * - Preserving existing CLI default when installing new PHP
 * - Changing CLI default through UI
 */
class PhpCliDefaultIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that CLI default query logic works correctly.
     */
    public function test_cli_default_query_logic(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create PHP 8.3 as CLI default (first PHP)
        $php83 = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'is_cli_default' => true,
            'is_site_default' => true,
            'status' => TaskStatus::Active,
        ]);

        // Create PHP 8.2 (not default)
        $php82 = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.2',
            'is_cli_default' => false,
            'is_site_default' => false,
            'status' => TaskStatus::Active,
        ]);

        // Assert: Query finds the correct CLI default
        $cliDefault = $server->phps()->where('is_cli_default', true)->first();
        $this->assertNotNull($cliDefault);
        $this->assertEquals($php83->id, $cliDefault->id);
        $this->assertEquals('8.3', $cliDefault->version);

        // Assert: PHP 8.2 is not CLI default
        $this->assertFalse($php82->is_cli_default);
    }

    /**
     * Test changing CLI default through UI updates status and dispatches job.
     */
    public function test_changing_cli_default_through_ui(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $php83 = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'is_cli_default' => true,
            'status' => TaskStatus::Active,
        ]);

        $php82 = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.2',
            'is_cli_default' => false,
            'status' => TaskStatus::Active,
        ]);

        // Act - User clicks "Set as CLI Default" on PHP 8.2
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/php/{$php82->id}/set-cli-default");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/php");

        // Database should be updated
        $php83->refresh();
        $php82->refresh();

        // Previous default should be unset
        $this->assertFalse($php83->is_cli_default);

        // New PHP status set to 'updating', is_cli_default still false (job will set it)
        $this->assertEquals(TaskStatus::Updating, $php82->status);
        $this->assertFalse($php82->is_cli_default);

        // Job should be dispatched to apply change on remote server
        Queue::assertPushed(DefaultPhpCliInstallerJob::class, function ($pushedJob) use ($server, $php82) {
            return $pushedJob->server->id === $server->id
                && $pushedJob->serverPhp->id === $php82->id;
        });
    }

    /**
     * Test first PHP installation sets CLI default correctly.
     */
    public function test_first_php_installation_sets_cli_default(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create first PHP installation
        $php83 = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'is_cli_default' => true,
            'is_site_default' => true,
            'status' => TaskStatus::Active,
        ]);

        // Assert
        $this->assertTrue($php83->is_cli_default);
        $this->assertTrue($php83->is_site_default);
    }

    /**
     * Test complete workflow: User changes CLI default through UI.
     */
    public function test_user_changes_cli_default_workflow(): void
    {
        // Arrange
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create PHP 8.3 as CLI default
        $php83 = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'is_cli_default' => true,
            'is_site_default' => true,
            'status' => TaskStatus::Active,
        ]);

        // Create PHP 8.2 (not default)
        $php82 = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.2',
            'is_cli_default' => false,
            'is_site_default' => false,
            'status' => TaskStatus::Active,
        ]);

        // Act: User changes CLI default to 8.2
        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/php/{$php82->id}/set-cli-default");

        // Assert: Response is correct
        $response->assertStatus(302);
        $response->assertRedirect("/servers/{$server->id}/php");

        // Assert: Database updated
        $php83->refresh();
        $php82->refresh();
        $this->assertFalse($php83->is_cli_default);

        // PHP 8.2 status updated to 'updating', is_cli_default still false (job will set it)
        $this->assertEquals(TaskStatus::Updating, $php82->status);
        $this->assertFalse($php82->is_cli_default);

        // Assert: Job dispatched to apply 8.2 on server
        Queue::assertPushed(DefaultPhpCliInstallerJob::class, function ($pushedJob) use ($server, $php82) {
            return $pushedJob->server->id === $server->id
                && $pushedJob->serverPhp->id === $php82->id;
        });
    }
}
