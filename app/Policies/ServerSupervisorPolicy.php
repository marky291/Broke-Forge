<?php

namespace App\Policies;

use App\Models\Server;
use App\Models\User;

class ServerSupervisorPolicy
{
    /**
     * Determine whether the user can view the supervisor page
     */
    public function view(User $user, Server $server): bool
    {
        return $user->id === $server->user_id;
    }

    /**
     * Determine whether the user can install supervisor
     */
    public function install(User $user, Server $server): bool
    {
        return $user->id === $server->user_id;
    }

    /**
     * Determine whether the user can uninstall supervisor
     */
    public function uninstall(User $user, Server $server): bool
    {
        return $user->id === $server->user_id;
    }

    /**
     * Determine whether the user can create supervisor tasks
     */
    public function createTask(User $user, Server $server): bool
    {
        return $user->id === $server->user_id;
    }

    /**
     * Determine whether the user can update supervisor tasks
     */
    public function updateTask(User $user, Server $server): bool
    {
        return $user->id === $server->user_id;
    }

    /**
     * Determine whether the user can delete supervisor tasks
     */
    public function deleteTask(User $user, Server $server): bool
    {
        return $user->id === $server->user_id;
    }

    /**
     * Determine whether the user can toggle supervisor tasks
     */
    public function toggleTask(User $user, Server $server): bool
    {
        return $user->id === $server->user_id;
    }

    /**
     * Determine whether the user can restart supervisor tasks
     */
    public function restartTask(User $user, Server $server): bool
    {
        return $user->id === $server->user_id;
    }
}
