<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PreparesSiteData;
use App\Http\Requests\Servers\ExecuteSiteCommandRequest;
use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Services\Sites\Command\SiteCommandInstaller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ServerSiteCommandsController extends Controller
{
    use PreparesSiteData;

    public function __invoke(Server $server, ServerSite $site): Response
    {
        $siteIdentifier = $site->domain ?: (string) $site->id;
        $workingDirectory = $site->document_root ?: sprintf('/home/brokeforge/%s', $siteIdentifier);

        return Inertia::render('servers/site-commands', [
            'server' => $this->prepareServerData($server),
            'site' => $this->prepareSiteData($site, ['document_root']),
            'executionContext' => [
                'workingDirectory' => $workingDirectory,
                'user' => $server->credential('brokeforge')?->getUsername() ?: 'brokeforge',
                'timeout' => 120,
            ],
            'commandResult' => session('commandResult'),
        ]);
    }

    public function store(ExecuteSiteCommandRequest $request, Server $server, ServerSite $site): RedirectResponse
    {
        $validated = $request->validated();
        $executor = new SiteCommandInstaller($server, $site);

        try {
            $result = $executor->execute($validated['command']);
        } catch (\Throwable $exception) {
            Log::error('Site command execution failed.', [
                'server_id' => $server->id,
                'site_id' => $site->id,
                'command' => $validated['command'],
                'exception' => $exception->getMessage(),
            ]);

            $result = [
                'command' => $validated['command'],
                'output' => '',
                'errorOutput' => $exception->getMessage(),
                'exitCode' => null,
                'ranAt' => now()->toIso8601String(),
                'durationMs' => 0,
                'success' => false,
            ];
        }

        return redirect()
            ->route('servers.sites.commands', [$server, $site])
            ->with('commandResult', $result);
    }
}
