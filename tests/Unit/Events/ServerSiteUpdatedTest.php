<?php

namespace Tests\Unit\Events;

use App\Events\ServerSiteUpdated;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Tests\TestCase;

class ServerSiteUpdatedTest extends TestCase
{
    public function test_implements_should_broadcast_now(): void
    {
        $event = new ServerSiteUpdated(1);

        $this->assertInstanceOf(ShouldBroadcastNow::class, $event);
    }

    public function test_broadcasts_on_correct_private_channel(): void
    {
        $siteId = 123;
        $event = new ServerSiteUpdated($siteId);

        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertInstanceOf(PrivateChannel::class, $channels[1]);
        $this->assertEquals("private-sites.{$siteId}", $channels[0]->name);
        $this->assertEquals('private-sites', $channels[1]->name);
    }

    public function test_payload_structure(): void
    {
        $siteId = 456;
        $event = new ServerSiteUpdated($siteId);

        $payload = $event->broadcastWith();

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('site_id', $payload);
        $this->assertArrayHasKey('timestamp', $payload);
        $this->assertEquals($siteId, $payload['site_id']);
        $this->assertNotEmpty($payload['timestamp']);
    }

    public function test_timestamp_is_iso8601_format(): void
    {
        $event = new ServerSiteUpdated(1);

        $payload = $event->broadcastWith();

        $timestamp = $payload['timestamp'];
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $timestamp);
    }
}
