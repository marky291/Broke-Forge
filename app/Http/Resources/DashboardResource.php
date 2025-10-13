<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'servers' => $this->transformServers($this->resource['servers']),
            'sites' => $this->transformSites($this->resource['sites']),
            'activities' => $this->transformActivities($this->resource['activities']),
        ];
    }

    /**
     * Transform servers collection
     */
    protected function transformServers($servers): array
    {
        return $servers->map(fn ($server) => [
            'id' => $server->id,
            'name' => $server->vanity_name,
            'provider' => $server->provider?->value,
            'public_ip' => $server->public_ip,
            'ssh_port' => $server->ssh_port,
            'connection' => $server->connection?->value,
            'provision_status' => $server->provision_status?->value,
            'monitoring_status' => $server->monitoring_status?->value,
            'scheduler_status' => $server->scheduler_status?->value,
            'supervisor_status' => $server->supervisor_status?->value,
            'php_version' => $server->defaultPhp?->version,
            'sites_count' => $server->sites->count(),
            'supervisor_tasks_count' => $server->supervisorTasks->count(),
            'scheduled_tasks_count' => $server->scheduledTasks->count(),
        ])->toArray();
    }

    /**
     * Transform sites collection
     */
    protected function transformSites($sites): array
    {
        return $sites->map(fn ($site) => [
            'id' => $site->id,
            'domain' => $site->domain,
            'repository' => $site->getGitConfiguration()['repository'],
            'php_version' => $site->php_version,
            'status' => $site->status,
            'health' => $site->health,
            'git_status' => $site->git_status?->value,
            'ssl_enabled' => $site->ssl_enabled,
            'server_id' => $site->server_id,
            'server_name' => $site->server->vanity_name,
            'last_deployed_at' => $site->last_deployed_at?->toISOString(),
            'last_deployed_at_human' => $site->last_deployed_at_human,
        ])->toArray();
    }

    /**
     * Transform activities collection
     */
    protected function transformActivities($activities): array
    {
        return $activities->map(fn ($activity) => [
            'id' => $activity['id'],
            'type' => $activity['type'],
            'label' => $activity['label'],
            'description' => $activity['description'],
            'detail' => $activity['detail'] ?? null,
            'created_at' => $activity['created_at'],
            'created_at_human' => $activity['created_at_human'],
        ])->toArray();
    }
}
