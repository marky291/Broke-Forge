<?php

namespace App\Packages\Services\Scheduler\Task;

use App\Packages\Base\Milestones;

class ServerScheduleTaskRemoverMilestones extends Milestones
{
    public const PREPARE_REMOVAL = 'prepare_removal';

    public const REMOVE_CRON_ENTRY = 'remove_cron_entry';

    public const REMOVE_WRAPPER_SCRIPT = 'remove_wrapper_script';

    public const VERIFY_REMOVAL = 'verify_removal';

    public const COMPLETE = 'complete';

    private const LABELS = [
        self::PREPARE_REMOVAL => 'Preparing task removal',
        self::REMOVE_CRON_ENTRY => 'Removing cron entry',
        self::REMOVE_WRAPPER_SCRIPT => 'Removing wrapper script',
        self::VERIFY_REMOVAL => 'Verifying removal',
        self::COMPLETE => 'Task removal complete',
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
