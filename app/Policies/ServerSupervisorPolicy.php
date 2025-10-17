<?php

namespace App\Policies;

use App\Models\Server;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ServerSupervisorPolicy
{
    /**
     * Determine whether the user can view the supervisor page
     */
    public function view(User $user, Server $server): Response
    {
        // Check if user owns the server
        return $user->id === $server->user_id
            ? Response::allow()
            : Response::deny('You do not have permission to view this server\'s supervisor.');
    }

    /**
     * Determine whether the user can install supervisor
     */
    public function install(User $user, Server $server): Response
    {
        // Check server ownership
        if ($user->id !== $server->user_id) {
            return Response::deny('You do not have permission to install supervisor on this server.');
        }

        // Prevent installation if already installing/active
        if ($server->supervisor_status && in_array($server->supervisor_status->value, ['installing', 'active'])) {
            return Response::deny('Supervisor is already installed or being installed on this server.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can uninstall supervisor
     */
    public function uninstall(User $user, Server $server): Response
    {
        // Check server ownership
        if ($user->id !== $server->user_id) {
            return Response::deny('You do not have permission to uninstall supervisor on this server.');
        }

        // Can only uninstall if active
        if (! $server->supervisor_status || $server->supervisor_status->value !== 'active') {
            return Response::deny('Supervisor must be active to uninstall.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can create supervisor tasks
     */
    public function createTask(User $user, Server $server): Response
    {
        // Check server ownership
        if ($user->id !== $server->user_id) {
            return Response::deny('You do not have permission to create tasks on this server.');
        }

        // Supervisor must be active
        if (! $server->supervisor_status || $server->supervisor_status->value !== 'active') {
            return Response::deny('Supervisor must be active before creating tasks.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can update supervisor tasks
     */
    public function updateTask(User $user, Server $server): Response
    {
        // Check server ownership
        if ($user->id !== $server->user_id) {
            return Response::deny('You do not have permission to update tasks on this server.');
        }

        // Supervisor must be active
        if (! $server->supervisor_status || $server->supervisor_status->value !== 'active') {
            return Response::deny('Supervisor must be active to update tasks.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can delete supervisor tasks
     */
    public function deleteTask(User $user, Server $server): Response
    {
        // Check server ownership
        return $user->id === $server->user_id
            ? Response::allow()
            : Response::deny('You do not have permission to delete tasks on this server.');
    }

    /**
     * Determine whether the user can toggle supervisor tasks
     */
    public function toggleTask(User $user, Server $server): Response
    {
        // Check server ownership
        return $user->id === $server->user_id
            ? Response::allow()
            : Response::deny('You do not have permission to toggle tasks on this server.');
    }

    /**
     * Determine whether the user can restart supervisor tasks
     */
    public function restartTask(User $user, Server $server): Response
    {
        // Check server ownership
        return $user->id === $server->user_id
            ? Response::allow()
            : Response::deny('You do not have permission to restart tasks on this server.');
    }
}
