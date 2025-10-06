<?php

namespace App\Packages\Services\Supervisor;

use App\Enums\SupervisorStatus;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageRemover;
use App\Packages\Base\ServerPackage;
use App\Packages\Enums\CredentialType;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

/**
 * Supervisor Removal Class
 *
 * Handles removal of supervisord from remote servers
 */
class SupervisorRemover extends PackageRemover implements ServerPackage
{
    public function packageName(): PackageName
    {
        return PackageName::Supervisor;
    }

    public function packageType(): PackageType
    {
        return PackageType::Supervisor;
    }

    public function milestones(): Milestones
    {
        return new SupervisorRemoverMilestones;
    }

    public function credentialType(): CredentialType
    {
        return CredentialType::Root;
    }

    public function execute(): void
    {
        $this->remove($this->commands());
    }

    protected function commands(): array
    {
        return [
            $this->track(SupervisorRemoverMilestones::STOP_TASKS),

            // Stop all supervisor processes
            'supervisorctl stop all || true',

            $this->track(SupervisorRemoverMilestones::REMOVE_CONFIGS),

            // Remove all config files
            'rm -rf /etc/supervisor/conf.d/*.conf || true',

            // Stop supervisor service
            'systemctl stop supervisor || true',
            'systemctl disable supervisor || true',

            $this->track(SupervisorRemoverMilestones::UNINSTALL_SUPERVISOR),

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
                'supervisor_status' => SupervisorStatus::Uninstalled,
                'supervisor_uninstalled_at' => now(),
            ]),

            $this->track(SupervisorRemoverMilestones::COMPLETE),
        ];
    }
}
