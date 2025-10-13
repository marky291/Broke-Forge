<?php

namespace Tests\Unit\Events;

use App\Events\ServerUpdated;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Tests\TestCase;

class ServerUpdatedTest extends TestCase
{
    public function test_implements_should_broadcast_now(): void
    {
        $event = new ServerUpdated(1);

        $this->assertInstanceOf(ShouldBroadcastNow::class, $event);
    }

    public function test_broadcasts_on_correct_private_channel(): void
    {
        $serverId = 123;
        $event = new ServerUpdated($serverId);

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals("private-servers.{$serverId}", $channels[0]->name);
    }

    public function test_payload_structure(): void
    {
        $serverId = 456;
        $event = new ServerUpdated($serverId);

        $payload = $event->broadcastWith();

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('server_id', $payload);
        $this->assertArrayHasKey('timestamp', $payload);
        $this->assertEquals($serverId, $payload['server_id']);
        $this->assertNotEmpty($payload['timestamp']);
    }

    public function test_timestamp_is_iso8601_format(): void
    {
        $event = new ServerUpdated(1);

        $payload = $event->broadcastWith();

        $timestamp = $payload['timestamp'];
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $timestamp);
    }
}
