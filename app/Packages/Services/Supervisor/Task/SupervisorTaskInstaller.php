<?php

namespace App\Packages\Services\Supervisor\Task;

use App\Models\Server;
use App\Models\ServerSupervisorTask;
use App\Packages\Base\PackageInstaller;
use App\Packages\Base\ServerPackage;

/**
 * Supervisor Task Installer
 *
 * Deploys individual supervisor task configurations to remote servers
 */
class SupervisorTaskInstaller extends PackageInstaller implements ServerPackage
{
    protected ServerSupervisorTask $task;

    public function __construct(Server $server, ServerSupervisorTask $task)
    {
        parent::__construct($server);
        $this->task = $task;
    }

    /**
     * Execute the task installation
     */
    public function execute(): void
    {
        $this->install($this->commands());
    }

    protected function commands(): array
    {
        // Generate sanitized task name for filename and supervisor program name
        $sanitizedName = preg_replace('/[^a-zA-Z0-9-_]/', '_', $this->task->name);

        // Generate config content from Blade template
        $configContent = view('supervisor.task', [
            'task' => $this->task,
        ])->render();

        return [

            // Deploy configuration file
            "cat > /etc/supervisor/conf.d/{$sanitizedName}.conf << 'EOF'\n{$configContent}\nEOF",

            // Set proper permissions
            "chmod 644 /etc/supervisor/conf.d/{$sanitizedName}.conf",

            // Reload supervisor to pick up new config
            'supervisorctl reread',
            'supervisorctl update',

            // Start the task using sanitized name
            "supervisorctl start {$sanitizedName} || true",

        ];
    }
}
