<?php

namespace App\Packages\Services\Supervisor\Task;

use App\Packages\Base\Milestones;

class SupervisorTaskRemoverMilestones extends Milestones
{
    public const STOP_TASK = 'stop_task';

    public const REMOVE_CONFIG = 'remove_config';

    public const RELOAD_SUPERVISOR = 'reload_supervisor';

    public const COMPLETE = 'complete';

    private const LABELS = [
        self::STOP_TASK => 'Stopping task',
        self::REMOVE_CONFIG => 'Removing configuration',
        self::RELOAD_SUPERVISOR => 'Reloading Supervisor',
        self::COMPLETE => 'Task removed',
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
