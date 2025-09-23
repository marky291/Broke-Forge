<?php

namespace App\Packages\Services\Sites\Git;

use App\Packages\Base\Milestones;

/**
 * Milestones surfaced while cloning or updating a site's Git repository.
 */
class GitRepositoryInstallerMilestones extends Milestones
{
    public const ENSURE_REPOSITORY_DIRECTORY = 'ensure_repository_directory';

    public const CLONE_OR_FETCH_REPOSITORY = 'clone_or_fetch_repository';

    public const CHECKOUT_TARGET_BRANCH = 'checkout_target_branch';

    public const SYNC_WORKTREE = 'sync_worktree';

    public const COMPLETE = 'complete';

    private const LABELS = [
        self::ENSURE_REPOSITORY_DIRECTORY => 'Ensuring repository directory exists',
        self::CLONE_OR_FETCH_REPOSITORY => 'Cloning or fetching repository',
        self::CHECKOUT_TARGET_BRANCH => 'Checking out target branch',
        self::SYNC_WORKTREE => 'Synchronizing working tree',
        self::COMPLETE => 'Repository ready for deployment',
    ];

    /**
     * Get all milestone labels
     */
    public static function labels(): array
    {
        return self::LABELS;
    }

    /**
     * Get label for specific milestone
     */
    public static function label(string $milestone): ?string
    {
        return self::LABELS[$milestone] ?? null;
    }

    /**
     * Count total milestones for progress calculation
     */
    public function countLabels(): int
    {
        return count(self::LABELS);
    }
}
