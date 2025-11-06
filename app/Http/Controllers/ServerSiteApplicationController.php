<?php

namespace App\Http\Controllers;

use App\Http\Resources\ServerSiteResource;
use App\Models\Server;
use App\Models\ServerSite;
use Inertia\Inertia;
use Inertia\Response;

class ServerSiteApplicationController extends Controller
{
    /**
     * Display the application management page.
     */
    public function show(Server $server, ServerSite $site): Response
    {
        // Authorize user can view this server
        $this->authorize('view', $server);

        return Inertia::render('servers/site-application', [
            'site' => new ServerSiteResource($site),
        ]);
    }
}
