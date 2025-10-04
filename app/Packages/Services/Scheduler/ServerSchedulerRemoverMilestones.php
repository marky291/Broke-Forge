<?php

namespace App\Packages\Services\Scheduler;

use App\Packages\Base\Milestones;

class ServerSchedulerRemoverMilestones extends Milestones
{
    public const STOP_TASKS = 'stop_tasks';

    public const REMOVE_CRON_ENTRIES = 'remove_cron_entries';

    public const REMOVE_FILES = 'remove_files';

    public const CLEANUP_DATABASE = 'cleanup_database';

    public const COMPLETE = 'complete';

    private const LABELS = [
        self::STOP_TASKS => 'Stopping scheduled tasks',
        self::REMOVE_CRON_ENTRIES => 'Removing cron entries',
        self::REMOVE_FILES => 'Removing scheduler files',
        self::CLEANUP_DATABASE => 'Cleaning up database',
        self::COMPLETE => 'Removal complete',
    ];

    public static function labels(): array
    {
        return self::LABELS;
    }

    public static function label(string $milestone): ?string
    {
        return self::LABELS[$milestone] ?? null;
    }

    public function countLabels(): int
    {
        return count(self::LABELS);
    }
}
