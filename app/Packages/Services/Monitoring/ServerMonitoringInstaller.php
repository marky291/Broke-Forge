<?php

namespace App\Packages\Services\Monitoring;

use App\Enums\TaskStatus;
use App\Packages\Core\Base\PackageInstaller;
use App\Packages\Core\Base\ServerPackage;

/**
 * Server Monitoring Installation Class
 *
 * Handles installation of monitoring capabilities for CPU, Memory, and Storage metrics
 */
class ServerMonitoringInstaller extends PackageInstaller implements ServerPackage
{
    /**
     * Execute the monitoring installation
     */
    public function execute(): void
    {
        $this->install($this->commands());
    }

    protected function commands(): array
    {
        $appUrl = config('app.url');
        $serverId = $this->server->id;

        // Generate monitoring token for API authentication
        $monitoringToken = $this->server->generateMonitoringToken();

        // Generate the monitoring script content
        $monitoringScript = view('monitoring.metrics-collector', [
            'appUrl' => $appUrl,
            'serverId' => $serverId,
            'monitoringToken' => $monitoringToken,
        ])->render();

        return [

            // Create monitoring directory
            'mkdir -p /opt/brokeforge/monitoring',

            // Install required packages for monitoring (sysstat for iostat, bc for calculations)
            'DEBIAN_FRONTEND=noninteractive apt-get update -y',
            'DEBIAN_FRONTEND=noninteractive apt-get install -y sysstat bc curl',

            // Create the metrics collection script with shebang
            "cat > /opt/brokeforge/monitoring/collect-metrics.sh << 'EOF'\n#!/bin/bash\n{$monitoringScript}\nEOF",

            // Make the script executable
            'chmod +x /opt/brokeforge/monitoring/collect-metrics.sh',

            // Create systemd service for monitoring
            function () {
                $serviceContent = view('monitoring.systemd-service')->render();

                return "cat > /etc/systemd/system/brokeforge-monitoring.service << 'EOF'\n{$serviceContent}\nEOF";
            },

            // Create systemd timer for periodic collection
            function () {
                $intervalMinutes = config('monitoring.collection_interval') / 60;
                $timerContent = view('monitoring.systemd-timer', [
                    'intervalMinutes' => $intervalMinutes,
                ])->render();

                return "cat > /etc/systemd/system/brokeforge-monitoring.timer << 'EOF'\n{$timerContent}\nEOF";
            },

            // Reload systemd
            'systemctl daemon-reload',

            // Enable and start the monitoring timer
            'systemctl enable --now brokeforge-monitoring.timer',

            // Verify timer is active
            'systemctl status brokeforge-monitoring.timer',

            // Run the monitoring script once to verify it works (allow failure - timer will retry)
            '/opt/brokeforge/monitoring/collect-metrics.sh || true',

            // Mark monitoring as active in database
            fn () => $this->server->update([
                'monitoring_status' => TaskStatus::Active,
                'monitoring_collection_interval' => config('monitoring.collection_interval'),
                'monitoring_installed_at' => now(),
            ]),

        ];
    }
}
