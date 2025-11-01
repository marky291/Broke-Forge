<?php

namespace App\Packages\Services\Supervisor;

use App\Packages\Core\Base\PackageRemover;
use App\Packages\Core\Base\ServerPackage;

/**
 * Supervisor Removal Class
 *
 * Handles removal of supervisord from remote servers
 */
class SupervisorRemover extends PackageRemover implements ServerPackage
{
    public function execute(): void
    {
        $this->remove($this->commands());
    }

    protected function commands(): array
    {
        return [

            // Stop all supervisor processes
            'supervisorctl stop all || true',

            // Remove all config files
            'rm -rf /etc/supervisor/conf.d/*.conf || true',

            // Stop supervisor service
            'systemctl stop supervisor || true',
            'systemctl disable supervisor || true',

            // Uninstall supervisor
            'DEBIAN_FRONTEND=noninteractive apt-get remove -y supervisor || true',
            'DEBIAN_FRONTEND=noninteractive apt-get autoremove -y || true',

            // Mark supervisor tasks as uninstalled
            fn () => $this->server->supervisorTasks()->update([
                'status' => 'inactive',
                'uninstalled_at' => now(),
            ]),

            // Mark supervisor as uninstalled in database
            fn () => $this->server->update([
                'supervisor_status' => null,
                'supervisor_uninstalled_at' => now(),
            ]),

        ];
    }
}
