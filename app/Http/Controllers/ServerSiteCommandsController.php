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

        $commandHistory = $site->commandHistory()
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->through(fn ($history) => [
                'id' => $history->id,
                'command' => $history->command,
                'output' => $history->output,
                'errorOutput' => $history->error_output,
                'exitCode' => $history->exit_code,
                'ranAt' => $history->created_at->toIso8601String(),
                'durationMs' => $history->duration_ms,
                'success' => $history->success,
            ]);

        return Inertia::render('servers/site-commands', [
            'server' => $this->prepareServerData($server),
            'site' => $this->prepareSiteData($site, ['document_root']),
            'executionContext' => [
                'workingDirectory' => $workingDirectory,
                'user' => $server->credential('brokeforge')?->getUsername() ?: 'brokeforge',
                'timeout' => 120,
            ],
            'commandResult' => session('commandResult'),
            'commandHistory' => $commandHistory,
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
