<?php

namespace App\Packages\Services\Scheduler;

use App\Enums\SchedulerStatus;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageInstaller;
use App\Packages\Base\ServerPackage;
use App\Packages\Enums\CredentialType;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

/**
 * Server Scheduler Framework Installation Class
 *
 * Handles installation of the scheduler framework on remote servers.
 * This installs the base infrastructure needed to run scheduled tasks.
 */
class ServerSchedulerInstaller extends PackageInstaller implements ServerPackage
{
    public function packageName(): PackageName
    {
        return PackageName::Scheduler;
    }

    public function packageType(): PackageType
    {
        return PackageType::Scheduler;
    }

    public function milestones(): Milestones
    {
        return new ServerSchedulerInstallerMilestones;
    }

    public function credentialType(): CredentialType
    {
        return CredentialType::Root;
    }

    /**
     * Execute the scheduler framework installation
     */
    public function execute(): void
    {
        $this->install($this->commands());
    }

    protected function commands(): array
    {
        // Generate scheduler token for API authentication
        $schedulerToken = $this->server->generateSchedulerToken();

        return [
            $this->track(ServerSchedulerInstallerMilestones::PREPARE_SYSTEM),

            // Ensure system is up to date
            'DEBIAN_FRONTEND=noninteractive apt-get update -y',

            $this->track(ServerSchedulerInstallerMilestones::INSTALL_DEPENDENCIES),

            // Install required packages for scheduler (jq for JSON processing, bc for calculations)
            'DEBIAN_FRONTEND=noninteractive apt-get install -y jq bc curl cron',

            // Ensure cron service is running
            'systemctl enable cron',
            'systemctl start cron',

            $this->track(ServerSchedulerInstallerMilestones::CREATE_DIRECTORIES),

            // Create scheduler directory structure
            'mkdir -p /opt/brokeforge/scheduler',
            'mkdir -p /opt/brokeforge/scheduler/tasks',
            'mkdir -p /var/log/brokeforge',

            // Set permissions
            'chmod 755 /opt/brokeforge/scheduler',
            'chmod 755 /opt/brokeforge/scheduler/tasks',
            'chmod 755 /var/log/brokeforge',

            $this->track(ServerSchedulerInstallerMilestones::CONFIGURE_SCHEDULER),

            // Create log file
            'touch /var/log/brokeforge/scheduler.log',
            'chmod 644 /var/log/brokeforge/scheduler.log',

            // Create cron.d directory if it doesn't exist
            'mkdir -p /etc/cron.d',

            $this->track(ServerSchedulerInstallerMilestones::VERIFY_INSTALL),

            // Verify cron is running
            'systemctl is-active --quiet cron || (echo "Cron service failed to start" && exit 1)',

            // Verify directories exist
            'test -d /opt/brokeforge/scheduler || (echo "Scheduler directory creation failed" && exit 1)',

            // Mark scheduler as active in database
            fn () => $this->server->update([
                'scheduler_status' => SchedulerStatus::Active,
                'scheduler_installed_at' => now(),
            ]),

            $this->track(ServerSchedulerInstallerMilestones::COMPLETE),
        ];
    }
}
