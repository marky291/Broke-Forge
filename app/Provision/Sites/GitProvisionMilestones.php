<?php

namespace App\Provision\Sites;

use App\Provision\Milestones;

/**
 * Milestones surfaced while cloning or updating a site's Git repository.
 */
class GitProvisionMilestones extends Milestones
{
    public const ENSURE_REPOSITORY_DIRECTORY = 'Ensuring repository directory exists';

    public const CLONE_OR_FETCH_REPOSITORY = 'Cloning or fetching repository';

    public const CHECKOUT_TARGET_BRANCH = 'Checking out target branch';

    public const SYNC_WORKTREE = 'Synchronizing working tree';

    public const COMPLETE = 'Repository ready for deployment';

    public function countLabels(): int
    {
        return 5;
    }
}
