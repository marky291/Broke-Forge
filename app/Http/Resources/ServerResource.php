<?php

namespace App\Http\Resources;

use App\Enums\MonitoringStatus;
use App\Packages\Services\PHP\Services\PhpConfigurationService;
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
            'monitoring_collection_interval' => $this->monitoring_collection_interval,
            'scheduler_status' => $this->scheduler_status?->value,
            'scheduler_installed_at' => $this->scheduler_installed_at?->toISOString(),
            'scheduler_uninstalled_at' => $this->scheduler_uninstalled_at?->toISOString(),
            'supervisor_status' => $this->supervisor_status?->value,
            'supervisor_installed_at' => $this->supervisor_installed_at?->toISOString(),
            'supervisor_uninstalled_at' => $this->supervisor_uninstalled_at?->toISOString(),
            'provision_status' => $this->provision_status?->value,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'firewall' => [
                'isInstalled' => $isFirewallInstalled,
                'status' => $this->getFirewallStatus($firewall),
                'is_enabled' => $firewall?->is_enabled ?? false,
                'rules' => $this->transformFirewallRules($firewall),
                'recentEvents' => [],
            ],
            'latestMetrics' => $this->getLatestMetrics(),
            'recentMetrics' => $this->transformRecentMetrics($request),
            'monitors' => $this->transformMonitors(),
            'scheduledTasks' => $this->transformScheduledTasks(),
            'recentTaskRuns' => $this->transformRecentTaskRuns($request),
            'supervisorTasks' => $this->transformSupervisorTasks(),
            'databases' => $this->transformDatabases(),
            'sites' => $this->transformSites(),
            'phps' => $this->transformPhps(),
            'availablePhpVersions' => $this->transformAvailablePhpVersions(),
            'phpExtensions' => PhpConfigurationService::getAvailableExtensions(),
            'defaultSettings' => PhpConfigurationService::getDefaultSettings(),
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

        return $firewall->rules()->latest('id')->get()->map(fn ($rule) => [
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

    /**
     * Transform recent metrics for monitoring page.
     */
    protected function transformRecentMetrics(Request $request): array
    {
        // Only load metrics if monitoring is active and timeframe is requested
        if ($this->monitoring_status !== MonitoringStatus::Active) {
            return [];
        }

        // Get timeframe from request (default 24 hours)
        $hours = $request->input('hours', 24);

        // Validate timeframe is reasonable
        if (! in_array($hours, [24, 72, 168])) {
            $hours = 24;
        }

        return $this->metrics()
            ->where('collected_at', '>=', now()->subHours($hours))
            ->orderBy('collected_at', 'desc')
            ->get()
            ->map(fn ($metric) => [
                'id' => $metric->id,
                'cpu_usage' => $metric->cpu_usage,
                'memory_total_mb' => $metric->memory_total_mb,
                'memory_used_mb' => $metric->memory_used_mb,
                'memory_usage_percentage' => $metric->memory_usage_percentage,
                'storage_total_gb' => $metric->storage_total_gb,
                'storage_used_gb' => $metric->storage_used_gb,
                'storage_usage_percentage' => $metric->storage_usage_percentage,
                'collected_at' => $metric->collected_at->toISOString(),
            ])
            ->toArray();
    }

    /**
     * Transform monitors collection.
     */
    protected function transformMonitors(): array
    {
        return $this->monitors()->orderBy('created_at', 'desc')->get()->map(fn ($monitor) => [
            'id' => $monitor->id,
            'name' => $monitor->name,
            'metric_type' => $monitor->metric_type,
            'operator' => $monitor->operator,
            'threshold' => $monitor->threshold,
            'duration_minutes' => $monitor->duration_minutes,
            'notification_emails' => $monitor->notification_emails,
            'enabled' => $monitor->enabled,
            'cooldown_minutes' => $monitor->cooldown_minutes,
            'last_triggered_at' => $monitor->last_triggered_at?->toISOString(),
            'last_recovered_at' => $monitor->last_recovered_at?->toISOString(),
            'status' => $monitor->status,
            'created_at' => $monitor->created_at->toISOString(),
            'updated_at' => $monitor->updated_at->toISOString(),
        ])->toArray();
    }

    /**
     * Transform sites collection.
     */
    protected function transformSites(): array
    {
        return $this->sites()->latest('id')->get()->map(fn ($site) => [
            'id' => $site->id,
            'domain' => $site->domain,
            'document_root' => $site->document_root,
            'php_version' => $site->php_version,
            'ssl_enabled' => $site->ssl_enabled,
            'status' => $site->status,
            'provisioned_at' => $site->provisioned_at?->toISOString(),
            'provisioned_at_human' => $site->provisioned_at?->diffForHumans(),
            'last_deployed_at' => $site->last_deployed_at?->toISOString(),
            'last_deployed_at_human' => $site->last_deployed_at?->diffForHumans(),
            'configuration' => $site->configuration,
            'git_status' => $site->git_status?->value,
            'error_log' => $site->error_log,
        ])->toArray();
    }

    /**
     * Transform PHP installations collection.
     */
    protected function transformPhps(): array
    {
        return $this->phps()->with('modules')->get()->map(fn ($php) => [
            'id' => $php->id,
            'version' => $php->version,
            'status' => $php->status?->value,
            'is_cli_default' => $php->is_cli_default,
            'is_site_default' => $php->is_site_default,
            'modules' => $php->modules->map(fn ($module) => [
                'id' => $module->id,
                'name' => $module->name,
                'is_enabled' => $module->is_enabled,
            ])->toArray(),
            'created_at' => $php->created_at->toISOString(),
            'updated_at' => $php->updated_at->toISOString(),
        ])->toArray();
    }

    /**
     * Transform available PHP versions into array of value/label objects.
     */
    protected function transformAvailablePhpVersions(): array
    {
        $versions = PhpConfigurationService::getAvailableVersions();

        return collect($versions)->map(fn ($label, $value) => [
            'value' => $value,
            'label' => $label,
        ])->values()->toArray();
    }

    /**
     * Transform scheduled tasks collection.
     */
    protected function transformScheduledTasks(): array
    {
        return $this->scheduledTasks()->orderBy('id')->get()->map(fn ($task) => [
            'id' => $task->id,
            'server_id' => $task->server_id,
            'name' => $task->name,
            'command' => $task->command,
            'frequency' => $task->frequency?->value,
            'cron_expression' => $task->cron_expression,
            'status' => $task->status?->value,
            'last_run_at' => $task->last_run_at?->toISOString(),
            'next_run_at' => $task->next_run_at?->toISOString(),
            'send_notifications' => $task->send_notifications,
            'timeout' => $task->timeout,
            'created_at' => $task->created_at->toISOString(),
            'updated_at' => $task->updated_at->toISOString(),
        ])->toArray();
    }

    /**
     * Transform recent task runs for scheduler page.
     */
    protected function transformRecentTaskRuns(Request $request): array
    {
        // Only load task runs if scheduler is active
        if ($this->scheduler_status?->value !== 'active') {
            return [];
        }

        // Get page parameter from request (default 1)
        $page = $request->input('page', 1);
        $perPage = 5;

        // Get recent task runs (last 7 days) with pagination
        $runs = $this->scheduledTaskRuns()
            ->with('task:id,server_id,name')
            ->where('started_at', '>=', now()->subDays(7))
            ->orderBy('started_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $runs->items() ? collect($runs->items())->map(fn ($run) => [
                'id' => $run->id,
                'server_id' => $run->server_id,
                'server_scheduled_task_id' => $run->server_scheduled_task_id,
                'started_at' => $run->started_at->toISOString(),
                'completed_at' => $run->completed_at?->toISOString(),
                'exit_code' => $run->exit_code,
                'output' => $run->output,
                'error_output' => $run->error_output,
                'duration_ms' => $run->duration_ms,
                'was_successful' => $run->was_successful,
                'created_at' => $run->created_at->toISOString(),
                'updated_at' => $run->updated_at->toISOString(),
                'task' => $run->task ? [
                    'id' => $run->task->id,
                    'server_id' => $run->task->server_id,
                    'name' => $run->task->name,
                ] : null,
            ])->toArray() : [],
            'links' => [
                'first' => $runs->url(1),
                'last' => $runs->url($runs->lastPage()),
                'prev' => $runs->previousPageUrl(),
                'next' => $runs->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $runs->currentPage(),
                'from' => $runs->firstItem(),
                'last_page' => $runs->lastPage(),
                'per_page' => $runs->perPage(),
                'to' => $runs->lastItem(),
                'total' => $runs->total(),
            ],
        ];
    }

    /**
     * Transform supervisor tasks collection.
     */
    protected function transformSupervisorTasks(): array
    {
        return $this->supervisorTasks()->orderBy('id')->get()->map(fn ($task) => [
            'id' => $task->id,
            'server_id' => $task->server_id,
            'name' => $task->name,
            'command' => $task->command,
            'working_directory' => $task->working_directory,
            'processes' => $task->processes,
            'user' => $task->user,
            'auto_restart' => $task->auto_restart,
            'autorestart_unexpected' => $task->autorestart_unexpected,
            'status' => $task->status,
            'stdout_logfile' => $task->stdout_logfile,
            'stderr_logfile' => $task->stderr_logfile,
            'installed_at' => $task->installed_at?->toISOString(),
            'uninstalled_at' => $task->uninstalled_at?->toISOString(),
            'created_at' => $task->created_at->toISOString(),
            'updated_at' => $task->updated_at->toISOString(),
        ])->toArray();
    }

    /**
     * Transform databases collection.
     */
    protected function transformDatabases(): array
    {
        return $this->databases()->latest()->get()->map(fn ($db) => [
            'id' => $db->id,
            'name' => $db->name,
            'type' => $db->type?->value ?? $db->type,
            'version' => $db->version,
            'port' => $db->port,
            'status' => $db->status?->value ?? $db->status,
            'created_at' => $db->created_at?->toISOString(),
        ])->toArray();
    }
}
