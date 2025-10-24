<?php

namespace App\Packages\Services\Scheduler;

use App\Packages\Base\PackageRemover;
use App\Packages\Base\ServerPackage;

/**
 * Server Scheduler Framework Removal Class
 *
 * Handles removal of scheduler framework from remote servers
 */
class ServerSchedulerRemover extends PackageRemover implements ServerPackage
{
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

            // Note: Individual tasks should be removed first via ServerScheduleTaskRemover
            // This is just cleanup for any remaining cron entries

            // Remove all brokeforge cron entries
            'rm -f /etc/cron.d/brokeforge-task-* >/dev/null 2>&1 || true',

            // Remove scheduler directory and scripts
            'rm -rf /opt/brokeforge/scheduler',

            // Remove log file
            'rm -f /var/log/brokeforge/scheduler.log',

            // Mark scheduler as uninstalled in database
            fn () => $this->server->update([
                'scheduler_status' => null,
                'scheduler_uninstalled_at' => now(),
            ]),

            // Note: We don't delete tasks/runs as they may be useful for historical reference
            // They will be cascade deleted when the server is deleted

        ];
    }
}
