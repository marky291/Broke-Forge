<?php

namespace App\Policies;

use App\Models\Server;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ServerSchedulerPolicy
{
    /**
     * Determine whether the user can view the scheduler.
     */
    public function view(User $user, Server $server): Response
    {
        // Check if user owns the server
        return $user->id === $server->user_id
            ? Response::allow()
            : Response::deny('You do not have permission to view this server\'s scheduler.');
    }

    /**
     * Determine whether the user can install scheduler.
     */
    public function install(User $user, Server $server): Response
    {
        // Check server ownership
        if ($user->id !== $server->user_id) {
            return Response::deny('You do not have permission to install scheduler on this server.');
        }

        // Prevent installation if already installing/active
        if ($server->scheduler_status && in_array($server->scheduler_status->value, ['installing', 'active'])) {
            return Response::deny('Scheduler is already installed or being installed on this server.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can uninstall scheduler.
     */
    public function uninstall(User $user, Server $server): Response
    {
        // Check server ownership
        if ($user->id !== $server->user_id) {
            return Response::deny('You do not have permission to uninstall scheduler on this server.');
        }

        // Can only uninstall if active
        if (! $server->scheduler_status || $server->scheduler_status->value !== 'active') {
            return Response::deny('Scheduler must be active to uninstall.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can create scheduled tasks.
     */
    public function createTask(User $user, Server $server): Response
    {
        // Check server ownership
        if ($user->id !== $server->user_id) {
            return Response::deny('You do not have permission to create tasks on this server.');
        }

        // Scheduler must be active
        if (! $server->schedulerIsActive()) {
            return Response::deny('Scheduler must be active before creating tasks.');
        }

        // Check max tasks limit
        $maxTasks = config('scheduler.max_tasks_per_server', 50);
        if ($server->scheduledTasks()->count() >= $maxTasks) {
            return Response::deny("Maximum number of scheduled tasks ({$maxTasks}) reached for this server.");
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can update scheduled tasks.
     */
    public function updateTask(User $user, Server $server): Response
    {
        // Check server ownership
        if ($user->id !== $server->user_id) {
            return Response::deny('You do not have permission to update tasks on this server.');
        }

        // Scheduler must be active
        if (! $server->schedulerIsActive()) {
            return Response::deny('Scheduler must be active to update tasks.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can delete scheduled tasks.
     */
    public function deleteTask(User $user, Server $server): Response
    {
        // Check server ownership
        return $user->id === $server->user_id
            ? Response::allow()
            : Response::deny('You do not have permission to delete tasks on this server.');
    }

    /**
     * Determine whether the user can manually run scheduled tasks.
     */
    public function runTask(User $user, Server $server): Response
    {
        // Check server ownership
        if ($user->id !== $server->user_id) {
            return Response::deny('You do not have permission to run tasks on this server.');
        }

        // Scheduler must be active
        if (! $server->schedulerIsActive()) {
            return Response::deny('Scheduler must be active to run tasks.');
        }

        return Response::allow();
    }
}
