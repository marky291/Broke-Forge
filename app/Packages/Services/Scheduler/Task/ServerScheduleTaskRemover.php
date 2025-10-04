<?php

namespace App\Packages\Services\Scheduler\Task;

use App\Models\Server;
use App\Models\ServerScheduledTask;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageRemover;
use App\Packages\Base\ServerPackage;
use App\Packages\Enums\CredentialType;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

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

    public function packageName(): PackageName
    {
        return PackageName::ScheduledTask;
    }

    public function packageType(): PackageType
    {
        return PackageType::Scheduler;
    }

    public function milestones(): Milestones
    {
        return new ServerScheduleTaskRemoverMilestones;
    }

    public function credentialType(): CredentialType
    {
        return CredentialType::Root;
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
            $this->track(ServerScheduleTaskRemoverMilestones::PREPARE_REMOVAL),

            // Verify scheduler is still installed
            'test -d /opt/brokeforge/scheduler/tasks || echo "Scheduler directory not found, continuing removal"',

            $this->track(ServerScheduleTaskRemoverMilestones::REMOVE_CRON_ENTRY),

            // Remove cron entry
            "rm -f /etc/cron.d/brokeforge-task-{$taskId}",

            $this->track(ServerScheduleTaskRemoverMilestones::REMOVE_WRAPPER_SCRIPT),

            // Remove wrapper script
            "rm -f /opt/brokeforge/scheduler/tasks/{$taskId}.sh",

            $this->track(ServerScheduleTaskRemoverMilestones::VERIFY_REMOVAL),

            // Verify cron entry is removed
            "test ! -f /etc/cron.d/brokeforge-task-{$taskId} || (echo 'Cron entry removal failed' && exit 1)",

            // Verify wrapper script is removed
            "test ! -f /opt/brokeforge/scheduler/tasks/{$taskId}.sh || (echo 'Wrapper script removal failed' && exit 1)",

            $this->track(ServerScheduleTaskRemoverMilestones::COMPLETE),
        ];
    }
}
