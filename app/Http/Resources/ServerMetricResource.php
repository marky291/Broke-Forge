<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServerMetricResource extends JsonResource
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
            'server_id' => $this->server_id,
            'cpu_usage' => (float) $this->cpu_usage,
            'memory_total_mb' => (int) $this->memory_total_mb,
            'memory_used_mb' => (int) $this->memory_used_mb,
            'memory_usage_percentage' => (float) $this->memory_usage_percentage,
            'storage_total_gb' => (int) $this->storage_total_gb,
            'storage_used_gb' => (int) $this->storage_used_gb,
            'storage_usage_percentage' => (float) $this->storage_usage_percentage,
            'collected_at' => $this->collected_at->toISOString(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
