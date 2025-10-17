<?php

namespace App\Packages\Services\Monitoring;

use App\Packages\Base\Milestones;
use App\Packages\Base\PackageInstaller;
use App\Packages\Base\ServerPackage;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

/**
 * Server Monitoring Timer Updater
 *
 * Updates the collection interval for monitoring metrics without reinstalling
 */
class ServerMonitoringTimerUpdater extends PackageInstaller implements ServerPackage
{
    public function packageName(): PackageName
    {
        return PackageName::Monitoring;
    }

    public function packageType(): PackageType
    {
        return PackageType::Monitoring;
    }

    public function milestones(): Milestones
    {
        return new ServerMonitoringTimerUpdaterMilestones;
    }

    /**
     * Execute the timer update
     *
     * @param  int  $intervalSeconds  Collection interval in seconds
     */
    public function execute(int $intervalSeconds): void
    {
        $this->install($this->commands($intervalSeconds));
    }

    protected function commands(int $intervalSeconds): array
    {
        $intervalMinutes = $intervalSeconds / 60;

        // Generate the updated timer content
        $timerContent = view('monitoring.systemd-timer', [
            'intervalMinutes' => $intervalMinutes,
        ])->render();

        return [
            $this->track(ServerMonitoringTimerUpdaterMilestones::UPDATE_TIMER),

            // Update the systemd timer configuration
            "cat > /etc/systemd/system/brokeforge-monitoring.timer << 'EOF'\n{$timerContent}\nEOF",

            $this->track(ServerMonitoringTimerUpdaterMilestones::RELOAD_SYSTEMD),

            // Reload systemd to pick up the changes
            'systemctl daemon-reload',

            $this->track(ServerMonitoringTimerUpdaterMilestones::RESTART_TIMER),

            // Restart the timer to apply the new interval
            'systemctl restart brokeforge-monitoring.timer',

            // Verify timer is active
            'systemctl is-active brokeforge-monitoring.timer',

            // Update database record
            fn () => $this->server->update([
                'monitoring_collection_interval' => $intervalSeconds,
            ]),

            $this->track(ServerMonitoringTimerUpdaterMilestones::COMPLETE),
        ];
    }
}
