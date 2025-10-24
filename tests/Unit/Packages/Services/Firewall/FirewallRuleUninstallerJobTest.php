<?php

namespace Tests\Unit\Packages\Services\Firewall;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerFirewall;
use App\Models\ServerFirewallRule;
use App\Packages\Services\Firewall\FirewallRuleUninstallerJob;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirewallRuleUninstallerJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_has_correct_timeout_property(): void
    {
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);
        $rule = ServerFirewallRule::factory()->create(['server_firewall_id' => $firewall->id]);
        $job = new FirewallRuleUninstallerJob($server, $rule);
        $this->assertEquals(600, $job->timeout);
    }

    public function test_job_has_correct_tries_property(): void
    {
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);
        $rule = ServerFirewallRule::factory()->create(['server_firewall_id' => $firewall->id]);
        $job = new FirewallRuleUninstallerJob($server, $rule);
        $this->assertEquals(0, $job->tries);
    }

    /**
     * Test job has correct maxExceptions property.
     */
    public function test_job_has_correct_max_exceptions_property(): void
    {
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);
        $rule = ServerFirewallRule::factory()->create(['server_firewall_id' => $firewall->id]);
        $job = new FirewallRuleUninstallerJob($server, $rule);
        $this->assertEquals(3, $job->maxExceptions);
    }

    public function test_middleware_configured_with_without_overlapping(): void
    {
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);
        $rule = ServerFirewallRule::factory()->create(['server_firewall_id' => $firewall->id]);
        $job = new FirewallRuleUninstallerJob($server, $rule);
        $middleware = $job->middleware();
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(\Illuminate\Queue\Middleware\WithoutOverlapping::class, $middleware[0]);
    }

    public function test_constructor_accepts_server_and_rule(): void
    {
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);
        $rule = ServerFirewallRule::factory()->create(['server_firewall_id' => $firewall->id]);
        $job = new FirewallRuleUninstallerJob($server, $rule);
        $this->assertInstanceOf(FirewallRuleUninstallerJob::class, $job);
        $this->assertEquals($server->id, $job->server->id);
        $this->assertEquals($rule->id, $job->serverFirewallRule->id);
    }

    public function test_failed_method_updates_status_to_failed(): void
    {
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);
        $rule = ServerFirewallRule::factory()->create(['server_firewall_id' => $firewall->id]);
        $job = new FirewallRuleUninstallerJob($server, $rule);
        $exception = new Exception('Operation failed');
        $job->failed($exception);
        $rule->refresh();
        $this->assertEquals(TaskStatus::Failed, $rule->status);
    }

    public function test_failed_method_stores_error_log(): void
    {
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);
        $rule = ServerFirewallRule::factory()->create(['server_firewall_id' => $firewall->id, 'error_log' => null]);
        $job = new FirewallRuleUninstallerJob($server, $rule);
        $errorMessage = 'Test error message';
        $exception = new Exception($errorMessage);
        $job->failed($exception);
        $rule->refresh();
        $this->assertEquals($errorMessage, $rule->error_log);
    }

    public function test_failed_method_handles_missing_records_gracefully(): void
    {
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);
        $rule = ServerFirewallRule::factory()->create(['server_firewall_id' => $firewall->id]);

        $job = new FirewallRuleUninstallerJob($server, $rule);
        $ruleId = $rule->id;
        $rule->delete(); // Now fresh() will return null

        $exception = new Exception('Test error');
        $job->failed($exception);
        $this->assertDatabaseMissing('server_firewall_rules', ['id' => $ruleId]);
    }
}
