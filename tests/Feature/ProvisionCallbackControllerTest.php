<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Packages\Enums\Connection;
use App\Packages\Enums\ProvisionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ProvisionCallbackControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_server_has_default_provision_state(): void
    {
        $server = Server::factory()->create();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $server->provision);
        $this->assertFalse($server->provision->isEmpty());
        $this->assertEquals('installing', $server->provision->get(1));
        $this->assertEquals(1, $server->provision->count());
    }

    public function test_it_updates_provision_step_status_for_new_server(): void
    {
        Log::shouldReceive('info')->once();

        $server = Server::factory()->create([
            'provision' => [],
            'provision_status' => ProvisionStatus::Pending,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $server->provision);
        $this->assertEmpty($server->provision);

        $url = URL::signedRoute('servers.provision.step', ['server' => $server->id]);

        $this->post($url, [
            'step' => 1,
            'status' => 'installing',
        ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $server->refresh();
        $this->assertEquals('installing', $server->provision->get(1));
    }

    public function test_it_handles_step_from_query_params(): void
    {
        Log::shouldReceive('info')->once();

        $server = Server::factory()->create([
            'provision' => [],
        ]);

        $url = URL::signedRoute('servers.provision.step', [
            'server' => $server->id,
            'step' => 2,
            'status' => 'completed',
        ]);

        $this->post($url)
            ->assertOk()
            ->assertJson(['ok' => true]);

        $server->refresh();
        $this->assertEquals('completed', $server->provision->get(2));
    }

    public function test_it_updates_server_connection_when_step_1_completes(): void
    {
        Log::shouldReceive('info')->once();

        $server = Server::factory()->create([
            'provision' => [],
            'connection' => Connection::PENDING,
            'provision_status' => ProvisionStatus::Pending,
        ]);

        $url = URL::signedRoute('servers.provision.step', ['server' => $server->id]);

        $this->post($url, [
            'step' => 1,
            'status' => ProvisionStatus::Completed->value,
        ])
            ->assertOk();

        $server->refresh();
        $this->assertEquals(Connection::CONNECTED, $server->connection);
        $this->assertEquals(ProvisionStatus::Installing, $server->provision_status);
    }

    public function test_it_validates_step_number(): void
    {
        $server = Server::factory()->create([
            'provision' => [],
        ]);

        $url = URL::signedRoute('servers.provision.step', ['server' => $server->id]);

        $this->post($url, [
            'step' => 99,
            'status' => 'pending',
        ])
            ->assertStatus(400);
    }

    public function test_it_validates_status_value(): void
    {
        $server = Server::factory()->create([
            'provision' => [],
        ]);

        $url = URL::signedRoute('servers.provision.step', ['server' => $server->id]);

        $this->post($url, [
            'step' => 1,
            'status' => 'invalid-status',
        ])
            ->assertStatus(400);
    }

    public function test_it_stores_multiple_step_updates(): void
    {
        Log::shouldReceive('info')->times(3);

        $server = Server::factory()->create([
            'provision' => [],
        ]);

        $url = URL::signedRoute('servers.provision.step', ['server' => $server->id]);

        // Step 1
        $this->post($url, ['step' => 1, 'status' => 'installing'])->assertOk();
        $server->refresh();
        $this->assertEquals('installing', $server->provision->get(1));

        // Step 2
        $this->post($url, ['step' => 2, 'status' => 'completed'])->assertOk();
        $server->refresh();
        $this->assertEquals('installing', $server->provision->get(1));
        $this->assertEquals('completed', $server->provision->get(2));

        // Update Step 1 to completed - this triggers cleanup and resets provision steps
        $this->post($url, ['step' => 1, 'status' => 'completed'])->assertOk();
        $server->refresh();
        $this->assertEquals('completed', $server->provision->get(1));
        // Step 2 is wiped out by the cleanup logic when step 1 completes
        $this->assertNull($server->provision->get(2));
        $this->assertEquals(1, $server->provision->count());
    }

    public function test_it_marks_provisioning_as_failed_when_step_fails(): void
    {
        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->once();

        $server = Server::factory()->create([
            'provision' => [],
            'provision_status' => ProvisionStatus::Installing,
        ]);

        $url = URL::signedRoute('servers.provision.step', ['server' => $server->id]);

        $this->post($url, [
            'step' => 2,
            'status' => 'failed',
        ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $server->refresh();
        $this->assertEquals('failed', $server->provision->get(2));
        $this->assertEquals(ProvisionStatus::Failed, $server->provision_status);
    }

    public function test_it_cleans_up_server_resources_when_step_1_completes(): void
    {
        Log::shouldReceive('info')->once();

        $server = Server::factory()->create([
            'provision' => ['1' => 'installing', '2' => 'completed'],
            'connection' => Connection::PENDING,
            'provision_status' => ProvisionStatus::Pending,
        ]);

        // Create server resources using factories where available
        \App\Models\ServerEvent::factory()->for($server)->create();
        \App\Models\ServerDatabase::factory()->for($server)->create();
        \App\Models\ServerPhp::factory()->for($server)->create();

        // Create resources without factories
        $server->reverseProxy()->create(['type' => 'nginx', 'status' => 'active']);

        // Create firewall with rules
        $firewall = $server->firewall()->create(['is_enabled' => true]);
        $firewall->rules()->create([
            'name' => 'HTTP',
            'port' => '80',
            'rule_type' => 'allow',
            'status' => 'active',
        ]);
        $firewall->rules()->create([
            'name' => 'HTTPS',
            'port' => '443',
            'rule_type' => 'allow',
            'status' => 'active',
        ]);

        // Verify resources exist before cleanup
        $this->assertEquals(1, $server->events()->count());
        $this->assertEquals(1, $server->databases()->count());
        $this->assertEquals(1, $server->phps()->count());
        $this->assertNotNull($server->reverseProxy);
        $this->assertNotNull($server->firewall);
        $this->assertEquals(2, $server->firewall->rules()->count());

        $url = URL::signedRoute('servers.provision.step', ['server' => $server->id]);

        $this->post($url, [
            'step' => 1,
            'status' => ProvisionStatus::Completed->value,
        ])
            ->assertOk();

        $server->refresh();

        // Verify all resources were deleted
        $this->assertEquals(0, $server->events()->count());
        $this->assertEquals(0, $server->databases()->count());
        $this->assertEquals(0, $server->phps()->count());
        $this->assertNull($server->reverseProxy);
        $this->assertNull($server->firewall);

        // Verify provision state was reset
        $this->assertEquals(ProvisionStatus::Completed->value, $server->provision->get(1));
        $this->assertEquals(1, $server->provision->count());
        $this->assertEquals(Connection::CONNECTED, $server->connection);
        $this->assertEquals(ProvisionStatus::Installing, $server->provision_status);
    }
}
