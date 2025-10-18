<?php

namespace App\Packages\Services\Scheduler\Task;

use App\Models\Server;
use App\Models\ServerScheduledTask;
use App\Packages\Base\PackageRemover;
use App\Packages\Base\ServerPackage;

/**
 * Server Scheduled Task Removal Class
 *
 * Handles removal of individual scheduled tasks from remote servers.
 * Removes cron entry and wrapper script for the specific task.
 */
class ServerScheduleTaskRemover extends PackageRemover implements ServerPackage
{
    protected ServerScheduledTask $task;

    public function __construct(Server $server, ServerScheduledTask $task)
    {
        parent::__construct($server);
        $this->task = $task;
    }

    /**
     * Execute the task removal and delete task from database on success
     */
    public function execute(): void
    {
        $this->remove($this->commands());

        // Only delete the task if remote removal succeeded
        $this->task->delete();
    }

    protected function commands(): array
    {
        $taskId = $this->task->id;

        return [

            // Verify scheduler is still installed
            'test -d /opt/brokeforge/scheduler/tasks || echo "Scheduler directory not found, continuing removal"',

            // Remove cron entry
            "rm -f /etc/cron.d/brokeforge-task-{$taskId}",

            // Remove wrapper script
            "rm -f /opt/brokeforge/scheduler/tasks/{$taskId}.sh",

            // Verify cron entry is removed
            "test ! -f /etc/cron.d/brokeforge-task-{$taskId} || (echo 'Cron entry removal failed' && exit 1)",

            // Verify wrapper script is removed
            "test ! -f /opt/brokeforge/scheduler/tasks/{$taskId}.sh || (echo 'Wrapper script removal failed' && exit 1)",

        ];
    }
}
