<?php

namespace App\Http\Controllers;

use App\Http\Requests\Servers\StoreSiteRequest;
use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Services\Sites\SiteInstallerJob;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ServerSitesController extends Controller
{
    public function index(Server $server): Response
    {
        $sites = $server->sites()
            ->select(['id', 'domain', 'document_root', 'php_version', 'ssl_enabled', 'status', 'configuration', 'git_status', 'provisioned_at'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('servers/sites', [
            'server' => $server->only([
                'id',
                'vanity_name',
                'public_ip',
                'private_ip',
                'ssh_port',
                'connection',
                'provision_status',
                'created_at',
                'updated_at',
            ]),
            'sites' => $sites,
        ]);
    }

    public function show(Server $server, ServerSite $site): Response
    {
        return Inertia::render('servers/site-application', [
            'server' => $server->only([
                'id',
                'vanity_name',
                'public_ip',
                'private_ip',
                'ssh_port',
                'connection',
                'provision_status',
                'created_at',
                'updated_at',
            ]),
            'site' => $site->only([
                'id',
                'domain',
                'document_root',
                'php_version',
                'ssl_enabled',
                'status',
                'configuration',
                'provisioned_at',
                'created_at',
                'updated_at',
            ]),
        ]);
    }

    public function store(StoreSiteRequest $request, Server $server): RedirectResponse
    {
        $validated = $request->validated();

        SiteInstallerJob::dispatch(
            $server,
            $validated['domain'],
            $validated['php_version'],
            $validated['ssl']
        );

        return back()->with('success', 'Site provisioning started.');
    }
}
