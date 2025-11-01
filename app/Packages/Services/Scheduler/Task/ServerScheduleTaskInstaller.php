<?php

namespace App\Packages\Services\Scheduler\Task;

use App\Models\Server;
use App\Models\ServerScheduledTask;
use App\Packages\Core\Base\PackageInstaller;
use App\Packages\Core\Base\ServerPackage;

/**
 * Server Scheduled Task Installation Class
 *
 * Handles installation of individual scheduled tasks on remote servers.
 * Accepts an existing ServerScheduledTask model only (task must be created first).
 */
class ServerScheduleTaskInstaller extends PackageInstaller implements ServerPackage
{
    protected ServerScheduledTask $task;

    public function __construct(Server $server, ServerScheduledTask $task)
    {
        parent::__construct($server);

        $this->task = $task;
    }

    /**
     * Execute the task installation
     * Installs existing task on remote server
     */
    public function execute(): void
    {
        // Install on remote server
        $this->install($this->commands());
    }

    protected function commands(): array
    {
        $appUrl = config('app.url');
        $taskId = $this->task->id;
        $serverId = $this->server->id;
        $schedulerToken = $this->server->scheduler_token;
        $command = $this->task->command;
        $timeout = $this->task->timeout;
        $cronExpression = $this->task->getCronExpression();

        // Generate the task wrapper script content
        $wrapperScript = view('scheduler.task-wrapper', [
            'appUrl' => $appUrl,
            'serverId' => $serverId,
            'taskId' => $taskId,
            'schedulerToken' => $schedulerToken,
            'command' => $command,
            'timeout' => $timeout,
        ])->render();

        // Generate the cron entry content
        $cronEntry = view('scheduler.cron-entry', [
            'task' => $this->task,
            'cronExpression' => $cronExpression,
        ])->render();

        return [

            // Ensure scheduler directory exists
            'test -d /opt/brokeforge/scheduler/tasks || (echo "Scheduler not installed" && exit 1)',

            // Create the task wrapper script
            "cat > /opt/brokeforge/scheduler/tasks/{$taskId}.sh << 'EOF'\n{$wrapperScript}\nEOF",

            // Make the script executable
            "chmod +x /opt/brokeforge/scheduler/tasks/{$taskId}.sh",

            // Create cron entry in /etc/cron.d/
            "cat > /etc/cron.d/brokeforge-task-{$taskId} << 'EOF'\n{$cronEntry}\nEOF",

            // Set proper permissions for cron file
            "chmod 644 /etc/cron.d/brokeforge-task-{$taskId}",

            // Verify cron file exists
            "test -f /etc/cron.d/brokeforge-task-{$taskId} || (echo 'Cron entry creation failed' && exit 1)",

            // Verify wrapper script exists
            "test -f /opt/brokeforge/scheduler/tasks/{$taskId}.sh || (echo 'Wrapper script creation failed' && exit 1)",

        ];
    }
}
