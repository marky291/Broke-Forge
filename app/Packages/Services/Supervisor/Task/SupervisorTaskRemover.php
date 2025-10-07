<?php

namespace App\Packages\Services\Supervisor\Task;

use App\Models\Server;
use App\Models\ServerSupervisorTask;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageRemover;
use App\Packages\Base\ServerPackage;
use App\Packages\Enums\CredentialType;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

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

    public function packageName(): PackageName
    {
        return PackageName::SupervisorTask;
    }

    public function packageType(): PackageType
    {
        return PackageType::Supervisor;
    }

    public function milestones(): Milestones
    {
        return new SupervisorTaskRemoverMilestones;
    }

    public function credentialType(): CredentialType
    {
        return CredentialType::Root;
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
            $this->track(SupervisorTaskRemoverMilestones::STOP_TASK),

            // Stop the task using sanitized name
            "supervisorctl stop {$sanitizedName} || true",

            $this->track(SupervisorTaskRemoverMilestones::REMOVE_CONFIG),

            // Remove configuration file
            "rm -f /etc/supervisor/conf.d/{$sanitizedName}.conf",

            $this->track(SupervisorTaskRemoverMilestones::RELOAD_SUPERVISOR),

            // Reload supervisor to remove task
            'supervisorctl reread',
            'supervisorctl update',

            // Mark task as removed
            fn () => $this->task->update([
                'status' => 'inactive',
                'uninstalled_at' => now(),
            ]),

            $this->track(SupervisorTaskRemoverMilestones::COMPLETE),
        ];
    }
}
