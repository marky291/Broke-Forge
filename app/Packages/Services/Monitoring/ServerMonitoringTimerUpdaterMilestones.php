<?php

namespace App\Packages\Services\Monitoring;

use App\Packages\Base\Milestones;

class ServerMonitoringTimerUpdaterMilestones extends Milestones
{
    public const UPDATE_TIMER = 'update_timer';

    public const RELOAD_SYSTEMD = 'reload_systemd';

    public const RESTART_TIMER = 'restart_timer';

    public const COMPLETE = 'complete';

    private const LABELS = [
        self::UPDATE_TIMER => 'Updating timer configuration',
        self::RELOAD_SYSTEMD => 'Reloading systemd',
        self::RESTART_TIMER => 'Restarting timer',
        self::COMPLETE => 'Timer update complete',
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
