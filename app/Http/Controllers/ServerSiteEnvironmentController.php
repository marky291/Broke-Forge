<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Services\Sites\SiteEnvironmentReader;
use App\Packages\Services\Sites\SiteEnvironmentWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServerSiteEnvironmentController extends Controller
{
    /**
     * Show the environment file edit page.
     */
    public function edit(Server $server, ServerSite $site): Response
    {
        // Authorize user can update this server
        $this->authorize('update', $server);

        // Check if site supports environment editing
        if (! $site->siteFramework?->supportsEnv()) {
            abort(404, 'This site does not support environment file editing');
        }

        // Read environment file content
        try {
            $reader = new SiteEnvironmentReader($site);
            $envContent = $reader->execute();
        } catch (\Throwable $e) {
            abort(500, 'Failed to read environment file: '.$e->getMessage());
        }

        return Inertia::render('servers/sites/environment-edit', [
            'server' => [
                'id' => $server->id,
                'vanity_name' => $server->vanity_name,
                'provider' => $server->provider?->value,
                'connection' => $server->connection?->value,
                'public_ip' => $server->public_ip,
                'private_ip' => $server->private_ip,
            ],
            'site' => [
                'id' => $site->id,
                'domain' => $site->domain,
                'status' => $site->status,
                'health' => $site->health,
                'git_status' => $site->git_status?->value,
                'git_provider' => $site->getGitConfiguration()['provider'],
                'git_repository' => $site->getGitConfiguration()['repository'],
                'git_branch' => $site->getGitConfiguration()['branch'],
                'last_deployed_at' => $site->last_deployed_at?->toISOString(),
                'site_framework' => [
                    'name' => $site->siteFramework->name,
                    'env' => $site->siteFramework->env,
                    'requirements' => $site->siteFramework->requirements,
                ],
            ],
            'envContent' => $envContent,
        ]);
    }

    /**
     * Update the environment file content.
     */
    public function update(Request $request, Server $server, ServerSite $site): RedirectResponse
    {
        // Authorize user can update this server
        $this->authorize('update', $server);

        // Check if site supports environment editing
        if (! $site->siteFramework?->supportsEnv()) {
            abort(404, 'This site does not support environment file editing');
        }

        // Validate request
        $validated = $request->validate([
            'content' => ['required', 'string'],
        ]);

        // Write environment file content
        try {
            $writer = new SiteEnvironmentWriter($site);
            $writer->execute($validated['content']);
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->withErrors(['content' => 'Failed to save environment file: '.$e->getMessage()]);
        }

        return redirect()
            ->back()
            ->with('success', 'Environment file saved successfully');
    }
}
