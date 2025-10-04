<?php

namespace App\Packages\Services\Scheduler;

use App\Enums\SchedulerStatus;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageRemover;
use App\Packages\Base\ServerPackage;
use App\Packages\Enums\CredentialType;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

/**
 * Server Scheduler Framework Removal Class
 *
 * Handles removal of scheduler framework from remote servers
 */
class ServerSchedulerRemover extends PackageRemover implements ServerPackage
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
        return new ServerSchedulerRemoverMilestones;
    }

    public function credentialType(): CredentialType
    {
        return CredentialType::Root;
    }

    /**
     * Execute the scheduler framework removal
     */
    public function execute(): void
    {
        $this->remove($this->commands());
    }

    protected function commands(): array
    {
        return [
            $this->track(ServerSchedulerRemoverMilestones::STOP_TASKS),

            // Note: Individual tasks should be removed first via ServerScheduleTaskRemover
            // This is just cleanup for any remaining cron entries

            $this->track(ServerSchedulerRemoverMilestones::REMOVE_CRON_ENTRIES),

            // Remove all brokeforge cron entries
            'rm -f /etc/cron.d/brokeforge-task-* >/dev/null 2>&1 || true',

            $this->track(ServerSchedulerRemoverMilestones::REMOVE_FILES),

            // Remove scheduler directory and scripts
            'rm -rf /opt/brokeforge/scheduler',

            // Remove log file
            'rm -f /var/log/brokeforge/scheduler.log',

            $this->track(ServerSchedulerRemoverMilestones::CLEANUP_DATABASE),

            // Mark scheduler as uninstalled in database
            fn () => $this->server->update([
                'scheduler_status' => SchedulerStatus::Uninstalled,
                'scheduler_uninstalled_at' => now(),
            ]),

            // Note: We don't delete tasks/runs as they may be useful for historical reference
            // They will be cascade deleted when the server is deleted

            $this->track(ServerSchedulerRemoverMilestones::COMPLETE),
        ];
    }
}
