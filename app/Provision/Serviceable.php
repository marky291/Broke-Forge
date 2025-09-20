<?php

namespace App\Provision;

use App\Models\ProvisionEvent;
use App\Models\Server;
use App\Provision\Enums\ExecutableUser;
use Closure;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Spatie\Ssh\Ssh;

abstract class Serviceable
{
    protected Server $server;

    /**
     * Get the total steps that have been run
     *
     * @var int
     */
    protected int $milestoneStep = 1;

    /**
     * Get the service type identifier (e.g., 'mysql', 'nginx', 'php')
     *
     * This is used by the InstallMilestone tracking system to determine the source
     * for ProvisionEvent records (e.g., 'service:mysql', 'service:nginx')
     */
    abstract protected function serviceType(): string;

    /**
     * Milestones that are used in the provision.
     *
     * Returns the FQCN of an enum or constants class.
     */
    abstract protected function milestones(): Milestones;

    /**
     * The user to execute the command list on remote host.
     *
     * Uses an ENUM CLASS.
     *
     * @return ExecutableUser
     */
    abstract protected function executableUser(): ExecutableUser;

    /**
     * Get the actionable name based on inherited class.
     *
     * @return string
     */
    protected function actionableName(): string
    {
        if ($this instanceof RemovableService) {
            return 'Deprovisioning';
        }

        return 'Provisioning';
    }

    /**
     * Count the total milestone steps in the installation
     * provision commands.
     */
    protected function countMilestones(): int
    {
        return $this->milestones()->countLabels();
    }

    /**
     * Track provision milestone and persist to database
     *
     * Creates a closure that logs and persists the provision event
     * when executed by the SSH command callback system
     */
    protected function track(string $milestone): \Closure
    {
        $service = $this->serviceType();
        $milestoneStep = $this->milestoneStep++;
        $totalMileSteps = $this->countMilestones();

        return function () use ($milestone, $service, $milestoneStep, $totalMileSteps) {
            // Log the milestone for debugging purposes
            Log::info("{$this->actionableName()} milestone: {$milestone} (step {$milestoneStep}/{$totalMileSteps}) for service {$service}", [
                'server_id' => $this->server->id,
                'service' => $service,
            ]);

            // Persist the provision event to database for frontend tracking
            ProvisionEvent::create([
                'server_id' => $this->server->id,
                'service_type' => $service,
                'provision_type' => $this->actionableName() == 'Provisioning' ? 'install' : 'remove',
                'milestone' => $milestone,
                'current_step' => $milestoneStep,
                'total_steps' => $totalMileSteps,
                'details' => [
                    'server_ip' => $this->server->public_ip,
                    'server_name' => $this->server->vanity_name,
                    'timestamp' => now()->toISOString(),
                ],
            ]);
        };
    }

    protected function sendCommandsToRemote(array $commandList): void
    {
        $sshUser = $this->executableUser() == ExecutableUser::RootUser ? $this->server->ssh_root_user : $this->server->ssh_app_user;

        foreach ($commandList as $command) {
            // Execute closures (milestones)
            if ($command instanceof Closure) {
                $command();

                continue;
            }

            // Execute SSH commands
            $process = Ssh::create($sshUser, $this->server->public_ip)
                ->disableStrictHostKeyChecking()
                ->execute($command);

            if (! $process->isSuccessful()) {
                Log::error("Failed to execute command $command", ['server' => $this->server]);
                throw new \RuntimeException("Command failed: $command");
            }
        }
    }
}
