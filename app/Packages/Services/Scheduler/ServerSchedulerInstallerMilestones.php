<?php

namespace App\Packages\Services\Scheduler;

use App\Packages\Base\Milestones;

class ServerSchedulerInstallerMilestones extends Milestones
{
    public const PREPARE_SYSTEM = 'prepare_system';

    public const INSTALL_DEPENDENCIES = 'install_dependencies';

    public const CREATE_DIRECTORIES = 'create_directories';

    public const CONFIGURE_SCHEDULER = 'configure_scheduler';

    public const VERIFY_INSTALL = 'verify_install';

    public const COMPLETE = 'complete';

    private const LABELS = [
        self::PREPARE_SYSTEM => 'Preparing system',
        self::INSTALL_DEPENDENCIES => 'Installing dependencies',
        self::CREATE_DIRECTORIES => 'Creating directories',
        self::CONFIGURE_SCHEDULER => 'Configuring scheduler',
        self::VERIFY_INSTALL => 'Verifying installation',
        self::COMPLETE => 'Installation complete',
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
