<?php

namespace App\Packages\Services\Monitoring\Events;

use App\Models\Server;
use App\Models\ServerMonitor;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MonitorTriggeredEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ServerMonitor $monitor,
        public Server $server,
        public float $currentValue,
    ) {}
}
