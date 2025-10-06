<?php

namespace App\Packages\Services\Supervisor\Task;

use App\Packages\Base\Milestones;

class SupervisorTaskInstallerMilestones extends Milestones
{
    public const GENERATE_CONFIG = 'generate_config';

    public const DEPLOY_CONFIG = 'deploy_config';

    public const RELOAD_SUPERVISOR = 'reload_supervisor';

    public const COMPLETE = 'complete';

    private const LABELS = [
        self::GENERATE_CONFIG => 'Generating task configuration',
        self::DEPLOY_CONFIG => 'Deploying configuration',
        self::RELOAD_SUPERVISOR => 'Reloading Supervisor',
        self::COMPLETE => 'Task installed',
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
