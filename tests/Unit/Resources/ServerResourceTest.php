<?php

namespace Tests\Unit\Resources;

use App\Enums\MonitoringStatus;
use App\Http\Resources\ServerResource;
use App\Models\Server;
use App\Models\ServerEvent;
use App\Models\ServerFirewall;
use App\Models\ServerFirewallRule;
use App\Models\ServerMetric;
use App\Packages\Enums\Connection;
use App\Packages\Enums\ProvisionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_transforms_basic_server_data(): void
    {
        $server = Server::factory()->create([
            'vanity_name' => 'web-server',
            'public_ip' => '192.168.1.100',
            'private_ip' => '10.0.0.1',
            'ssh_port' => 22,
            'connection' => Connection::CONNECTED,
            'provision_status' => ProvisionStatus::Completed,
        ]);

        $resource = new ServerResource($server);
        $array = $resource->toArray(request());

        $this->assertEquals($server->id, $array['id']);
        $this->assertEquals('web-server', $array['vanity_name']);
        $this->assertEquals('192.168.1.100', $array['public_ip']);
        $this->assertEquals('10.0.0.1', $array['private_ip']);
        $this->assertEquals(22, $array['ssh_port']);
        $this->assertEquals('connected', $array['connection']);
        $this->assertEquals('completed', $array['provision_status']);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
    }

    public function test_it_indicates_firewall_not_installed_when_no_firewall(): void
    {
        $server = Server::factory()->create();

        $resource = new ServerResource($server);
        $array = $resource->toArray(request());

        $this->assertFalse($array['isFirewallInstalled']);
        $this->assertEquals('not_installed', $array['firewallStatus']);
        $this->assertEmpty($array['rules']);
    }

    public function test_it_indicates_firewall_installed_when_firewall_exists(): void
    {
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->for($server)->create([
            'is_enabled' => true,
        ]);

        $resource = new ServerResource($server);
        $array = $resource->toArray(request());

        $this->assertTrue($array['isFirewallInstalled']);
        $this->assertEquals('enabled', $array['firewallStatus']);
    }

    public function test_it_shows_firewall_as_disabled_when_not_enabled(): void
    {
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->for($server)->create([
            'is_enabled' => false,
        ]);

        $resource = new ServerResource($server);
        $array = $resource->toArray(request());

        $this->assertTrue($array['isFirewallInstalled']);
        $this->assertEquals('disabled', $array['firewallStatus']);
    }

    public function test_it_transforms_firewall_rules(): void
    {
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->for($server)->create();
        $rule = ServerFirewallRule::factory()->for($firewall, 'firewall')->create([
            'name' => 'Allow HTTP',
            'port' => '80',
            'from_ip_address' => '0.0.0.0/0',
            'rule_type' => 'allow',
            'status' => 'active',
        ]);

        $resource = new ServerResource($server);
        $array = $resource->toArray(request());

        $this->assertCount(1, $array['rules']);
        $this->assertEquals($rule->id, $array['rules'][0]['id']);
        $this->assertEquals('Allow HTTP', $array['rules'][0]['name']);
        $this->assertEquals('80', $array['rules'][0]['port']);
        $this->assertEquals('0.0.0.0/0', $array['rules'][0]['from_ip_address']);
        $this->assertEquals('allow', $array['rules'][0]['rule_type']);
        $this->assertEquals('active', $array['rules'][0]['status']);
        $this->assertArrayHasKey('created_at', $array['rules'][0]);
    }

    public function test_it_transforms_recent_firewall_events(): void
    {
        $server = Server::factory()->create();

        ServerEvent::factory()->for($server)->create([
            'service_type' => 'firewall',
            'milestone' => 'Firewall enabled',
            'status' => 'success',
            'current_step' => 1,
            'total_steps' => 1,
        ]);

        $resource = new ServerResource($server);
        $array = $resource->toArray(request());

        $this->assertCount(1, $array['recentEvents']);
        $this->assertEquals('Firewall enabled', $array['recentEvents'][0]['milestone']);
        $this->assertEquals('success', $array['recentEvents'][0]['status']);
    }

    public function test_it_limits_recent_events_to_five(): void
    {
        $server = Server::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            ServerEvent::factory()->for($server)->create([
                'service_type' => 'firewall',
                'milestone' => "Event $i",
            ]);
        }

        $resource = new ServerResource($server);
        $array = $resource->toArray(request());

        $this->assertCount(5, $array['recentEvents']);
    }

    public function test_it_returns_null_metrics_when_monitoring_not_active(): void
    {
        $server = Server::factory()->create([
            'monitoring_status' => MonitoringStatus::Uninstalled,
        ]);

        ServerMetric::factory()->for($server)->create();

        $resource = new ServerResource($server);
        $array = $resource->toArray(request());

        $this->assertNull($array['latestMetrics']);
    }

    public function test_it_returns_latest_metrics_when_monitoring_active(): void
    {
        $server = Server::factory()->create([
            'monitoring_status' => MonitoringStatus::Active,
        ]);

        $metric = ServerMetric::factory()->for($server)->create([
            'cpu_usage' => 45.5,
            'memory_total_mb' => 8192,
            'memory_used_mb' => 4096,
            'memory_usage_percentage' => 50.0,
        ]);

        $resource = new ServerResource($server);
        $array = $resource->toArray(request());

        $this->assertNotNull($array['latestMetrics']);
        $this->assertEquals(45.5, $array['latestMetrics']['cpu_usage']);
        $this->assertEquals(8192, $array['latestMetrics']['memory_total_mb']);
        $this->assertEquals(4096, $array['latestMetrics']['memory_used_mb']);
        $this->assertEquals(50.0, $array['latestMetrics']['memory_usage_percentage']);
    }

    public function test_it_handles_server_without_firewall_or_metrics(): void
    {
        $server = Server::factory()->create();

        $resource = new ServerResource($server);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('vanity_name', $array);
        $this->assertFalse($array['isFirewallInstalled']);
        $this->assertEquals('not_installed', $array['firewallStatus']);
        $this->assertEmpty($array['rules']);
        $this->assertEmpty($array['recentEvents']);
        $this->assertNull($array['latestMetrics']);
    }
}
