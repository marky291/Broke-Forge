<?php

namespace App\Packages\Services\Supervisor;

use App\Enums\TaskStatus;
use App\Packages\Base\PackageInstaller;
use App\Packages\Base\ServerPackage;

/**
 * Supervisor Installation Class
 *
 * Handles installation of supervisord process manager on remote servers.
 * Supervisor monitors and controls processes, automatically restarting them on failure.
 */
class SupervisorInstaller extends PackageInstaller implements ServerPackage
{
    /**
     * Execute the supervisor installation
     */
    public function execute(): void
    {
        $this->install($this->commands());
    }

    protected function commands(): array
    {
        return [

            // Ensure system is up to date
            'DEBIAN_FRONTEND=noninteractive apt-get update -y',

            // Install supervisor
            'DEBIAN_FRONTEND=noninteractive apt-get install -y supervisor',

            // Create directory structure for supervisor configs
            'mkdir -p /etc/supervisor/conf.d',
            'mkdir -p /var/log/supervisor',

            // Set permissions
            'chmod 755 /etc/supervisor/conf.d',
            'chmod 755 /var/log/supervisor',

            // Ensure supervisor service is enabled and started
            'systemctl enable supervisor',
            'systemctl start supervisor',

            // Verify supervisor is running
            'systemctl is-active --quiet supervisor || (echo "Supervisor service failed to start" && exit 1)',

            // Verify supervisorctl is working
            'supervisorctl version || (echo "Supervisorctl failed" && exit 1)',

            // Mark supervisor as active in database
            fn () => $this->server->update([
                'supervisor_status' => TaskStatus::Active,
                'supervisor_installed_at' => now(),
            ]),

        ];
    }
}
