<?php

namespace App\Packages\Services\Sites\Git;

use App\Models\ServerSite;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageInstaller;
use App\Packages\Enums\CredentialType;
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

        // Configure Git SSH command to use brokeforge's private key
        $sshKeyPath = '/home/brokeforge/.ssh/id_rsa';
        $gitSshCommand = sprintf(
            'GIT_SSH_COMMAND="ssh -i %s -o StrictHostKeyChecking=accept-new -o IdentitiesOnly=yes -o UserKnownHostsFile=/home/brokeforge/.ssh/known_hosts"',
            $sshKeyPath
        );

        return [
            $this->track(GitRepositoryInstallerMilestones::ENSURE_REPOSITORY_DIRECTORY),
            sprintf('rm -rf %1$s && mkdir -p %1$s && chmod -R 775 %1$s', escapeshellarg($documentRoot)),

            $this->track(GitRepositoryInstallerMilestones::CLONE_OR_FETCH_REPOSITORY),
            sprintf(
                'git config --global --add safe.directory %1$s; REPO_DIR=%1$s; if [ -d "$REPO_DIR/.git" ]; then cd "$REPO_DIR" && %2$s git fetch --all --prune; else %3$s git clone %4$s "$REPO_DIR"; fi',
                escapeshellarg($documentRoot),
                $gitSshCommand,
                $gitSshCommand,
                escapeshellarg($repositorySshUrl)
            ),

            $this->track(GitRepositoryInstallerMilestones::CHECKOUT_TARGET_BRANCH),
            sprintf(
                'REPO_DIR=%1$s; cd "$REPO_DIR" && BRANCH=%2$s; if git show-ref --verify --quiet refs/heads/"$BRANCH" || git show-ref --verify --quiet refs/remotes/origin/"$BRANCH"; then git checkout "$BRANCH"; else DETECTED_BRANCH=$(git symbolic-ref refs/remotes/origin/HEAD 2>/dev/null | sed \'s@^refs/remotes/origin/@@\'); if [ -n "$DETECTED_BRANCH" ]; then git checkout "$DETECTED_BRANCH"; elif git show-ref --verify --quiet refs/remotes/origin/master; then git checkout master; elif git show-ref --verify --quiet refs/remotes/origin/main; then git checkout main; else echo "Error: Could not determine branch to checkout" && exit 1; fi; fi',
                escapeshellarg($documentRoot),
                escapeshellarg($branch)
            ),

            $this->track(GitRepositoryInstallerMilestones::SYNC_WORKTREE),
            sprintf(
                'REPO_DIR=%1$s; cd "$REPO_DIR" && CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD) && git reset --hard origin/"$CURRENT_BRANCH" && %2$s git pull origin "$CURRENT_BRANCH"',
                escapeshellarg($documentRoot),
                $gitSshCommand
            ),

            $this->track(GitRepositoryInstallerMilestones::COMPLETE),
            function () use ($site, $repositoryConfiguration) {
                // Persist repository configuration with server-specific deploy key
                if (! $site instanceof ServerSite || ! $site->exists) {
                    return;
                }

                $configuration = $site->configuration ?? [];

                // Get server-specific brokeforge credential from database
                $brokeforgeCredential = $this->server->credential('brokeforge');
                $deployKey = $brokeforgeCredential?->public_key;

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

    public function credentialType(): CredentialType
    {
        return CredentialType::BrokeForge;
    }
}
