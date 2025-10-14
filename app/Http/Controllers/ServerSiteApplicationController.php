<?php

namespace App\Http\Controllers;

use App\Http\Resources\ServerSiteResource;
use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Enums\GitStatus;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ServerSiteApplicationController extends Controller
{
    /**
     * Display the application management page.
     *
     * Shows application dashboard if installed,
     * redirects to Git setup page if Git installation failed.
     */
    public function show(Server $server, ServerSite $site): Response|RedirectResponse
    {
        // If Git failed, redirect to retry page
        if ($site->git_status === GitStatus::Failed) {
            return redirect()->route('servers.sites.application.git.setup', [$server, $site]);
        }

        return Inertia::render('servers/site-application', [
            'site' => new ServerSiteResource($site),
        ]);
    }
}
