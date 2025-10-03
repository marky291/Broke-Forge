<?php

namespace App\Http\Controllers\Concerns;

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
            'public_ip',
            'private_ip',
            'connection',
        ];

        return $server->only(array_merge($baseFields, $additionalFields));
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

        return array_merge(
            $site->only(array_merge($baseFields, $additionalFields)),
            [
                'git_provider' => $gitConfig['provider'],
                'git_repository' => $gitConfig['repository'],
                'git_branch' => $gitConfig['branch'],
            ]
        );
    }
}