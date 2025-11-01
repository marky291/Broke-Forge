<?php

namespace App\Packages\Services\Supervisor\Task;

use App\Models\Server;
use App\Models\ServerSupervisorTask;
use App\Packages\Core\Base\PackageRemover;
use App\Packages\Core\Base\ServerPackage;

/**
 * Supervisor Task Remover
 *
 * Removes supervisor task configurations from remote servers
 */
class SupervisorTaskRemover extends PackageRemover implements ServerPackage
{
    protected ServerSupervisorTask $task;

    public function __construct(Server $server, ServerSupervisorTask $task)
    {
        parent::__construct($server);
        $this->task = $task;
    }

    /**
     * Execute the task removal
     */
    public function execute(): void
    {
        $this->remove($this->commands());
    }

    protected function commands(): array
    {
        // Generate sanitized task name for filename and supervisor program name
        $sanitizedName = preg_replace('/[^a-zA-Z0-9-_]/', '_', $this->task->name);

        return [

            // Stop the task using sanitized name
            "supervisorctl stop {$sanitizedName} || true",

            // Remove configuration file
            "rm -f /etc/supervisor/conf.d/{$sanitizedName}.conf",

            // Reload supervisor to remove task
            'supervisorctl reread',
            'supervisorctl update',

        ];
    }
}
