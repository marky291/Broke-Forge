<?php

namespace App\Provision\Sites;

use App\Models\ServerSite;
use App\Provision\Enums\ServiceType;
use App\Provision\InstallableService;
use App\Provision\Milestones;
use App\Provision\Server\Access\SshCredential;
use App\Provision\Server\Access\UserCredential;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use LogicException;

/**
 * Handle cloning or updating a site's Git repository over SSH.
 */
class GitProvision extends InstallableService
{
    protected ?ServerSite $site = null;

    /**
     * Original repository identifier supplied by the operator.
     */
    protected ?string $repositoryInput = null;

    /**
     * Repository in SSH form ready for git clone.
     */
    protected ?string $repositorySshUrl = null;

    protected string $branch = 'main';

    protected ?string $documentRoot = null;

    /**
     * Cached repository metadata persisted back onto the Site model.
     *
     * @var array<string, mixed>
     */
    protected array $repositoryConfiguration = [];

    /**
     * Associate the provisioner with a site record to use for path resolution and persistence.
     */
    public function forSite(ServerSite $site): self
    {
        $this->site = $site;

        return $this;
    }

    /**
     * Configure repository details ahead of cloning.
     *
     * Expected keys: repository (owner/name or SSH URL), branch (optional), provider (optional), document_root (optional).
     *
     * @param  array<string, mixed>  $config
     */
    public function setConfiguration(array $config): self
    {
        if (! isset($config['repository']) || ! is_string($config['repository'])) {
            throw new InvalidArgumentException('A repository identifier is required.');
        }

        $this->repositoryInput = trim($config['repository']);
        $this->repositorySshUrl = $this->normalizeRepository($this->repositoryInput);
        $this->branch = $this->normalizeBranch($config['branch'] ?? null);

        if (isset($config['document_root'])) {
            if (! is_string($config['document_root']) || trim($config['document_root']) === '') {
                throw new InvalidArgumentException('Document root must be a non-empty string when provided.');
            }

            $this->documentRoot = trim($config['document_root']);
        }

        $provider = isset($config['provider']) && is_string($config['provider'])
            ? trim($config['provider'])
            : 'github';

        $this->repositoryConfiguration = [
            'provider' => $provider,
            'repository' => $this->repositoryInput,
            'branch' => $this->branch,
        ];

        return $this;
    }

    /**
     * Execute the remote git provisioning workflow.
     */
    public function provision(): void
    {
        if (! $this->site instanceof ServerSite) {
            throw new LogicException('A site must be associated before provisioning a repository.');
        }

        if (! $this->repositorySshUrl) {
            throw new LogicException('Repository configuration must be defined before provisioning.');
        }

        $this->install($this->commands());
    }

    protected function serviceType(): string
    {
        return ServiceType::SITE;
    }

    protected function milestones(): Milestones
    {
        return new GitProvisionMilestones;
    }

    protected function sshCredential(): SshCredential
    {
        return new UserCredential;
    }

    /**
     * Compile the command list executed on the remote host.
     *
     * @return array<int, \Closure|string>
     */
    protected function commands(): array
    {
        $documentRoot = rtrim($this->resolveDocumentRoot(), '/');

        return [
            $this->track(GitProvisionMilestones::ENSURE_REPOSITORY_DIRECTORY),
            sprintf('mkdir -p %s', escapeshellarg($documentRoot)),

            $this->track(GitProvisionMilestones::CLONE_OR_FETCH_REPOSITORY),
            sprintf(
                'REPO_DIR=%1$s; if [ -d "$REPO_DIR/.git" ]; then cd "$REPO_DIR" && git fetch --all --prune; else git clone %2$s "$REPO_DIR"; fi',
                escapeshellarg($documentRoot),
                escapeshellarg($this->repositorySshUrl)
            ),

            $this->track(GitProvisionMilestones::CHECKOUT_TARGET_BRANCH),
            sprintf(
                'REPO_DIR=%1$s; cd "$REPO_DIR" && git checkout %2$s',
                escapeshellarg($documentRoot),
                escapeshellarg($this->branch)
            ),

            $this->track(GitProvisionMilestones::SYNC_WORKTREE),
            sprintf(
                'REPO_DIR=%1$s; cd "$REPO_DIR" && git reset --hard origin/%2$s && git pull origin %3$s',
                escapeshellarg($documentRoot),
                $this->branch,
                escapeshellarg($this->branch)
            ),

            $this->track(GitProvisionMilestones::COMPLETE),
            fn () => $this->persistRepositoryConfiguration(),
        ];
    }

    /**
     * Convert repository identifiers into SSH URLs suitable for clone operations.
     */
    protected function normalizeRepository(string $repository): string
    {
        $repository = trim($repository);

        if ($repository === '') {
            throw new InvalidArgumentException('Repository cannot be empty.');
        }

        if (str_starts_with($repository, 'git@') || str_starts_with($repository, 'ssh://') || str_starts_with($repository, 'https://')) {
            return $repository;
        }

        if (! preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $repository)) {
            throw new InvalidArgumentException('Repository must be an SSH URL or follow the owner/name format.');
        }

        return sprintf('git@github.com:%s.git', $repository);
    }

    /**
     * Validate and normalize the branch name.
     */
    protected function normalizeBranch(?string $branch): string
    {
        $branch = $branch ? trim($branch) : 'main';

        if ($branch === '') {
            throw new InvalidArgumentException('Branch cannot be empty.');
        }

        if (! preg_match('/^[A-Za-z0-9._\/-]{1,255}$/', $branch)) {
            throw new InvalidArgumentException('Branch may only contain letters, numbers, periods, hyphens, underscores, or slashes.');
        }

        return $branch;
    }

    /**
     * Determine the directory where the repository should live.
     */
    protected function resolveDocumentRoot(): string
    {
        if ($this->documentRoot) {
            return $this->documentRoot;
        }

        if ($this->site && is_string($this->site->document_root) && $this->site->document_root !== '') {
            return $this->site->document_root;
        }

        if ($this->site && is_string($this->site->domain) && $this->site->domain !== '') {
            return "/var/www/{$this->site->domain}/public";
        }

        throw new LogicException('Unable to determine repository document root.');
    }

    /**
     * Persist Git repository metadata onto the backing site record for later retrieval.
     */
    protected function persistRepositoryConfiguration(): void
    {
        if (! $this->site instanceof ServerSite || ! $this->site->exists) {
            return;
        }

        $configuration = $this->site->configuration ?? [];

        $configuration['git_repository'] = array_filter([
            'provider' => Arr::get($this->repositoryConfiguration, 'provider'),
            'repository' => Arr::get($this->repositoryConfiguration, 'repository'),
            'branch' => Arr::get($this->repositoryConfiguration, 'branch'),
            'deploy_key' => $this->resolveDeployKey(),
        ], fn ($value) => $value !== null && $value !== '');

        $this->site->update(['configuration' => $configuration]);
    }

    /**
     * Retrieve the public deploy key tied to the SSH access mechanism.
     */
    protected function resolveDeployKey(): ?string
    {
        $sshAccess = $this->resolveSshAccess();
        $publicKeyPath = $sshAccess->publicKey();

        if (! is_string($publicKeyPath) || ! is_readable($publicKeyPath)) {
            return null;
        }

        return trim((string) file_get_contents($publicKeyPath));
    }
}
