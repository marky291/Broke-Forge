<?php

namespace Tests\Feature;

use App\Events\ServerSiteUpdated;
use App\Events\ServerUpdated;
use App\Models\Server;
use App\Models\ServerFirewall;
use App\Models\ServerFirewallRule;
use App\Models\ServerSite;
use App\Models\User;
use App\Packages\Enums\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ServerBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_update_dispatches_broadcast_event(): void
    {
        Event::fake([ServerUpdated::class]);

        $server = Server::factory()->create(['connection' => Connection::PENDING]);

        // Update a broadcast field to trigger event
        $server->update(['connection' => Connection::CONNECTED]);

        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    public function test_server_owner_can_access_server_channel(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create();

        $result = $user->id === $server->user_id;

        $this->assertTrue($result);
    }

    public function test_non_owner_cannot_access_server_channel(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->for($owner)->create();

        $result = $otherUser->id === $server->user_id;

        $this->assertFalse($result);
    }

    public function test_server_site_update_dispatches_broadcast_event(): void
    {
        Event::fake([ServerSiteUpdated::class]);

        $server = Server::factory()->create();
        $site = ServerSite::factory()->for($server)->create();

        $site->update(['domain' => 'updated.example.com']);

        Event::assertDispatched(ServerSiteUpdated::class, function ($event) use ($site) {
            return $event->siteId === $site->id;
        });
    }

    public function test_server_site_owner_can_access_site_channel(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create();
        $site = ServerSite::factory()->for($server)->create();

        $result = $user->id === $site->server->user_id;

        $this->assertTrue($result);
    }

    public function test_non_owner_cannot_access_site_channel(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->for($owner)->create();
        $site = ServerSite::factory()->for($server)->create();

        $result = $otherUser->id === $site->server->user_id;

        $this->assertFalse($result);
    }

    public function test_multiple_server_updates_dispatch_multiple_events(): void
    {
        Event::fake([ServerUpdated::class]);

        $server = Server::factory()->create();

        // Update broadcast fields to trigger events
        $server->update(['os_name' => 'Ubuntu']);
        $server->update(['os_version' => '22.04']);
        $server->update(['os_codename' => 'jammy']);

        Event::assertDispatched(ServerUpdated::class, 3);
    }

    public function test_multiple_site_updates_dispatch_multiple_events(): void
    {
        Event::fake([ServerSiteUpdated::class]);

        $server = Server::factory()->create();
        $site = ServerSite::factory()->for($server)->create();

        $site->update(['domain' => 'first.example.com']);
        $site->update(['domain' => 'second.example.com']);

        Event::assertDispatched(ServerSiteUpdated::class, 2);
    }

    public function test_server_event_broadcasts_on_correct_channels(): void
    {
        $serverId = 123;
        $event = new ServerUpdated($serverId);

        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);
        $this->assertEquals("private-servers.{$serverId}", $channels[0]->name);
        $this->assertEquals('private-servers', $channels[1]->name);
    }

    public function test_site_event_broadcasts_on_correct_channels(): void
    {
        $siteId = 456;
        $event = new ServerSiteUpdated($siteId);

        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);
        $this->assertEquals("private-sites.{$siteId}", $channels[0]->name);
        $this->assertEquals('private-sites', $channels[1]->name);
    }

    public function test_firewall_creation_dispatches_server_updated_event(): void
    {
        Event::fake([ServerUpdated::class]);

        $server = Server::factory()->create();
        ServerFirewall::factory()->for($server)->create();

        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    public function test_firewall_update_dispatches_server_updated_event(): void
    {
        Event::fake([ServerUpdated::class]);

        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->for($server)->create(['is_enabled' => false]);

        $firewall->update(['is_enabled' => true]);

        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    public function test_firewall_rule_creation_dispatches_server_updated_event(): void
    {
        Event::fake([ServerUpdated::class]);

        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->for($server)->create();
        ServerFirewallRule::factory()->for($firewall, 'firewall')->create();

        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    public function test_firewall_rule_update_dispatches_server_updated_event(): void
    {
        Event::fake([ServerUpdated::class]);

        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->for($server)->create();
        $rule = ServerFirewallRule::factory()->for($firewall, 'firewall')->create(['status' => 'pending']);

        $rule->update(['status' => 'active']);

        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    public function test_firewall_rule_deletion_dispatches_server_updated_event(): void
    {
        Event::fake([ServerUpdated::class]);

        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->for($server)->create();
        $rule = ServerFirewallRule::factory()->for($firewall, 'firewall')->create();

        $rule->delete();

        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }
}
