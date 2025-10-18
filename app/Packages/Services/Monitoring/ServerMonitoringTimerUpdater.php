<?php

namespace App\Packages\Services\Monitoring;

use App\Packages\Base\PackageInstaller;
use App\Packages\Base\ServerPackage;

/**
 * Server Monitoring Timer Updater
 *
 * Updates the collection interval for monitoring metrics without reinstalling
 */
class ServerMonitoringTimerUpdater extends PackageInstaller implements ServerPackage
{
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

            // Update the systemd timer configuration
            "cat > /etc/systemd/system/brokeforge-monitoring.timer << 'EOF'\n{$timerContent}\nEOF",

            // Reload systemd to pick up the changes
            'systemctl daemon-reload',

            // Restart the timer to apply the new interval
            'systemctl restart brokeforge-monitoring.timer',

            // Verify timer is active
            'systemctl is-active brokeforge-monitoring.timer',

            // Update database record
            fn () => $this->server->update([
                'monitoring_collection_interval' => $intervalSeconds,
            ]),

        ];
    }
}
