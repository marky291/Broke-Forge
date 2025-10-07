<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PreparesSiteData;
use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Enums\GitStatus;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ServerSiteApplicationController extends Controller
{
    use PreparesSiteData;

    /**
     * Display the application management page.
     *
     * Shows application dashboard if installed,
     * redirects to Git setup page if Git installation failed.
     */
    public function show(Server $server, ServerSite $site): Response|RedirectResponse
    {
        $applicationType = $site->configuration['application_type'] ?? null;

        // If Git failed, redirect to retry page
        if ($site->git_status === GitStatus::Failed) {
            return redirect()->route('servers.sites.application.git.setup', [$server, $site]);
        }

        return Inertia::render('servers/site-application', [
            'server' => $this->prepareServerData($server),
            'site' => $this->prepareSiteData($site, [
                'document_root',
                'php_version',
                'ssl_enabled',
                'configuration',
                'provisioned_at',
            ]),
            'applicationType' => $applicationType,
            'gitRepository' => $applicationType === 'application' ? $this->getGitRepositoryData($site) : null,
        ]);
    }

    /**
     * Get Git repository configuration data.
     */
    protected function getGitRepositoryData(ServerSite $site): array
    {
        $config = $site->getGitConfiguration();

        return [
            'provider' => $config['provider'],
            'repository' => $config['repository'],
            'branch' => $config['branch'],
            'deployKey' => $config['deploy_key'],
            'lastDeployedSha' => $site->last_deployment_sha,
            'lastDeployedAt' => $site->last_deployed_at?->toISOString(),
        ];
    }
}
