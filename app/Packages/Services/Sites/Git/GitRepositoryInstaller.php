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

        // Determine if site has dedicated deploy key
        $hasDedicatedKey = $site->has_dedicated_deploy_key ?? false;

        // Generate SSH config if site has dedicated deploy key
        $sshConfigCommands = [];
        $transformedRepositoryUrl = $repositorySshUrl;

        if ($hasDedicatedKey) {
            $sshConfigCommands = $this->generateSshConfigCommands($site);
            $transformedRepositoryUrl = $this->transformGitUrlForSshConfig($repositorySshUrl, $site->id);
        }

        // Configure Git SSH command to use brokeforge's private key
        $sshKeyPath = '/home/brokeforge/.ssh/id_rsa';
        $gitSshCommand = sprintf(
            'GIT_SSH_COMMAND="ssh -i %s -o StrictHostKeyChecking=accept-new -o IdentitiesOnly=yes -o UserKnownHostsFile=/home/brokeforge/.ssh/known_hosts"',
            $sshKeyPath
        );

        return [
            // Generate SSH config for dedicated deploy keys (if applicable)
            ...$sshConfigCommands,

            $this->track(GitRepositoryInstallerMilestones::ENSURE_REPOSITORY_DIRECTORY),
            sprintf('rm -rf %1$s && mkdir -p %1$s && chmod -R 775 %1$s', escapeshellarg($documentRoot)),

            $this->track(GitRepositoryInstallerMilestones::CLONE_OR_FETCH_REPOSITORY),
            sprintf(
                'git config --global --add safe.directory %1$s; REPO_DIR=%1$s; if [ -d "$REPO_DIR/.git" ]; then cd "$REPO_DIR" && %2$s git fetch --all --prune; else %3$s git clone %4$s "$REPO_DIR"; fi',
                escapeshellarg($documentRoot),
                $gitSshCommand,
                $gitSshCommand,
                escapeshellarg($transformedRepositoryUrl)
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

                $configuration['application_type'] = 'application';
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

    /**
     * Generate SSH config commands for site-specific deploy keys
     *
     * Creates an SSH config entry that uses a host alias to route Git traffic
     * through the site-specific SSH key instead of the server-wide key.
     *
     * @param  ServerSite  $site  The site with a dedicated deploy key
     * @return array Array of SSH commands to configure host alias
     */
    protected function generateSshConfigCommands(ServerSite $site): array
    {
        $siteId = $site->id;
        $keyPath = "/home/brokeforge/.ssh/site_{$siteId}_rsa";

        // SSH config entry using host alias pattern
        // This allows Git to use site-specific keys via URL transformation
        $sshConfigEntry = <<<EOF
Host github.com-site-{$siteId}
  HostName github.com
  IdentityFile {$keyPath}
  IdentitiesOnly yes
  StrictHostKeyChecking accept-new
  UserKnownHostsFile /home/brokeforge/.ssh/known_hosts
EOF;

        return [
            // Ensure SSH config file exists
            'mkdir -p /home/brokeforge/.ssh',
            'touch /home/brokeforge/.ssh/config',
            'chmod 600 /home/brokeforge/.ssh/config',

            // Check if config entry already exists, append if not
            sprintf(
                'if ! grep -q "Host github.com-site-%d" /home/brokeforge/.ssh/config; then cat >> /home/brokeforge/.ssh/config << \'SSH_CONFIG_EOF\'\n%s\nSSH_CONFIG_EOF\nfi',
                $siteId,
                $sshConfigEntry
            ),
        ];
    }

    /**
     * Transform Git URL to use SSH config host alias
     *
     * Converts standard GitHub SSH URLs to use the site-specific host alias
     * defined in SSH config. This routes Git operations through the dedicated
     * deploy key for this site.
     *
     * Examples:
     *   git@github.com:owner/repo.git → git@github.com-site-123:owner/repo.git
     *   ssh://git@github.com/owner/repo.git → ssh://git@github.com-site-123/owner/repo.git
     *
     * @param  string  $originalUrl  The original Git SSH URL
     * @param  int  $siteId  The site ID for the host alias
     * @return string The transformed URL using the host alias
     */
    protected function transformGitUrlForSshConfig(string $originalUrl, int $siteId): string
    {
        // Transform git@github.com: format to use host alias
        if (str_starts_with($originalUrl, 'git@github.com:')) {
            return str_replace('git@github.com:', "git@github.com-site-{$siteId}:", $originalUrl);
        }

        // Transform ssh://git@github.com/ format to use host alias
        if (str_starts_with($originalUrl, 'ssh://git@github.com/')) {
            return str_replace('ssh://git@github.com/', "ssh://git@github.com-site-{$siteId}/", $originalUrl);
        }

        // Return unchanged if not a recognized GitHub SSH URL
        return $originalUrl;
    }
}
