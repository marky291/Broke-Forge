<?php

namespace App\Packages\Services\Monitoring;

use App\Packages\Base\Milestones;

class ServerMonitoringRemoverMilestones extends Milestones
{
    public const STOP_MONITORING = 'stop_monitoring';

    public const REMOVE_SERVICES = 'remove_services';

    public const REMOVE_SCRIPTS = 'remove_scripts';

    public const CLEANUP_DATABASE = 'cleanup_database';

    public const COMPLETE = 'complete';

    private const LABELS = [
        self::STOP_MONITORING => 'Stopping monitoring service',
        self::REMOVE_SERVICES => 'Removing systemd services',
        self::REMOVE_SCRIPTS => 'Removing monitoring scripts',
        self::CLEANUP_DATABASE => 'Cleaning up database records',
        self::COMPLETE => 'Monitoring removal complete',
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
