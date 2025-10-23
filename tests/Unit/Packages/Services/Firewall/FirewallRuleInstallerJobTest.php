<?php

namespace Tests\Unit\Packages\Services\Firewall;

use App\Enums\FirewallRuleStatus;
use App\Models\Server;
use App\Models\ServerFirewallRule;
use App\Packages\Services\Firewall\FirewallRuleInstallerJob;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirewallRuleInstallerJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test job has correct timeout property.
     */
    public function test_job_has_correct_timeout_property(): void
    {
        $server = Server::factory()->create();
        $firewall = \App\Models\ServerFirewall::factory()->create(['server_id' => $server->id]);
        $rule = ServerFirewallRule::factory()->create(['server_firewall_id' => $firewall->id]);
        $job = new FirewallRuleInstallerJob($server, $rule->id);
        $this->assertEquals(600, $job->timeout);
    }

    /**
     * Test job has correct tries property.
     */
    public function test_job_has_correct_tries_property(): void
    {
        $server = Server::factory()->create();
        $firewall = \App\Models\ServerFirewall::factory()->create(['server_id' => $server->id]);
        $rule = ServerFirewallRule::factory()->create(['server_firewall_id' => $firewall->id]);
        $job = new FirewallRuleInstallerJob($server, $rule->id);
        $this->assertEquals(0, $job->tries);
    }

    /**
     * Test job has correct maxExceptions property.
     */
    public function test_job_has_correct_max_exceptions_property(): void
    {
        $server = Server::factory()->create();
        $firewall = \App\Models\ServerFirewall::factory()->create(['server_id' => $server->id]);
        $rule = ServerFirewallRule::factory()->create(['server_firewall_id' => $firewall->id]);
        $job = new FirewallRuleInstallerJob($server, $rule->id);
        $this->assertEquals(3, $job->maxExceptions);
    }

    /**
     * Test middleware is configured with WithoutOverlapping.
     */
    public function test_middleware_configured_with_without_overlapping(): void
    {
        $server = Server::factory()->create();
        $firewall = \App\Models\ServerFirewall::factory()->create(['server_id' => $server->id]);
        $rule = ServerFirewallRule::factory()->create(['server_firewall_id' => $firewall->id]);
        $job = new FirewallRuleInstallerJob($server, $rule->id);
        $middleware = $job->middleware();
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(\Illuminate\Queue\Middleware\WithoutOverlapping::class, $middleware[0]);
    }

    /**
     * Test job constructor accepts server and rule ID.
     */
    public function test_constructor_accepts_server_and_id(): void
    {
        $server = Server::factory()->create();
        $ruleId = 123;
        $job = new FirewallRuleInstallerJob($server, $ruleId);
        $this->assertInstanceOf(FirewallRuleInstallerJob::class, $job);
        $this->assertEquals($server->id, $job->server->id);
        $this->assertEquals($ruleId, $job->ruleId);
    }

    /**
     * Test failed() method updates status to FirewallRuleStatus::Failed.
     */
    public function test_failed_method_updates_status_to_failed(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = \App\Models\ServerFirewall::factory()->create([
            'server_id' => $server->id,
        ]);
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'status' => FirewallRuleStatus::Installing->value,
        ]);

        $job = new FirewallRuleInstallerJob($server, $rule->id);
        $exception = new Exception('Firewall configuration failed');

        // Act
        $job->failed($exception);

        // Assert
        $rule->refresh();
        $this->assertEquals(FirewallRuleStatus::Failed, $rule->status);
    }

    /**
     * Test failed() method stores error message.
     */
    public function test_failed_method_stores_error_log(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = \App\Models\ServerFirewall::factory()->create([
            'server_id' => $server->id,
        ]);
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'status' => FirewallRuleStatus::Installing->value,
            'error_log' => null,
        ]);

        $job = new FirewallRuleInstallerJob($server, $rule->id);
        $errorMessage = 'UFW command execution failed';
        $exception = new Exception($errorMessage);

        // Act
        $job->failed($exception);

        // Assert
        $rule->refresh();
        $this->assertEquals($errorMessage, $rule->error_log);
    }

    /**
     * Test failed() method handles missing records gracefully.
     */
    public function test_failed_method_handles_missing_records_gracefully(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $nonExistentId = 99999;

        $job = new FirewallRuleInstallerJob($server, $nonExistentId);
        $exception = new Exception('Test error');

        // Act - should not throw exception
        $job->failed($exception);

        // Assert - verify no firewall rule was created
        $this->assertDatabaseMissing('server_firewall_rules', [
            'id' => $nonExistentId,
        ]);
    }

    /**
     * Test catch block sets failed status immediately when exception occurs.
     */
    public function test_catch_block_sets_failed_status_immediately(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = \App\Models\ServerFirewall::factory()->create([
            'server_id' => $server->id,
        ]);
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'status' => FirewallRuleStatus::Pending->value,
            'port' => 8080,
        ]);

        $job = new FirewallRuleInstallerJob($server, $rule->id);

        // Act & Assert - handle() will throw exception because installer cannot run
        // We expect the catch block to set status to failed
        try {
            $job->handle();
        } catch (Exception $e) {
            // Expected to throw
        }

        // Assert
        $rule->refresh();
        $this->assertEquals(FirewallRuleStatus::Failed, $rule->status);
        $this->assertNotNull($rule->error_log);
    }

    /**
     * Test failed() method updates from any status to failed.
     */
    public function test_failed_method_updates_from_any_status_to_failed(): void
    {
        // Arrange - test from pending status
        $server = Server::factory()->create();
        $firewall = \App\Models\ServerFirewall::factory()->create([
            'server_id' => $server->id,
        ]);
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'status' => FirewallRuleStatus::Pending->value,
        ]);

        $job = new FirewallRuleInstallerJob($server, $rule->id);
        $exception = new Exception('Network timeout error');

        // Act
        $job->failed($exception);

        // Assert
        $rule->refresh();
        $this->assertEquals(FirewallRuleStatus::Failed, $rule->status);
        $this->assertEquals('Network timeout error', $rule->error_log);
    }

    /**
     * Test failed() method preserves firewall rule data except status and error.
     */
    public function test_failed_method_preserves_firewall_rule_data(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = \App\Models\ServerFirewall::factory()->create([
            'server_id' => $server->id,
        ]);
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'status' => FirewallRuleStatus::Installing->value,
            'name' => 'HTTP Access',
            'port' => 80,
            'from_ip_address' => '192.168.1.1',
            'rule_type' => 'allow',
        ]);

        $job = new FirewallRuleInstallerJob($server, $rule->id);
        $exception = new Exception('Configuration error');

        // Act
        $job->failed($exception);

        // Assert - verify other fields remain unchanged
        $rule->refresh();
        $this->assertEquals('HTTP Access', $rule->name);
        $this->assertEquals(80, $rule->port);
        $this->assertEquals('192.168.1.1', $rule->from_ip_address);
        $this->assertEquals('allow', $rule->rule_type);
        $this->assertEquals($firewall->id, $rule->server_firewall_id);
    }

    /**
     * Test failed() method handles different exception types.
     */
    public function test_failed_method_handles_different_exception_types(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = \App\Models\ServerFirewall::factory()->create([
            'server_id' => $server->id,
        ]);
        $rule = ServerFirewallRule::factory()->create([
            'server_firewall_id' => $firewall->id,
            'status' => FirewallRuleStatus::Installing->value,
        ]);

        $job = new FirewallRuleInstallerJob($server, $rule->id);
        $exception = new \RuntimeException('Runtime error occurred');

        // Act
        $job->failed($exception);

        // Assert
        $rule->refresh();
        $this->assertEquals(FirewallRuleStatus::Failed, $rule->status);
        $this->assertEquals('Runtime error occurred', $rule->error_log);
    }
}
