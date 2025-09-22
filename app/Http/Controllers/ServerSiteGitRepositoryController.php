<?php

namespace App\Http\Controllers;

use App\Http\Requests\Servers\InstallSiteGitRepositoryRequest;
use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Credentials\WorkerCredential;
use App\Packages\Enums\GitStatus;
use App\Packages\Services\Sites\GitRepositoryInstallerJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ServerSiteGitRepositoryController extends Controller
{
    /**
     * Display the Git repository setup workflow.
     */
    public function show(Server $server, ServerSite $site): Response
    {
        // Add success flash message if installation just completed
        $flash = [];
        if ($site->git_status === GitStatus::Installed && $site->git_installed_at?->diffInSeconds(now()) < 10) {
            $flash['success'] = 'Git repository installed successfully! Your application is ready for deployments.';
        }

        return Inertia::render('servers/site-git-repository', [
            'server' => $this->getServerData($server),
            'site' => $this->getSiteData($site),
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
            'git_status' => GitStatus::Installing,
            'configuration' => array_merge(
                $site->configuration ?? [],
                ['git_repository' => $configuration]
            ),
        ]);

        // Dispatch installation job
        GitRepositoryInstallerJob::dispatch($server, $site, $configuration);

        $this->logInstallationStart($server, $site, $configuration);

        return redirect()
            ->route('servers.sites.git-repository', [$server, $site])
            ->with('info', 'Repository installation started. This may take a few minutes.');
    }

    /**
     * Get server data for the view.
     */
    protected function getServerData(Server $server): array
    {
        return $server->only(['id', 'vanity_name', 'connection']);
    }

    /**
     * Get site data for the view.
     */
    protected function getSiteData(ServerSite $site): array
    {
        return array_merge(
            $site->only(['id', 'domain', 'status']),
            [
                'git_status' => $site->git_status?->value,
                'git_installed_at' => $site->git_installed_at?->toISOString(),
            ]
        );
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
            'deployKey' => $config['deploy_key'] ?? $this->resolveDeployKey(),
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
            ->route('servers.sites.git-repository', [$server, $site])
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
     * Resolve the worker deploy key for SSH access.
     */
    protected function resolveDeployKey(): ?string
    {
        $credential = new WorkerCredential;
        $publicKeyPath = $credential->publicKey();

        if (! is_string($publicKeyPath) || ! is_file($publicKeyPath) || ! is_readable($publicKeyPath)) {
            return null;
        }

        $content = file_get_contents($publicKeyPath);

        return $content === false ? null : trim($content);
    }
}
