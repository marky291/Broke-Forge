<?php

namespace App\Http\Resources;

use App\Enums\MonitoringStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $firewall = $this->firewall;
        $isFirewallInstalled = $firewall !== null;

        return [
            'id' => $this->id,
            'vanity_name' => $this->vanity_name,
            'provider' => $this->provider?->value,
            'public_ip' => $this->public_ip,
            'private_ip' => $this->private_ip,
            'ssh_port' => $this->ssh_port,
            'connection' => $this->connection?->value,
            'monitoring_status' => $this->monitoring_status?->value,
            'provision_status' => $this->provision_status?->value,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'isFirewallInstalled' => $isFirewallInstalled,
            'firewallStatus' => $this->getFirewallStatus($firewall),
            'rules' => $this->transformFirewallRules($firewall),
            'recentEvents' => $this->transformRecentEvents(),
            'latestMetrics' => $this->getLatestMetrics(),
        ];
    }

    /**
     * Get the firewall status.
     */
    protected function getFirewallStatus($firewall): string
    {
        if (! $firewall) {
            return 'not_installed';
        }

        return $firewall->is_enabled ? 'enabled' : 'disabled';
    }

    /**
     * Transform firewall rules collection.
     */
    protected function transformFirewallRules($firewall): array
    {
        if (! $firewall) {
            return [];
        }

        return $firewall->rules()->latest()->get()->map(fn ($rule) => [
            'id' => $rule->id,
            'name' => $rule->name,
            'port' => $rule->port,
            'from_ip_address' => $rule->from_ip_address,
            'rule_type' => $rule->rule_type,
            'status' => $rule->status,
            'created_at' => $rule->created_at->toISOString(),
        ])->toArray();
    }

    /**
     * Transform recent firewall events.
     */
    protected function transformRecentEvents(): array
    {
        return $this->events()
            ->where('service_type', 'firewall')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($event) => [
                'id' => $event->id,
                'milestone' => $event->milestone,
                'status' => $event->status,
                'current_step' => $event->current_step,
                'total_steps' => $event->total_steps,
                'progress' => $event->progressPercentage ?? null,
                'details' => $event->details,
                'created_at' => $event->created_at->toISOString(),
            ])->toArray();
    }

    /**
     * Get latest metrics for server header display.
     */
    protected function getLatestMetrics(): ?array
    {
        if ($this->monitoring_status !== MonitoringStatus::Active) {
            return null;
        }

        $metric = $this->metrics()->latest('collected_at')->first();

        return $metric ? $metric->only([
            'id',
            'cpu_usage',
            'memory_total_mb',
            'memory_used_mb',
            'memory_usage_percentage',
            'storage_total_gb',
            'storage_used_gb',
            'storage_usage_percentage',
            'collected_at',
        ]) : null;
    }
}
