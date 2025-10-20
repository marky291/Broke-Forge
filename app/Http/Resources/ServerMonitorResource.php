<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServerMonitorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'server_id' => $this->server_id,
            'name' => $this->name,
            'metric_type' => $this->metric_type,
            'operator' => $this->operator,
            'threshold' => (float) $this->threshold,
            'duration_minutes' => $this->duration_minutes,
            'notification_emails' => $this->notification_emails,
            'enabled' => $this->enabled,
            'cooldown_minutes' => $this->cooldown_minutes,
            'last_triggered_at' => $this->last_triggered_at?->toISOString(),
            'last_recovered_at' => $this->last_recovered_at?->toISOString(),
            'status' => $this->status,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
