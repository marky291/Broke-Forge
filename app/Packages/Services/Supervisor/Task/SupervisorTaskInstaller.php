<?php

namespace App\Packages\Services\Supervisor\Task;

use App\Models\Server;
use App\Models\ServerSupervisorTask;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageInstaller;
use App\Packages\Base\ServerPackage;
use App\Packages\Enums\CredentialType;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

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
        return new SupervisorTaskInstallerMilestones;
    }

    public function credentialType(): CredentialType
    {
        return CredentialType::Root;
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
        // Generate sanitized task name for filename
        $taskFileName = preg_replace('/[^a-zA-Z0-9-_]/', '_', $this->task->name);

        // Generate config content from Blade template
        $configContent = view('supervisor.task', [
            'task' => $this->task,
        ])->render();

        return [
            $this->track(SupervisorTaskInstallerMilestones::GENERATE_CONFIG),

            $this->track(SupervisorTaskInstallerMilestones::DEPLOY_CONFIG),

            // Deploy configuration file
            "cat > /etc/supervisor/conf.d/{$taskFileName}.conf << 'EOF'\n{$configContent}\nEOF",

            // Set proper permissions
            "chmod 644 /etc/supervisor/conf.d/{$taskFileName}.conf",

            $this->track(SupervisorTaskInstallerMilestones::RELOAD_SUPERVISOR),

            // Reload supervisor to pick up new config
            'supervisorctl reread',
            'supervisorctl update',

            // Start the task
            "supervisorctl start {$this->task->name} || true",

            // Mark task as installed and active
            fn () => $this->task->update([
                'status' => 'active',
                'installed_at' => now(),
            ]),

            $this->track(SupervisorTaskInstallerMilestones::COMPLETE),
        ];
    }
}
