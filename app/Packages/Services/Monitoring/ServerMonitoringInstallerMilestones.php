<?php

namespace App\Packages\Services\Monitoring;

use App\Packages\Base\Milestones;

class ServerMonitoringInstallerMilestones extends Milestones
{
    public const PREPARE_SYSTEM = 'prepare_system';

    public const INSTALL_DEPENDENCIES = 'install_dependencies';

    public const CREATE_MONITORING_SCRIPT = 'create_monitoring_script';

    public const CONFIGURE_COLLECTION = 'configure_collection';

    public const START_MONITORING = 'start_monitoring';

    public const VERIFY_INSTALL = 'verify_install';

    public const COMPLETE = 'complete';

    private const LABELS = [
        self::PREPARE_SYSTEM => 'Preparing system',
        self::INSTALL_DEPENDENCIES => 'Installing monitoring dependencies',
        self::CREATE_MONITORING_SCRIPT => 'Creating monitoring script',
        self::CONFIGURE_COLLECTION => 'Configuring metrics collection',
        self::START_MONITORING => 'Starting monitoring service',
        self::VERIFY_INSTALL => 'Verifying monitoring installation',
        self::COMPLETE => 'Monitoring setup complete',
    ];

    /**
     * @return array<string, string>
     */
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
