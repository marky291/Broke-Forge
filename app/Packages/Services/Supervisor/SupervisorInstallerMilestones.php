<?php

namespace App\Packages\Services\Supervisor;

use App\Packages\Base\Milestones;

class SupervisorInstallerMilestones extends Milestones
{
    public const PREPARE_SYSTEM = 'prepare_system';

    public const INSTALL_SUPERVISOR = 'install_supervisor';

    public const CREATE_DIRECTORIES = 'create_directories';

    public const CONFIGURE_SERVICE = 'configure_service';

    public const VERIFY_INSTALL = 'verify_install';

    public const COMPLETE = 'complete';

    private const LABELS = [
        self::PREPARE_SYSTEM => 'Preparing system',
        self::INSTALL_SUPERVISOR => 'Installing Supervisor',
        self::CREATE_DIRECTORIES => 'Creating directories',
        self::CONFIGURE_SERVICE => 'Configuring service',
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
