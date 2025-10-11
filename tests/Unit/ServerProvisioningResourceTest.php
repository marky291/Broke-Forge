<?php

namespace Tests\Unit;

use App\Http\Resources\ServerProvisioningResource;
use App\Models\Server;
use App\Packages\Enums\ProvisionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerProvisioningResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_transforms_server_with_basic_details(): void
    {
        $server = Server::factory()->create([
            'vanity_name' => 'web-server-01',
            'public_ip' => '192.168.1.100',
            'private_ip' => '10.0.0.1',
            'ssh_port' => 22,
            'provision_status' => ProvisionStatus::Installing,
            'os_name' => 'Ubuntu',
            'os_version' => '24.04',
            'os_codename' => 'Noble Numbat',
        ]);

        $resource = new ServerProvisioningResource($server);
        $array = $resource->toArray(request());

        $this->assertEquals($server->id, $array['id']);
        $this->assertEquals('web-server-01', $array['vanity_name']);
        $this->assertEquals('192.168.1.100', $array['public_ip']);
        $this->assertEquals('10.0.0.1', $array['private_ip']);
        $this->assertEquals(22, $array['ssh_port']);
        $this->assertEquals('installing', $array['provision_status']);
        $this->assertEquals('Installing services', $array['provision_status_label']);
        $this->assertEquals('blue', $array['provision_status_color']);
        $this->assertEquals('Ubuntu', $array['os_name']);
        $this->assertEquals('24.04', $array['os_version']);
        $this->assertEquals('Noble Numbat', $array['os_codename']);
    }

    public function test_it_includes_static_provisioning_steps(): void
    {
        $server = Server::factory()->create([
            'provision_status' => ProvisionStatus::Pending,
        ]);

        $resource = new ServerProvisioningResource($server);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('steps', $array);
        $this->assertIsArray($array['steps']);
        $this->assertCount(7, $array['steps']);

        // Verify first step
        $this->assertEquals('Waiting on your server to become ready', $array['steps'][0]['name']);
        $this->assertStringContainsString('waiting to hear from your server', $array['steps'][0]['description']);
        $this->assertEquals(ProvisionStatus::Pending, $array['steps'][0]['status']);

        // Verify some middle steps
        $this->assertEquals('Installing PHP', $array['steps'][4]['name']);
        $this->assertEquals('Installing Nginx', $array['steps'][5]['name']);

        // Verify last step
        $this->assertEquals('Making final touches', $array['steps'][6]['name']);
    }

    public function test_all_steps_have_required_fields(): void
    {
        $server = Server::factory()->create([
            'provision_status' => ProvisionStatus::Installing,
        ]);

        $resource = new ServerProvisioningResource($server);
        $array = $resource->toArray(request());

        foreach ($array['steps'] as $step) {
            $this->assertArrayHasKey('name', $step);
            $this->assertArrayHasKey('description', $step);
            $this->assertArrayHasKey('status', $step);
            $this->assertInstanceOf(ProvisionStatus::class, $step['status']);
        }
    }
}
