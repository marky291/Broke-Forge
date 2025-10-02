<?php

namespace App\Packages\Services\Sites\Git;

use App\Models\ServerSite;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageInstaller;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use LogicException;

/**
 * Git Repository Installation Class
 *
 * Handle cloning or updating a site's Git repository over SSH
 */
class GitRepositoryInstaller extends PackageInstaller implements \App\Packages\Base\SitePackage
{
    /**
     * Execute the Git repository installation
     */
    public function execute(ServerSite $site, array $config): void
    {
        if (! isset($config['repository']) || ! is_string($config['repository'])) {
            throw new InvalidArgumentException('A repository identifier is required.');
        }

        $repositoryInput = trim($config['repository']);

        // Normalize repository inline (avoid helper methods)
        $repository = trim($repositoryInput);
        if ($repository === '') {
            throw new InvalidArgumentException('Repository cannot be empty.');
        }
        if (str_starts_with($repository, 'git@') || str_starts_with($repository, 'ssh://') || str_starts_with($repository, 'https://')) {
            $repositorySshUrl = $repository;
        } elseif (preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $repository)) {
            $repositorySshUrl = sprintf('git@github.com:%s.git', $repository);
        } else {
            throw new InvalidArgumentException('Repository must be an SSH URL or follow the owner/name format.');
        }

        // Normalize branch inline (avoid helper methods)
        $branch = isset($config['branch']) ? trim($config['branch']) : 'main';
        if ($branch === '') {
            $branch = 'main';
        }
        if (! preg_match('/^[A-Za-z0-9._\/-]{1,255}$/', $branch)) {
            throw new InvalidArgumentException('Branch may only contain letters, numbers, periods, hyphens, underscores, or slashes.');
        }

        $documentRoot = null;
        if (isset($config['document_root'])) {
            if (! is_string($config['document_root']) || trim($config['document_root']) === '') {
                throw new InvalidArgumentException('Document root must be a non-empty string when provided.');
            }
            $documentRoot = trim($config['document_root']);
        }

        // Resolve document root inline (avoid helper methods)
        if ($documentRoot) {
            $resolvedDocumentRoot = $documentRoot;
        } elseif ($site && is_string($site->document_root) && $site->document_root !== '') {
            $resolvedDocumentRoot = $site->document_root;
        } elseif ($site && is_string($site->domain) && $site->domain !== '') {
            $resolvedDocumentRoot = "/var/www/{$site->domain}/public";
        } else {
            throw new LogicException('Unable to determine repository document root.');
        }

        $provider = isset($config['provider']) && is_string($config['provider'])
            ? trim($config['provider'])
            : 'github';

        $repositoryConfiguration = [
            'provider' => $provider,
            'repository' => $repositoryInput,
            'branch' => $branch,
        ];

        $this->install($this->commands($resolvedDocumentRoot, $repositorySshUrl, $branch, $site, $repositoryConfiguration));
    }

    /**
     * Compile the command list executed on the remote host.
     */
    protected function commands(string $documentRoot, string $repositorySshUrl, string $branch, ServerSite $site, array $repositoryConfiguration): array
    {
        $documentRoot = rtrim($documentRoot, '/');

        // Configure Git SSH command to use worker's private key
        $sshKeyPath = '/home/worker/.ssh/id_rsa';
        $gitSshCommand = sprintf(
            'GIT_SSH_COMMAND="ssh -i %s -o StrictHostKeyChecking=accept-new -o IdentitiesOnly=yes -o UserKnownHostsFile=/home/worker/.ssh/known_hosts"',
            $sshKeyPath
        );

        return [
            $this->track(GitRepositoryInstallerMilestones::ENSURE_REPOSITORY_DIRECTORY),
            sprintf('sudo -u worker mkdir -p %s', escapeshellarg($documentRoot)),

            $this->track(GitRepositoryInstallerMilestones::CLONE_OR_FETCH_REPOSITORY),
            sprintf(
                'REPO_DIR=%1$s; if [ -d "$REPO_DIR/.git" ]; then cd "$REPO_DIR" && sudo -u worker %2$s git fetch --all --prune; else sudo -u worker %3$s git clone %4$s "$REPO_DIR"; fi',
                escapeshellarg($documentRoot),
                $gitSshCommand,
                $gitSshCommand,
                escapeshellarg($repositorySshUrl)
            ),

            $this->track(GitRepositoryInstallerMilestones::CHECKOUT_TARGET_BRANCH),
            sprintf(
                'REPO_DIR=%1$s; cd "$REPO_DIR" && sudo -u worker git checkout %2$s',
                escapeshellarg($documentRoot),
                escapeshellarg($branch)
            ),

            $this->track(GitRepositoryInstallerMilestones::SYNC_WORKTREE),
            sprintf(
                'REPO_DIR=%1$s; cd "$REPO_DIR" && sudo -u worker git reset --hard origin/%2$s && sudo -u worker %3$s git pull origin %4$s',
                escapeshellarg($documentRoot),
                $branch,
                $gitSshCommand,
                escapeshellarg($branch)
            ),

            $this->track(GitRepositoryInstallerMilestones::COMPLETE),
            function () use ($site, $repositoryConfiguration) {
                // Persist repository configuration with server-specific deploy key
                if (! $site instanceof ServerSite || ! $site->exists) {
                    return;
                }

                $configuration = $site->configuration ?? [];

                // Get server-specific worker credential from database
                $workerCredential = $this->server->credential('worker');
                $deployKey = $workerCredential?->public_key;

                $configuration['git_repository'] = array_filter([
                    'provider' => Arr::get($repositoryConfiguration, 'provider'),
                    'repository' => Arr::get($repositoryConfiguration, 'repository'),
                    'branch' => Arr::get($repositoryConfiguration, 'branch'),
                    'deploy_key' => $deployKey,
                ], fn ($value) => $value !== null && $value !== '');

                $site->update(['configuration' => $configuration]);
            },
        ];
    }

    public function packageName(): PackageName
    {
        return PackageName::Git;
    }

    public function packageType(): PackageType
    {
        return PackageType::Git;
    }

    public function milestones(): Milestones
    {
        return new GitRepositoryInstallerMilestones;
    }
}
