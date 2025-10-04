<?php

namespace App\Packages\Services\Scheduler\Task;

use App\Packages\Base\Milestones;

class ServerScheduleTaskInstallerMilestones extends Milestones
{
    public const PREPARE_TASK = 'prepare_task';

    public const CREATE_WRAPPER_SCRIPT = 'create_wrapper_script';

    public const INSTALL_CRON_ENTRY = 'install_cron_entry';

    public const VERIFY_CRON = 'verify_cron';

    public const COMPLETE = 'complete';

    private const LABELS = [
        self::PREPARE_TASK => 'Preparing task',
        self::CREATE_WRAPPER_SCRIPT => 'Creating wrapper script',
        self::INSTALL_CRON_ENTRY => 'Installing cron entry',
        self::VERIFY_CRON => 'Verifying cron installation',
        self::COMPLETE => 'Task installation complete',
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
