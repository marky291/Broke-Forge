<?php

namespace Tests\Feature\Feature\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerFirewall;
use App\Models\ServerFirewallRule;
use App\Models\User;
use App\Packages\Services\Firewall\FirewallRuleInstallerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerFirewallControllerRetryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can retry a failed firewall rule installation.
     */
    public function test_user_can_retry_failed_firewall_rule_installation(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'name' => 'HTTP',
            'port' => '80',
            'status' => TaskStatus::Failed,
            'error_log' => 'Installation failed',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall/{$rule->id}/retry");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect();

        // Verify rule status reset to pending
        $this->assertDatabaseHas('server_firewall_rules', [
            'id' => $rule->id,
            'status' => TaskStatus::Pending->value,
            'error_log' => null,
        ]);

        // Verify job was dispatched
        Queue::assertPushed(FirewallRuleInstallerJob::class, function ($job) use ($server, $rule) {
            return $job->server->id === $server->id
                && $job->serverFirewallRule->id === $rule->id;
        });
    }

    /**
     * Test cannot retry firewall rule that is not failed.
     */
    public function test_cannot_retry_firewall_rule_that_is_not_failed(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'status' => TaskStatus::Active,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall/{$rule->id}/retry");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('error', 'Only failed firewall rules can be retried');

        // Verify status was not changed
        $this->assertDatabaseHas('server_firewall_rules', [
            'id' => $rule->id,
            'status' => TaskStatus::Active->value,
        ]);

        // Verify no job was dispatched
        Queue::assertNothingPushed();
    }

    /**
     * Test user cannot retry firewall rule on another user's server.
     */
    public function test_user_cannot_retry_firewall_rule_on_another_users_server(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'status' => TaskStatus::Failed,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall/{$rule->id}/retry");

        // Assert
        $response->assertStatus(403);

        // Verify status was not changed
        $this->assertDatabaseHas('server_firewall_rules', [
            'id' => $rule->id,
            'status' => TaskStatus::Failed->value,
        ]);

        Queue::assertNothingPushed();
    }

    /**
     * Test guest cannot retry firewall rule installation.
     */
    public function test_guest_cannot_retry_firewall_rule_installation(): void
    {
        // Arrange
        Queue::fake();
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'status' => TaskStatus::Failed,
        ]);

        // Act
        $response = $this->post("/servers/{$server->id}/firewall/{$rule->id}/retry");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
        Queue::assertNothingPushed();
    }

    /**
     * Test retry returns error when firewall rule does not belong to server.
     */
    public function test_retry_returns_error_when_firewall_rule_does_not_belong_to_server(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server1 = Server::factory()->create(['user_id' => $user->id]);
        $server2 = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server2->id]);
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'status' => TaskStatus::Failed,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server1->id}/firewall/{$rule->id}/retry");

        // Assert
        $response->assertStatus(302);
        $response->assertSessionHas('error', 'Invalid firewall rule.');
        Queue::assertNothingPushed();
    }

    /**
     * Test retry logs audit information.
     */
    public function test_retry_logs_audit_information(): void
    {
        // Arrange
        Log::spy();
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'name' => 'HTTPS',
            'port' => '443',
            'status' => TaskStatus::Failed,
        ]);

        // Act
        $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall/{$rule->id}/retry");

        // Assert
        Log::shouldHaveReceived('info')
            ->once()
            ->with('Firewall rule installation retry initiated', \Mockery::on(function ($context) use ($user, $server, $rule) {
                return $context['user_id'] === $user->id
                    && $context['server_id'] === $server->id
                    && $context['rule_id'] === $rule->id
                    && $context['rule_name'] === 'HTTPS'
                    && $context['port'] === '443';
            }));

        // PHPUnit assertion to avoid risky test warning
        $this->assertTrue(true);
    }

    /**
     * Test retry clears error log when resetting status.
     */
    public function test_retry_clears_error_log_when_resetting_status(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'status' => TaskStatus::Failed,
            'error_log' => 'Previous error message',
        ]);

        // Act
        $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall/{$rule->id}/retry");

        // Assert
        $this->assertDatabaseHas('server_firewall_rules', [
            'id' => $rule->id,
            'status' => TaskStatus::Pending->value,
            'error_log' => null,
        ]);
    }

    /**
     * Test retry dispatches installer job with correct parameters.
     */
    public function test_retry_dispatches_installer_job_with_correct_parameters(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'name' => 'SSH',
            'port' => '22',
            'status' => TaskStatus::Failed,
        ]);

        // Act
        $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall/{$rule->id}/retry");

        // Assert
        Queue::assertPushed(FirewallRuleInstallerJob::class, function ($job) use ($server, $rule) {
            return $job->server->id === $server->id
                && $job->serverFirewallRule->id === $rule->id;
        });
    }
}
