<?php

namespace App\Packages\Services\TimeSync;

use App\Packages\Core\Base\PackageInstaller;
use App\Packages\Core\Base\ServerPackage;

/**
 * Time Synchronization Installation Class
 *
 * Handles systemd-timesyncd configuration to ensure accurate system time.
 * Prevents APT repository validation failures due to clock skew.
 */
class TimeSyncInstaller extends PackageInstaller implements ServerPackage
{
    /**
     * Execute time synchronization setup
     */
    public function execute(): void
    {
        $this->install($this->commands());
    }

    protected function commands(): array
    {
        return [
            // Ensure systemd-timesyncd is installed (usually pre-installed on Ubuntu)
            'DEBIAN_FRONTEND=noninteractive apt-get install -y systemd-timesyncd',

            // Enable NTP synchronization
            'timedatectl set-ntp true',

            // Restart timesyncd service to apply changes
            'systemctl restart systemd-timesyncd',

            // Wait briefly for time sync to initialize
            'sleep 2',

            // Verify time sync status
            'timedatectl status',

            // Show current synchronization state
            'timedatectl show-timesync --all || true',
        ];
    }
}
