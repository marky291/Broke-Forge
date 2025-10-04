<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServerScheduledTaskRunResource extends JsonResource
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
            'server_scheduled_task_id' => $this->server_scheduled_task_id,
            'started_at' => $this->started_at->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'exit_code' => $this->exit_code,
            'output' => $this->output,
            'error_output' => $this->error_output,
            'duration_ms' => $this->duration_ms,
            'was_successful' => $this->wasSuccessful(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
