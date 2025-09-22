<?php

namespace Tests\Unit\Packages\Enums;

use App\Packages\Enums\GitStatus;
use Tests\TestCase;

class GitStatusTest extends TestCase
{
    public function test_git_status_enum_values(): void
    {
        $this->assertEquals('not_installed', GitStatus::NotInstalled->value);
        $this->assertEquals('installing', GitStatus::Installing->value);
        $this->assertEquals('installed', GitStatus::Installed->value);
        $this->assertEquals('failed', GitStatus::Failed->value);
        $this->assertEquals('updating', GitStatus::Updating->value);
    }

    public function test_git_status_labels(): void
    {
        $this->assertEquals('Not Installed', GitStatus::NotInstalled->label());
        $this->assertEquals('Installing', GitStatus::Installing->label());
        $this->assertEquals('Installed', GitStatus::Installed->label());
        $this->assertEquals('Failed', GitStatus::Failed->label());
        $this->assertEquals('Updating', GitStatus::Updating->label());
    }

    public function test_is_processing_method(): void
    {
        $this->assertFalse(GitStatus::NotInstalled->isProcessing());
        $this->assertTrue(GitStatus::Installing->isProcessing());
        $this->assertFalse(GitStatus::Installed->isProcessing());
        $this->assertFalse(GitStatus::Failed->isProcessing());
        $this->assertTrue(GitStatus::Updating->isProcessing());
    }

    public function test_is_terminal_method(): void
    {
        $this->assertFalse(GitStatus::NotInstalled->isTerminal());
        $this->assertFalse(GitStatus::Installing->isTerminal());
        $this->assertTrue(GitStatus::Installed->isTerminal());
        $this->assertTrue(GitStatus::Failed->isTerminal());
        $this->assertFalse(GitStatus::Updating->isTerminal());
    }

    public function test_can_retry_method(): void
    {
        $this->assertTrue(GitStatus::NotInstalled->canRetry());
        $this->assertFalse(GitStatus::Installing->canRetry());
        $this->assertFalse(GitStatus::Installed->canRetry());
        $this->assertTrue(GitStatus::Failed->canRetry());
        $this->assertFalse(GitStatus::Updating->canRetry());
    }

    public function test_git_status_from_string(): void
    {
        $this->assertEquals(GitStatus::NotInstalled, GitStatus::from('not_installed'));
        $this->assertEquals(GitStatus::Installing, GitStatus::from('installing'));
        $this->assertEquals(GitStatus::Installed, GitStatus::from('installed'));
        $this->assertEquals(GitStatus::Failed, GitStatus::from('failed'));
        $this->assertEquals(GitStatus::Updating, GitStatus::from('updating'));
    }

    public function test_git_status_try_from_string(): void
    {
        $this->assertEquals(GitStatus::NotInstalled, GitStatus::tryFrom('not_installed'));
        $this->assertNull(GitStatus::tryFrom('invalid_status'));
    }

    public function test_git_status_cases(): void
    {
        $cases = GitStatus::cases();

        $this->assertCount(5, $cases);
        $this->assertContains(GitStatus::NotInstalled, $cases);
        $this->assertContains(GitStatus::Installing, $cases);
        $this->assertContains(GitStatus::Installed, $cases);
        $this->assertContains(GitStatus::Failed, $cases);
        $this->assertContains(GitStatus::Updating, $cases);
    }

    public function test_git_status_is_backed_enum(): void
    {
        $reflection = new \ReflectionEnum(GitStatus::class);
        $this->assertTrue($reflection->isBacked());
        $this->assertEquals('string', $reflection->getBackingType()->getName());
    }

    public function test_processing_and_terminal_states_are_mutually_exclusive(): void
    {
        foreach (GitStatus::cases() as $status) {
            if ($status->isProcessing()) {
                $this->assertFalse($status->isTerminal(), "Status {$status->value} cannot be both processing and terminal");
            }
        }
    }

    public function test_only_failed_and_not_installed_can_retry(): void
    {
        $retryableStatuses = array_filter(GitStatus::cases(), fn ($status) => $status->canRetry());
        $retryableValues = array_map(fn ($status) => $status->value, $retryableStatuses);

        $this->assertEquals(['not_installed', 'failed'], array_values($retryableValues));
    }
}
