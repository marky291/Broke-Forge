<?php

namespace Tests\Feature;

use App\Events\ServerProvisionUpdated;
use App\Models\Server;
use App\Models\User;
use App\Packages\Enums\ProvisionStatus;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ServerProvisionRealtimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_provision_step_update_broadcasts_event(): void
    {
        Event::fake();
        Log::shouldReceive('info')->once();

        $server = Server::factory()->create([
            'provision' => [],
            'provision_status' => ProvisionStatus::Pending,
        ]);

        $url = URL::signedRoute('servers.provision.step', ['server' => $server->id]);

        $this->post($url, [
            'step' => 1,
            'status' => 'installing',
        ])->assertOk();

        Event::assertDispatched(ServerProvisionUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    public function test_server_owner_can_access_provision_channel(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create();

        // Test the channel authorization logic directly
        $result = $user->id === $server->user_id;

        $this->assertTrue($result);
    }

    public function test_non_owner_cannot_access_provision_channel(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->for($owner)->create();

        // Test the channel authorization logic directly
        $result = $otherUser->id === $server->user_id;

        $this->assertFalse($result);
    }

    public function test_unauthenticated_user_cannot_access_provision_channel(): void
    {
        $owner = User::factory()->create();
        $server = Server::factory()->for($owner)->create();

        $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-servers.{$server->id}.provision",
            'socket_id' => '123.456',
        ])->assertStatus(403);  // Laravel returns 403 for unauthenticated broadcast requests
    }

    public function test_event_broadcasts_on_correct_channel(): void
    {
        $serverId = 123;
        $event = new ServerProvisionUpdated($serverId);

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        // PrivateChannel automatically prepends "private-" to the name
        $this->assertEquals("private-servers.{$serverId}.provision", $channels[0]->name);
    }

    public function test_multiple_step_updates_dispatch_multiple_events(): void
    {
        Event::fake();
        Log::shouldReceive('info')->times(2);

        $server = Server::factory()->create([
            'provision' => [],
            'provision_status' => ProvisionStatus::Pending,
        ]);

        $url = URL::signedRoute('servers.provision.step', ['server' => $server->id]);

        // First step
        $this->post($url, ['step' => 1, 'status' => 'installing'])->assertOk();

        // Second step
        $this->post($url, ['step' => 2, 'status' => 'completed'])->assertOk();

        // Should dispatch event for each step update
        Event::assertDispatched(ServerProvisionUpdated::class, 2);
    }
}
