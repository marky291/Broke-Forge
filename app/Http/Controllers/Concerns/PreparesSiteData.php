<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerSite;

trait PreparesSiteData
{
    /**
     * Prepare minimal server data for site layout header.
     */
    protected function prepareServerData(Server $server, array $additionalFields = []): array
    {
        $baseFields = [
            'id',
            'vanity_name',
            'provider',
            'public_ip',
            'private_ip',
            'connection',
            'monitoring_status',
        ];

        return $server->only(array_merge($baseFields, $additionalFields));
    }

    /**
     * Get latest metrics for server header display.
     */
    protected function getLatestMetrics(Server $server): ?array
    {
        if ($server->monitoring_status !== TaskStatus::Active) {
            return null;
        }

        $metric = $server->metrics()->latest('collected_at')->first();

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
     * Prepare site data for site layout header with Git configuration.
     */
    protected function prepareSiteData(ServerSite $site, array $additionalFields = []): array
    {
        $gitConfig = $site->getGitConfiguration();

        $baseFields = [
            'id',
            'domain',
            'status',
            'health',
            'git_status',
            'last_deployed_at',
        ];

        $data = $site->only(array_merge($baseFields, $additionalFields));

        // Ensure git_status is serialized as string value, not enum object
        if (isset($data['git_status'])) {
            $data['git_status'] = $data['git_status']->value;
        }

        return array_merge(
            $data,
            [
                'git_provider' => $gitConfig['provider'],
                'git_repository' => $gitConfig['repository'],
                'git_branch' => $gitConfig['branch'],
                'site_framework' => [
                    'name' => $site->siteFramework->name,
                    'env' => $site->siteFramework->env,
                    'requirements' => $site->siteFramework->requirements,
                ],
            ]
        );
    }
}
