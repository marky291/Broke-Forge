<?php

namespace Tests\Unit\Packages\Services\Sites;

use App\Packages\Services\Sites\GitRepositoryInstallerMilestones;
use PHPUnit\Framework\TestCase;

class GitRepositoryInstallerMilestonesTest extends TestCase
{
    private GitRepositoryInstallerMilestones $milestones;

    protected function setUp(): void
    {
        parent::setUp();
        $this->milestones = new GitRepositoryInstallerMilestones;
    }

    public function test_extends_milestones_base_class(): void
    {
        $this->assertInstanceOf(\App\Packages\Base\Milestones::class, $this->milestones);
    }

    public function test_count_labels_returns_correct_number(): void
    {
        $this->assertEquals(5, $this->milestones->countLabels());
    }

    public function test_has_all_required_constants(): void
    {
        $this->assertEquals('ensure_repository_directory', GitRepositoryInstallerMilestones::ENSURE_REPOSITORY_DIRECTORY);
        $this->assertEquals('clone_or_fetch_repository', GitRepositoryInstallerMilestones::CLONE_OR_FETCH_REPOSITORY);
        $this->assertEquals('checkout_target_branch', GitRepositoryInstallerMilestones::CHECKOUT_TARGET_BRANCH);
        $this->assertEquals('sync_worktree', GitRepositoryInstallerMilestones::SYNC_WORKTREE);
        $this->assertEquals('complete', GitRepositoryInstallerMilestones::COMPLETE);
    }

    public function test_all_constants_are_strings(): void
    {
        $this->assertIsString(GitRepositoryInstallerMilestones::ENSURE_REPOSITORY_DIRECTORY);
        $this->assertIsString(GitRepositoryInstallerMilestones::CLONE_OR_FETCH_REPOSITORY);
        $this->assertIsString(GitRepositoryInstallerMilestones::CHECKOUT_TARGET_BRANCH);
        $this->assertIsString(GitRepositoryInstallerMilestones::SYNC_WORKTREE);
        $this->assertIsString(GitRepositoryInstallerMilestones::COMPLETE);
    }

    public function test_constants_have_unique_values(): void
    {
        $constants = [
            GitRepositoryInstallerMilestones::ENSURE_REPOSITORY_DIRECTORY,
            GitRepositoryInstallerMilestones::CLONE_OR_FETCH_REPOSITORY,
            GitRepositoryInstallerMilestones::CHECKOUT_TARGET_BRANCH,
            GitRepositoryInstallerMilestones::SYNC_WORKTREE,
            GitRepositoryInstallerMilestones::COMPLETE,
        ];

        $this->assertEquals(count($constants), count(array_unique($constants)));
    }

    public function test_count_matches_actual_constants(): void
    {
        $reflection = new \ReflectionClass(GitRepositoryInstallerMilestones::class);
        $constants = $reflection->getConstants();

        // Should have 5 milestone constants + 1 LABELS array = 6 total
        $this->assertCount(6, $constants);
        $this->assertEquals(5, $this->milestones->countLabels());
    }
}
