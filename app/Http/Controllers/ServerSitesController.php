<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PreparesSiteData;
use App\Http\Requests\Servers\StoreSiteRequest;
use App\Http\Resources\ServerResource;
use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Enums\CredentialType;
use App\Packages\Enums\GitStatus;
use App\Packages\Services\Sites\ProvisionedSiteInstallerJob;
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
        return Inertia::render('servers/sites', [
            'server' => new ServerResource($server),
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

            // Dispatch site installation job with site ID
            ProvisionedSiteInstallerJob::dispatch($server, $site->id);

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

    /**
     * Delete a site from the server (typically for failed installations)
     */
    public function destroy(Server $server, ServerSite $site): RedirectResponse
    {
        try {
            // Set status to removing to indicate cleanup is in progress
            $site->update(['status' => 'removing']);

            // Dispatch site removal job to clean up any partial installation
            SiteRemoverJob::dispatch($server, $site);

            return redirect()
                ->route('servers.sites', $server)
                ->with('success', 'Site deletion started. Any partial installation will be cleaned up.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to delete site: '.$e->getMessage());
        }
    }
}
