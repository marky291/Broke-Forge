<?php

namespace App\Http\Controllers;

use App\Http\Resources\ServerSiteInstallationResource;
use App\Models\Server;
use App\Models\ServerSite;
use Inertia\Inertia;
use Inertia\Response;

class ServerSiteInstallationController extends Controller
{
    /**
     * Show the site installation progress page.
     */
    public function show(Server $server, ServerSite $site): Response|\Illuminate\Http\RedirectResponse
    {
        // Authorize user can view this server
        $this->authorize('view', $server);

        // Verify site belongs to server
        if ($site->server_id !== $server->id) {
            abort(404);
        }

        // Redirect to site page if installation is complete
        if ($site->status === 'active') {
            return redirect()->route('servers.show', $server)
                ->with('success', "Site {$site->domain} installed successfully!");
        }

        // Keep user on installation page if failed so they can see which step failed
        // The frontend will show the error state and failed step

        return Inertia::render('servers/sites/installing', [
            'server' => $server->only(['id', 'vanity_name', 'public_ip']),
            'site' => new ServerSiteInstallationResource($site),
        ]);
    }
}
