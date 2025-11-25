<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\Servers\StoreUserRequest;
use App\Http\Requests\Servers\UpdateUserRequest;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Models\ServerDatabaseUser;
use App\Packages\Services\Database\User\DatabaseUserInstallerJob;
use App\Packages\Services\Database\User\DatabaseUserRemoverJob;
use App\Packages\Services\Database\User\DatabaseUserUpdaterJob;
use Illuminate\Http\RedirectResponse;

class ServerDatabaseUserController extends Controller
{
    /**
     * Store a new database user
     */
    public function store(StoreUserRequest $request, Server $server, ServerDatabase $database): RedirectResponse
    {
        // Authorize user can update this server
        $this->authorize('update', $server);

        // Ensure database belongs to this server
        if ($database->server_id !== $server->id) {
            abort(404);
        }

        $validated = $request->validated();

        // ✅ CREATE RECORD FIRST with 'pending' status
        $user = $database->users()->create([
            'username' => $validated['username'],
            'password' => $validated['password'],
            'host' => $validated['host'] ?? '%',
            'privileges' => $validated['privileges'],
            'status' => TaskStatus::Pending->value,
        ]);

        // Attach schemas to user
        if (! empty($validated['schema_ids'])) {
            $user->schemas()->attach($validated['schema_ids']);
        }

        // ✅ THEN dispatch job with user record
        DatabaseUserInstallerJob::dispatch($server, $user);

        return redirect()
            ->route('servers.databases.show', [$server, $database])
            ->with('success', 'Database user creation started.');
    }

    /**
     * Update an existing database user
     */
    public function update(UpdateUserRequest $request, Server $server, ServerDatabase $database, ServerDatabaseUser $user): RedirectResponse
    {
        // Authorize user can update this server
        $this->authorize('update', $server);

        // Ensure database belongs to this server
        if ($database->server_id !== $server->id) {
            abort(404);
        }

        // Ensure user belongs to this database
        if ($user->server_database_id !== $database->id) {
            abort(404);
        }

        // Prevent editing root user
        if ($user->is_root) {
            return redirect()
                ->route('servers.databases.show', [$server, $database])
                ->with('error', 'Root user cannot be modified.');
        }

        $validated = $request->validated();

        // Update user record and set update_status to pending
        $user->update([
            'password' => $validated['password'] ?? $user->password,
            'privileges' => $validated['privileges'] ?? $user->privileges,
            'update_status' => TaskStatus::Pending,
        ]);

        // Sync schemas
        if (isset($validated['schema_ids'])) {
            $user->schemas()->sync($validated['schema_ids']);
        }

        // Dispatch updater job with user model
        DatabaseUserUpdaterJob::dispatch($server, $user);

        return redirect()
            ->route('servers.databases.show', [$server, $database])
            ->with('success', 'Database user update started.');
    }

    /**
     * Remove a database user
     */
    public function destroy(Server $server, ServerDatabase $database, ServerDatabaseUser $user): RedirectResponse
    {
        // Authorize user can delete this server
        $this->authorize('delete', $server);

        // Ensure database belongs to this server
        if ($database->server_id !== $server->id) {
            abort(404);
        }

        // Ensure user belongs to this database
        if ($user->server_database_id !== $database->id) {
            abort(404);
        }

        // Prevent deleting root user
        if ($user->is_root) {
            return redirect()
                ->route('servers.databases.show', [$server, $database])
                ->with('error', 'Root user cannot be deleted.');
        }

        // Update user record to removing status
        $user->update(['status' => TaskStatus::Removing->value]);

        // Dispatch removal job with user model
        DatabaseUserRemoverJob::dispatch($server, $user);

        return redirect()
            ->route('servers.databases.show', [$server, $database])
            ->with('success', 'Database user deletion started.');
    }

    /**
     * Retry a failed database user update
     */
    public function retry(Server $server, ServerDatabase $database, ServerDatabaseUser $user): RedirectResponse
    {
        // Authorize user can update this server
        $this->authorize('update', $server);

        // Ensure database belongs to this server
        if ($database->server_id !== $server->id) {
            abort(404);
        }

        // Ensure user belongs to this database
        if ($user->server_database_id !== $database->id) {
            abort(404);
        }

        // Prevent editing root user
        if ($user->is_root) {
            return back()->with('error', 'Root user cannot be modified.');
        }

        // Only allow retry for failed updates
        if ($user->update_status !== TaskStatus::Failed) {
            return back()->with('error', 'Only failed user updates can be retried.');
        }

        // Reset update_status to pending and clear error log
        $user->update([
            'update_status' => TaskStatus::Pending,
            'update_error_log' => null,
        ]);

        // Re-dispatch updater job
        DatabaseUserUpdaterJob::dispatch($server, $user);

        return back();
    }

    /**
     * Cancel a pending or failed database user update
     */
    public function cancelUpdate(Server $server, ServerDatabase $database, ServerDatabaseUser $user): RedirectResponse
    {
        // Authorize user can update this server
        $this->authorize('update', $server);

        // Ensure database belongs to this server
        if ($database->server_id !== $server->id) {
            abort(404);
        }

        // Ensure user belongs to this database
        if ($user->server_database_id !== $database->id) {
            abort(404);
        }

        // Prevent editing root user
        if ($user->is_root) {
            return back()->with('error', 'Root user cannot be modified.');
        }

        // Clear update status and error log
        $user->update([
            'update_status' => null,
            'update_error_log' => null,
        ]);

        return back()->with('success', 'Update cancelled.');
    }
}
