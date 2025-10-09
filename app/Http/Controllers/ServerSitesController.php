<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PreparesSiteData;
use App\Http\Requests\Servers\StoreSiteRequest;
use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Enums\CredentialType;
use App\Packages\Enums\GitStatus;
use App\Packages\Services\Sites\Git\GitRepositoryInstallerJob;
use App\Packages\Services\Sites\SiteInstallerJob;
use App\Packages\Services\Sites\SiteRemoverJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ServerSitesController extends Controller
{
    use PreparesSiteData;

    public function index(Server $server): Response
    {
        $sites = $server->sites()
            ->latest()
            ->paginate(10);

        return Inertia::render('servers/sites', [
            'server' => $server->only([
                'id',
                'vanity_name',
                'provider',
                'public_ip',
                'private_ip',
                'ssh_port',
                'connection',
                'provision_status',
                'monitoring_status',
                'created_at',
                'updated_at',
            ]),
            'sites' => $sites,
            'latestMetrics' => $this->getLatestMetrics($server),
        ]);
    }

    public function show(Server $server, ServerSite $site): RedirectResponse
    {
        // Always redirect to the application page
        return redirect()->route('servers.sites.application', [$server, $site]);
    }

    /**
     * Get the deploy key for the server.
     */
    public function deployKey(Server $server): JsonResponse
    {
        $credential = $server->credential(CredentialType::BrokeForge);

        return response()->json([
            'deploy_key' => $credential?->public_key ?? 'Deploy key not available',
        ]);
    }

    public function store(StoreSiteRequest $request, Server $server): RedirectResponse
    {
        $validated = $request->validated();

        try {
            // Build configuration with Git repository (all sites are "Application" type)
            $configuration = [
                'application_type' => 'application',
                'git_repository' => [
                    'provider' => 'github',
                    'repository' => $validated['git_repository'],
                    'branch' => $validated['git_branch'],
                ],
            ];

            // Create site with Git status as "installing"
            $site = ServerSite::create([
                'server_id' => $server->id,
                'domain' => $validated['domain'],
                'php_version' => $validated['php_version'],
                'ssl_enabled' => $validated['ssl'],
                'status' => 'provisioning',
                'document_root' => "/home/brokeforge/{$validated['domain']}/public",
                'nginx_config_path' => "/etc/nginx/sites-available/{$validated['domain']}",
                'configuration' => $configuration,
                'git_status' => GitStatus::Installing,
            ]);

            // Dispatch jobs: nginx/directories + Git clone
            SiteInstallerJob::dispatch($server, $validated['domain'], $validated['php_version'], $validated['ssl']);
            GitRepositoryInstallerJob::dispatch($server, $site, $configuration['git_repository']);

            return back()->with('success', 'Site provisioning started. Repository will be cloned automatically.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to start site provisioning: '.$e->getMessage());
        }
    }

    /**
     * Uninstall a site from the server
     */
    public function uninstall(Server $server, ServerSite $site): RedirectResponse
    {
        // Set status to uninstalling
        $site->update(['status' => 'uninstalling']);

        // Dispatch site removal job
        SiteRemoverJob::dispatch($server, $site);

        return redirect()
            ->route('servers.sites', $server)
            ->with('success', 'Site uninstallation started');
    }
}
