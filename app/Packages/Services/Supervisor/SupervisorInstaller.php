<?php

namespace App\Packages\Services\Supervisor;

use App\Enums\SupervisorStatus;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageInstaller;
use App\Packages\Base\ServerPackage;
use App\Packages\Enums\CredentialType;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

/**
 * Supervisor Installation Class
 *
 * Handles installation of supervisord process manager on remote servers.
 * Supervisor monitors and controls processes, automatically restarting them on failure.
 */
class SupervisorInstaller extends PackageInstaller implements ServerPackage
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
        return new SupervisorInstallerMilestones;
    }

    public function credentialType(): CredentialType
    {
        return CredentialType::Root;
    }

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
            $this->track(SupervisorInstallerMilestones::PREPARE_SYSTEM),

            // Ensure system is up to date
            'DEBIAN_FRONTEND=noninteractive apt-get update -y',

            $this->track(SupervisorInstallerMilestones::INSTALL_SUPERVISOR),

            // Install supervisor
            'DEBIAN_FRONTEND=noninteractive apt-get install -y supervisor',

            $this->track(SupervisorInstallerMilestones::CREATE_DIRECTORIES),

            // Create directory structure for supervisor configs
            'mkdir -p /etc/supervisor/conf.d',
            'mkdir -p /var/log/supervisor',

            // Set permissions
            'chmod 755 /etc/supervisor/conf.d',
            'chmod 755 /var/log/supervisor',

            $this->track(SupervisorInstallerMilestones::CONFIGURE_SERVICE),

            // Ensure supervisor service is enabled and started
            'systemctl enable supervisor',
            'systemctl start supervisor',

            $this->track(SupervisorInstallerMilestones::VERIFY_INSTALL),

            // Verify supervisor is running
            'systemctl is-active --quiet supervisor || (echo "Supervisor service failed to start" && exit 1)',

            // Verify supervisorctl is working
            'supervisorctl version || (echo "Supervisorctl failed" && exit 1)',

            // Mark supervisor as active in database
            fn () => $this->server->update([
                'supervisor_status' => SupervisorStatus::Active,
                'supervisor_installed_at' => now(),
            ]),

            $this->track(SupervisorInstallerMilestones::COMPLETE),
        ];
    }
}
