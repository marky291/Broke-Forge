<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PreparesSiteData;
use App\Http\Requests\Servers\StoreSiteRequest;
use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Services\Sites\SiteInstallerJob;
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

    public function show(Server $server, ServerSite $site): Response|RedirectResponse
    {
        // Redirect to application page if site is already initialized
        if ($site->status === 'active') {
            return redirect()->route('servers.sites.application', [$server, $site]);
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
        ]);
    }

    public function store(StoreSiteRequest $request, Server $server): RedirectResponse
    {
        $validated = $request->validated();

        try {
            // Create the site record immediately so it appears in the list
            $site = ServerSite::create([
                'server_id' => $server->id,
                'domain' => $validated['domain'],
                'php_version' => $validated['php_version'],
                'ssl_enabled' => $validated['ssl'],
                'status' => 'provisioning',
                'document_root' => "/home/brokeforge/{$validated['domain']}/public",
                'nginx_config_path' => "/etc/nginx/sites-available/{$validated['domain']}",
            ]);

            // Dispatch job to provision the site on the remote server
            SiteInstallerJob::dispatch(
                $server,
                $validated['domain'],
                $validated['php_version'],
                $validated['ssl']
            );

            return back()->with('success', 'Site provisioning started. The site will appear in the list shortly.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to start site provisioning: '.$e->getMessage());
        }
    }
}
