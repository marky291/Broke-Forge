<?php

namespace App\Packages\Services\Supervisor;

use App\Packages\Base\Milestones;

class SupervisorRemoverMilestones extends Milestones
{
    public const STOP_TASKS = 'stop_tasks';

    public const REMOVE_CONFIGS = 'remove_configs';

    public const UNINSTALL_SUPERVISOR = 'uninstall_supervisor';

    public const COMPLETE = 'complete';

    private const LABELS = [
        self::STOP_TASKS => 'Stopping all tasks',
        self::REMOVE_CONFIGS => 'Removing configurations',
        self::UNINSTALL_SUPERVISOR => 'Uninstalling Supervisor',
        self::COMPLETE => 'Uninstallation complete',
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
