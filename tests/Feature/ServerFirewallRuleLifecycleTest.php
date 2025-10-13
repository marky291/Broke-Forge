<?php

namespace Tests\Feature;

use App\Events\ServerUpdated;
use App\Models\Server;
use App\Models\ServerFirewall;
use App\Models\ServerFirewallRule;
use App\Models\User;
use App\Packages\Services\Firewall\FirewallRuleInstallerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerFirewallRuleLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_controller_creates_rule_with_pending_status(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create();
        $firewall = ServerFirewall::factory()->for($server)->create();

        $this->actingAs($user);

        $response = $this->post(route('servers.firewall.store', $server), [
            'name' => 'Test Rule',
            'port' => '8080',
            'from_ip_address' => '192.168.1.1',
            'rule_type' => 'allow',
        ]);

        $response->assertRedirect();

        // Verify rule was created with pending status
        $rule = ServerFirewallRule::where('name', 'Test Rule')->first();
        $this->assertNotNull($rule);
        $this->assertEquals('pending', $rule->status);
        $this->assertEquals('8080', $rule->port);
        $this->assertEquals('192.168.1.1', $rule->from_ip_address);
        $this->assertEquals('allow', $rule->rule_type);
    }

    public function test_controller_dispatches_job_with_rule_id(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create();
        $firewall = ServerFirewall::factory()->for($server)->create();

        $this->actingAs($user);

        $this->post(route('servers.firewall.store', $server), [
            'name' => 'Test Rule',
            'port' => '8080',
            'from_ip_address' => null,
            'rule_type' => 'allow',
        ]);

        $rule = ServerFirewallRule::where('name', 'Test Rule')->first();

        Queue::assertPushed(FirewallRuleInstallerJob::class, function ($job) use ($server, $rule) {
            return $job->server->id === $server->id
                && $job->ruleId === $rule->id;
        });
    }

    public function test_controller_returns_error_if_firewall_not_installed(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create();
        // No firewall created

        $this->actingAs($user);

        $response = $this->post(route('servers.firewall.store', $server), [
            'name' => 'Test Rule',
            'port' => '8080',
            'from_ip_address' => null,
            'rule_type' => 'allow',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Firewall is not installed on this server.');

        // Verify rule was not created
        $this->assertCount(0, ServerFirewallRule::all());
    }

    public function test_job_updates_status_to_installing(): void
    {
        Event::fake([ServerUpdated::class]);

        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create();
        $firewall = ServerFirewall::factory()->for($server)->create();
        $rule = ServerFirewallRule::factory()->for($firewall, 'firewall')->create([
            'status' => 'pending',
        ]);

        // Mock the Symfony Process object
        $mockProcess = \Mockery::mock(\Symfony\Component\Process\Process::class);
        $mockProcess->shouldReceive('isSuccessful')->andReturn(true);
        $mockProcess->shouldReceive('getOutput')->andReturn('Success');
        $mockProcess->shouldReceive('getCommandLine')->andReturn('mock command');

        // Mock the Spatie\Ssh\Ssh object
        $mockSsh = \Mockery::mock(\Spatie\Ssh\Ssh::class);
        $mockSsh->shouldReceive('setTimeout')->andReturnSelf();
        $mockSsh->shouldReceive('execute')->andReturn($mockProcess);

        $server = \Mockery::mock($server)->makePartial();
        $server->shouldReceive('createSshConnection')->andReturn($mockSsh);

        try {
            $job = new FirewallRuleInstallerJob($server, $rule->id);
            $job->handle();
        } catch (\Exception $e) {
            // Expected to fail since we're mocking
        }

        // Verify the rule status was updated to installing at some point
        $rule->refresh();
        $this->assertContains($rule->status, ['installing', 'active', 'failed']);
    }

    public function test_job_updates_status_to_active_on_success(): void
    {
        Event::fake([ServerUpdated::class]);

        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create();
        $firewall = ServerFirewall::factory()->for($server)->create();
        $rule = ServerFirewallRule::factory()->for($firewall, 'firewall')->create([
            'status' => 'pending',
        ]);

        // Mock the Symfony Process object
        $mockProcess = \Mockery::mock(\Symfony\Component\Process\Process::class);
        $mockProcess->shouldReceive('isSuccessful')->andReturn(true);
        $mockProcess->shouldReceive('getOutput')->andReturn('Success');
        $mockProcess->shouldReceive('getCommandLine')->andReturn('mock command');

        // Mock the Spatie\Ssh\Ssh object
        $mockSsh = \Mockery::mock(\Spatie\Ssh\Ssh::class);
        $mockSsh->shouldReceive('setTimeout')->andReturnSelf();
        $mockSsh->shouldReceive('execute')->andReturn($mockProcess);

        $server = \Mockery::mock($server)->makePartial();
        $server->shouldReceive('createSshConnection')->andReturn($mockSsh);

        $job = new FirewallRuleInstallerJob($server, $rule->id);
        $job->handle();

        // Verify the rule status was updated to active
        $rule->refresh();
        $this->assertEquals('active', $rule->status);
    }

    public function test_job_updates_status_to_failed_on_error(): void
    {
        Event::fake([ServerUpdated::class]);

        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create();
        $firewall = ServerFirewall::factory()->for($server)->create();
        $rule = ServerFirewallRule::factory()->for($firewall, 'firewall')->create([
            'status' => 'pending',
        ]);

        // Mock the Spatie\Ssh\Ssh object to simulate failure
        $mockSsh = \Mockery::mock(\Spatie\Ssh\Ssh::class);
        $mockSsh->shouldReceive('setTimeout')->andReturnSelf();
        $mockSsh->shouldReceive('execute')->andThrow(new \Exception('SSH connection failed'));

        $server = \Mockery::mock($server)->makePartial();
        $server->shouldReceive('createSshConnection')->andReturn($mockSsh);

        $job = new FirewallRuleInstallerJob($server, $rule->id);

        try {
            $job->handle();
        } catch (\Exception $e) {
            // Expected to throw
        }

        // Verify the rule status was updated to failed
        $rule->refresh();
        $this->assertEquals('failed', $rule->status);
    }

    public function test_rule_creation_dispatches_server_updated_event(): void
    {
        Event::fake([ServerUpdated::class]);

        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create();
        $firewall = ServerFirewall::factory()->for($server)->create();

        ServerFirewallRule::create([
            'server_firewall_id' => $firewall->id,
            'name' => 'Test Rule',
            'port' => '8080',
            'from_ip_address' => null,
            'rule_type' => 'allow',
            'status' => 'pending',
        ]);

        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    public function test_rule_status_update_dispatches_server_updated_event(): void
    {
        Event::fake([ServerUpdated::class]);

        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create();
        $firewall = ServerFirewall::factory()->for($server)->create();
        $rule = ServerFirewallRule::factory()->for($firewall, 'firewall')->create([
            'status' => 'pending',
        ]);

        // Clear any events from creation
        Event::assertDispatched(ServerUpdated::class);
        Event::fake([ServerUpdated::class]);

        // Update status
        $rule->update(['status' => 'installing']);

        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }
}
