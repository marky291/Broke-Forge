<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Controllers\Concerns\PreparesSiteData;
use App\Http\Requests\Servers\InstallSiteGitRepositoryRequest;
use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Services\Sites\Git\GitRepositoryInstallerJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ServerSiteGitRepositoryController extends Controller
{
    use PreparesSiteData;

    /**
     * Display the Git repository setup workflow.
     */
    public function show(Server $server, ServerSite $site): Response
    {
        // Add success flash message if installation just completed
        $flash = [];
        if ($site->git_status === TaskStatus::Success && $site->git_installed_at?->diffInSeconds(now()) < 10) {
            $flash['success'] = 'Git repository installed successfully! Your application is ready for deployments.';
        }

        return Inertia::render('servers/site-git-repository', [
            'server' => $this->prepareServerData($server),
            'site' => $this->prepareSiteData($site, ['git_installed_at']),
            'gitRepository' => $this->getGitRepositoryData($site),
            'flash' => $flash,
        ]);
    }

    /**
     * Install or update the Git repository for the site.
     */
    public function store(InstallSiteGitRepositoryRequest $request, Server $server, ServerSite $site): RedirectResponse
    {
        // Validate site can install Git
        if ($site->isGitProcessing()) {
            return $this->redirectWithError($server, $site, 'Git installation is already in progress.');
        }

        if (! $site->canInstallGitRepository()) {
            return $this->redirectWithError($server, $site, 'Git repository cannot be installed in the current state.');
        }

        // Prepare configuration
        $configuration = $this->prepareConfiguration($request->validated());

        // Store configuration and update status to installing
        $site->update([
            'git_status' => TaskStatus::Installing,
            'configuration' => array_merge(
                $site->configuration ?? [],
                [
                    'application_type' => 'git',
                    'git_repository' => $configuration,
                ]
            ),
        ]);

        // Dispatch installation job
        GitRepositoryInstallerJob::dispatch($server, $site, $configuration);

        $this->logInstallationStart($server, $site, $configuration);

        return redirect()
            ->route('servers.sites.application', [$server, $site])
            ->with('info', 'Repository installation started. This may take a few minutes.');
    }

    /**
     * Get Git repository configuration data.
     */
    protected function getGitRepositoryData(ServerSite $site): array
    {
        $config = $site->getGitConfiguration();

        return [
            'provider' => $config['provider'],
            'repository' => $config['repository'],
            'branch' => $config['branch'],
            'deployKey' => $config['deploy_key'] ?? $this->resolveDeployKey($site->server),
            'lastDeployedSha' => $site->last_deployment_sha,
            'lastDeployedAt' => $site->last_deployed_at?->toISOString(),
        ];
    }

    /**
     * Prepare configuration from validated request data.
     */
    protected function prepareConfiguration(array $validated): array
    {
        $configuration = [
            'provider' => $validated['provider'],
            'repository' => $validated['repository'],
            'branch' => $validated['branch'],
        ];

        if (! empty($validated['document_root'])) {
            $configuration['document_root'] = $validated['document_root'];
        }

        return $configuration;
    }

    /**
     * Redirect with an error message.
     */
    protected function redirectWithError(Server $server, ServerSite $site, string $message): RedirectResponse
    {
        return redirect()
            ->route('servers.sites.application', [$server, $site])
            ->withErrors(['repository' => $message]);
    }

    /**
     * Log the start of Git installation.
     */
    protected function logInstallationStart(Server $server, ServerSite $site, array $configuration): void
    {
        Log::info('Git repository installation job dispatched', [
            'server_id' => $server->id,
            'site_id' => $site->id,
            'repository' => $configuration['repository'],
            'branch' => $configuration['branch'],
        ]);
    }

    /**
     * Resolve the brokeforge deploy key for SSH access.
     * Returns the server-specific brokeforge SSH public key.
     *
     * This key is unique to this server and should be added as a deploy key
     * to the Git repository with read-only access.
     */
    protected function resolveDeployKey(Server $server): ?string
    {
        // Get or generate brokeforge credential for this specific server
        $brokeforgeCredential = $server->credentials()
            ->where('user', 'brokeforge')
            ->first() ?? \App\Models\ServerCredential::generateKeyPair($server, 'brokeforge');

        return $brokeforgeCredential->public_key;
    }
}
