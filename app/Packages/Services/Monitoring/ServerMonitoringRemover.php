<?php

namespace App\Packages\Services\Monitoring;

use App\Enums\MonitoringStatus;
use App\Models\ServerMetric;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageRemover;
use App\Packages\Base\ServerPackage;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

/**
 * Server Monitoring Removal Class
 *
 * Handles removal of monitoring capabilities
 */
class ServerMonitoringRemover extends PackageRemover implements ServerPackage
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
        return new ServerMonitoringRemoverMilestones;
    }

    /**
     * Execute the monitoring removal
     */
    public function execute(): void
    {
        $this->remove($this->commands());
    }

    protected function commands(): array
    {
        return [
            $this->track(ServerMonitoringRemoverMilestones::STOP_MONITORING),

            // Stop and disable the monitoring timer and service
            'systemctl stop brokeforge-monitoring.timer >/dev/null 2>&1 || true',
            'systemctl disable brokeforge-monitoring.timer >/dev/null 2>&1 || true',
            'systemctl stop brokeforge-monitoring.service >/dev/null 2>&1 || true',
            'systemctl disable brokeforge-monitoring.service >/dev/null 2>&1 || true',

            $this->track(ServerMonitoringRemoverMilestones::REMOVE_SERVICES),

            // Remove systemd service and timer files
            'rm -f /etc/systemd/system/brokeforge-monitoring.service',
            'rm -f /etc/systemd/system/brokeforge-monitoring.timer',

            // Reload systemd
            'systemctl daemon-reload',

            $this->track(ServerMonitoringRemoverMilestones::REMOVE_SCRIPTS),

            // Remove monitoring directory and scripts
            'rm -rf /opt/brokeforge/monitoring',

            $this->track(ServerMonitoringRemoverMilestones::CLEANUP_DATABASE),

            // Mark monitoring as uninstalled in database
            fn () => $this->server->update([
                'monitoring_status' => MonitoringStatus::Uninstalled,
                'monitoring_uninstalled_at' => now(),
            ]),

            // Optionally clean up old metrics
            fn () => ServerMetric::where('server_id', $this->server->id)
                ->where('created_at', '<', now()->subDays(config('monitoring.retention_days')))
                ->delete(),

            $this->track(ServerMonitoringRemoverMilestones::COMPLETE),
        ];
    }
}
