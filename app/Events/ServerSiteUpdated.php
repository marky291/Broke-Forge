<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServerSiteUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $siteId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('sites.'.$this->siteId),  // Specific: sites.10
            new PrivateChannel('sites'),                  // Generic: all user's sites
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'site_id' => $this->siteId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
