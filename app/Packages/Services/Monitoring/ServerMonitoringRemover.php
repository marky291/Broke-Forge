<?php

namespace App\Packages\Services\Monitoring;

use App\Models\ServerMetric;
use App\Packages\Core\Base\PackageRemover;
use App\Packages\Core\Base\ServerPackage;

/**
 * Server Monitoring Removal Class
 *
 * Handles removal of monitoring capabilities
 */
class ServerMonitoringRemover extends PackageRemover implements ServerPackage
{
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

            // Stop and disable the monitoring timer and service
            'systemctl stop brokeforge-monitoring.timer >/dev/null 2>&1 || true',
            'systemctl disable brokeforge-monitoring.timer >/dev/null 2>&1 || true',
            'systemctl stop brokeforge-monitoring.service >/dev/null 2>&1 || true',
            'systemctl disable brokeforge-monitoring.service >/dev/null 2>&1 || true',

            // Remove systemd service and timer files
            'rm -f /etc/systemd/system/brokeforge-monitoring.service',
            'rm -f /etc/systemd/system/brokeforge-monitoring.timer',

            // Reload systemd
            'systemctl daemon-reload',

            // Remove monitoring directory and scripts
            'rm -rf /opt/brokeforge/monitoring',

            // Mark monitoring as uninstalled in database
            fn () => $this->server->update([
                'monitoring_status' => null,
                'monitoring_uninstalled_at' => now(),
            ]),

            // Optionally clean up old metrics
            fn () => ServerMetric::where('server_id', $this->server->id)
                ->where('created_at', '<', now()->subDays(config('monitoring.retention_days')))
                ->delete(),

        ];
    }
}
