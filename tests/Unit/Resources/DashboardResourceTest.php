<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\DashboardResource;
use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Enums\Connection;
use App\Packages\Enums\ProvisionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_transforms_dashboard_data_with_servers_sites_activities(): void
    {
        $server = Server::factory()->create([
            'vanity_name' => 'test-server',
            'public_ip' => '192.168.1.100',
            'connection' => Connection::CONNECTED,
            'provision_status' => ProvisionStatus::Completed,
        ]);

        $site = ServerSite::factory()->for($server)->create([
            'domain' => 'example.com',
        ]);

        $activities = collect([
            [
                'id' => 1,
                'type' => 'server.created',
                'label' => 'Server created',
                'description' => 'Test description',
                'detail' => 'Test detail',
                'created_at' => now()->toISOString(),
                'created_at_human' => 'just now',
            ],
        ]);

        $resource = new DashboardResource([
            'servers' => collect([$server]),
            'sites' => collect([$site]),
            'activities' => $activities,
        ]);

        $array = $resource->toArray(request());

        $this->assertArrayHasKey('servers', $array);
        $this->assertArrayHasKey('sites', $array);
        $this->assertArrayHasKey('activities', $array);
        $this->assertCount(1, $array['servers']);
        $this->assertCount(1, $array['sites']);
        $this->assertCount(1, $array['activities']);
    }

    public function test_it_transforms_server_data_correctly(): void
    {
        $server = Server::factory()->create([
            'vanity_name' => 'web-server',
            'public_ip' => '10.0.0.1',
            'ssh_port' => 22,
            'connection' => Connection::CONNECTED,
            'provision_status' => ProvisionStatus::Completed,
        ]);

        $resource = new DashboardResource([
            'servers' => collect([$server]),
            'sites' => collect([]),
            'activities' => collect([]),
        ]);

        $array = $resource->toArray(request());

        $this->assertEquals($server->id, $array['servers'][0]['id']);
        $this->assertEquals('web-server', $array['servers'][0]['name']);
        $this->assertEquals('10.0.0.1', $array['servers'][0]['public_ip']);
        $this->assertEquals(22, $array['servers'][0]['ssh_port']);
        $this->assertEquals('connected', $array['servers'][0]['connection']);
        $this->assertEquals('completed', $array['servers'][0]['provision_status']);
    }

    public function test_it_transforms_site_data_correctly(): void
    {
        $server = Server::factory()->create(['vanity_name' => 'test-server']);
        $site = ServerSite::factory()->for($server)->create([
            'domain' => 'example.com',
            'php_version' => '8.3',
            'ssl_enabled' => true,
        ]);

        $resource = new DashboardResource([
            'servers' => collect([]),
            'sites' => collect([$site]),
            'activities' => collect([]),
        ]);

        $array = $resource->toArray(request());

        $this->assertEquals($site->id, $array['sites'][0]['id']);
        $this->assertEquals('example.com', $array['sites'][0]['domain']);
        $this->assertEquals('8.3', $array['sites'][0]['php_version']);
        $this->assertTrue($array['sites'][0]['ssl_enabled']);
        $this->assertEquals('test-server', $array['sites'][0]['server_name']);
    }

    public function test_it_transforms_activity_data_correctly(): void
    {
        $activities = collect([
            [
                'id' => 1,
                'type' => 'server.created',
                'label' => 'Server created',
                'description' => 'New server deployed',
                'detail' => '192.168.1.1',
                'created_at' => now()->toISOString(),
                'created_at_human' => '5 minutes ago',
            ],
        ]);

        $resource = new DashboardResource([
            'servers' => collect([]),
            'sites' => collect([]),
            'activities' => $activities,
        ]);

        $array = $resource->toArray(request());

        $this->assertEquals(1, $array['activities'][0]['id']);
        $this->assertEquals('server.created', $array['activities'][0]['type']);
        $this->assertEquals('Server created', $array['activities'][0]['label']);
        $this->assertEquals('New server deployed', $array['activities'][0]['description']);
        $this->assertEquals('192.168.1.1', $array['activities'][0]['detail']);
    }

    public function test_it_handles_empty_collections(): void
    {
        $resource = new DashboardResource([
            'servers' => collect([]),
            'sites' => collect([]),
            'activities' => collect([]),
        ]);

        $array = $resource->toArray(request());

        $this->assertArrayHasKey('servers', $array);
        $this->assertArrayHasKey('sites', $array);
        $this->assertArrayHasKey('activities', $array);
        $this->assertEmpty($array['servers']);
        $this->assertEmpty($array['sites']);
        $this->assertEmpty($array['activities']);
    }

    public function test_it_handles_server_without_relationships(): void
    {
        $server = Server::factory()->create();

        $resource = new DashboardResource([
            'servers' => collect([$server]),
            'sites' => collect([]),
            'activities' => collect([]),
        ]);

        $array = $resource->toArray(request());

        $this->assertNull($array['servers'][0]['php_version']);
        $this->assertEquals(0, $array['servers'][0]['sites_count']);
        $this->assertEquals(0, $array['servers'][0]['supervisor_tasks_count']);
        $this->assertEquals(0, $array['servers'][0]['scheduled_tasks_count']);
    }
}
