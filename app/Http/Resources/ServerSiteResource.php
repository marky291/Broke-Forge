<?php

namespace App\Http\Resources;

use App\Enums\TaskStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServerSiteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $gitConfig = $this->getGitConfiguration();

        return [
            'id' => $this->id,
            'domain' => $this->domain,
            'document_root' => $this->document_root,
            'php_version' => $this->php_version,
            'ssl_enabled' => $this->ssl_enabled,
            'status' => $this->status,
            'health' => $this->health,
            'git_status' => $this->git_status?->value,
            'git_provider' => $gitConfig['provider'],
            'git_repository' => $gitConfig['repository'],
            'git_branch' => $gitConfig['branch'],
            'configuration' => $this->configuration,
            'installed_at' => $this->installed_at?->toISOString(),
            'installed_at_human' => $this->installed_at_human,
            'last_deployed_at' => $this->last_deployed_at?->toISOString(),
            'last_deployed_at_human' => $this->last_deployed_at_human,
            'last_deployment_sha' => $this->last_deployment_sha,
            'active_deployment_id' => $this->active_deployment_id,
            'auto_deploy_enabled' => $this->auto_deploy_enabled,
            'error_log' => $this->error_log,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'server' => $this->transformServer(),
            'executionContext' => $this->transformExecutionContext(),
            'commandHistory' => $this->transformCommandHistory($request),
            'applicationType' => $this->transformApplicationType(),
            'gitRepository' => $this->transformGitRepository(),
            'deploymentScript' => $this->transformDeploymentScript(),
            'gitConfig' => $this->transformGitConfig(),
            'deployments' => $this->transformDeployments($request),
            'latestDeployment' => $this->transformLatestDeployment(),
            'site_framework' => $this->transformSiteFramework(),
        ];
    }

    /**
     * Transform site framework data for navigation.
     */
    protected function transformSiteFramework(): array
    {
        return [
            'env' => $this->siteFramework->env,
        ];
    }

    /**
     * Transform minimal server data for site layout header.
     */
    protected function transformServer(): array
    {
        $server = $this->server;

        $data = [
            'id' => $server->id,
            'vanity_name' => $server->vanity_name,
            'provider' => $server->provider?->value,
            'public_ip' => $server->public_ip,
            'private_ip' => $server->private_ip,
            'connection' => $server->connection?->value,
            'monitoring_status' => $server->monitoring_status?->value,
        ];

        // Get latest metrics for server header display if monitoring is active
        if ($server->monitoring_status === TaskStatus::Active) {
            $metric = $server->metrics()->latest('collected_at')->first();

            if ($metric) {
                $data['latestMetrics'] = $metric->only([
                    'id',
                    'cpu_usage',
                    'memory_total_mb',
                    'memory_used_mb',
                    'memory_usage_percentage',
                    'storage_total_gb',
                    'storage_used_gb',
                    'storage_usage_percentage',
                    'collected_at',
                ]);
            }
        }

        return $data;
    }

    /**
     * Transform execution context for site commands.
     */
    protected function transformExecutionContext(): array
    {
        $server = $this->server;
        $siteIdentifier = $this->domain ?: (string) $this->id;
        $workingDirectory = $this->document_root ?: sprintf('/home/brokeforge/%s', $siteIdentifier);

        $brokeforgeCredential = $server->credentials()
            ->where('user', 'brokeforge')
            ->first();

        return [
            'workingDirectory' => $workingDirectory,
            'user' => $brokeforgeCredential?->getUsername() ?: 'brokeforge',
            'timeout' => 120,
        ];
    }

    /**
     * Transform command history for site commands page.
     */
    protected function transformCommandHistory(Request $request): array
    {
        $page = $request->input('page', 1);
        $perPage = 10;

        $history = $this->commandHistory()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $history->items() ? collect($history->items())->map(fn ($item) => [
                'id' => $item->id,
                'command' => $item->command,
                'output' => $item->output,
                'errorOutput' => $item->error_output,
                'exitCode' => $item->exit_code,
                'ranAt' => $item->created_at->toIso8601String(),
                'durationMs' => $item->duration_ms,
                'success' => $item->success,
            ])->toArray() : [],
            'current_page' => $history->currentPage(),
            'last_page' => $history->lastPage(),
            'per_page' => $history->perPage(),
            'total' => $history->total(),
        ];
    }

    /**
     * Transform application type for site application page.
     */
    protected function transformApplicationType(): ?string
    {
        return $this->configuration['application_type'] ?? null;
    }

    /**
     * Transform Git repository data for site application page.
     */
    protected function transformGitRepository(): ?array
    {
        $applicationType = $this->configuration['application_type'] ?? null;

        if ($applicationType !== 'application') {
            return null;
        }

        $config = $this->getGitConfiguration();

        return [
            'provider' => $config['provider'],
            'repository' => $config['repository'],
            'branch' => $config['branch'],
            'deployKey' => $config['deploy_key'],
            'lastDeployedSha' => $this->last_deployment_sha,
            'lastDeployedAt' => $this->last_deployed_at?->toISOString(),
        ];
    }

    /**
     * Transform deployment script for site deployments page.
     */
    protected function transformDeploymentScript(): ?string
    {
        return $this->getDeploymentScript();
    }

    /**
     * Transform Git configuration for site deployments page.
     */
    protected function transformGitConfig(): array
    {
        return $this->getGitConfiguration();
    }

    /**
     * Transform deployment history for site deployments page.
     */
    protected function transformDeployments(Request $request): array
    {
        $page = $request->input('page', 1);
        $perPage = 10;

        $deployments = $this->deployments()
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $deployments->items() ? collect($deployments->items())->map(fn ($deployment) => [
                'id' => $deployment->id,
                'status' => $deployment->status,
                'deployment_script' => $deployment->deployment_script,
                'deployment_path' => $deployment->deployment_path,
                'log_file_path' => $deployment->log_file_path,
                'exit_code' => $deployment->exit_code,
                'commit_sha' => $deployment->commit_sha,
                'branch' => $deployment->branch,
                'duration_ms' => $deployment->duration_ms,
                'duration_seconds' => $deployment->getDurationSeconds(),
                'started_at' => $deployment->started_at,
                'completed_at' => $deployment->completed_at,
                'created_at' => $deployment->created_at,
                'created_at_human' => $deployment->created_at->diffForHumans(),
                'can_rollback' => $deployment->canRollback(),
                'is_active' => $deployment->id === $this->active_deployment_id,
            ])->toArray() : [],
            'current_page' => $deployments->currentPage(),
            'last_page' => $deployments->lastPage(),
            'per_page' => $deployments->perPage(),
            'total' => $deployments->total(),
            'links' => [
                'first' => $deployments->url(1),
                'last' => $deployments->url($deployments->lastPage()),
                'prev' => $deployments->previousPageUrl(),
                'next' => $deployments->nextPageUrl(),
            ],
        ];
    }

    /**
     * Transform latest deployment for site deployments page.
     */
    protected function transformLatestDeployment(): ?array
    {
        $latestDeployment = $this->latestDeployment;

        if (! $latestDeployment) {
            return null;
        }

        return [
            'id' => $latestDeployment->id,
            'status' => $latestDeployment->status,
            'log_file_path' => $latestDeployment->log_file_path,
            'commit_sha' => $latestDeployment->commit_sha,
            'branch' => $latestDeployment->branch,
            'duration_seconds' => $latestDeployment->getDurationSeconds(),
            'started_at' => $latestDeployment->started_at,
            'completed_at' => $latestDeployment->completed_at,
        ];
    }
}
