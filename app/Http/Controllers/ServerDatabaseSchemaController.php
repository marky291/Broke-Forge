<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\Servers\StoreSchemaRequest;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Models\ServerDatabaseSchema;
use App\Packages\Services\Database\Schema\DatabaseSchemaInstallerJob;
use App\Packages\Services\Database\Schema\DatabaseSchemaRemoverJob;
use Illuminate\Http\RedirectResponse;

class ServerDatabaseSchemaController extends Controller
{
    /**
     * Store a new database schema
     */
    public function store(StoreSchemaRequest $request, Server $server, ServerDatabase $database): RedirectResponse
    {
        // Authorize user can update this server
        $this->authorize('update', $server);

        // Ensure database belongs to this server
        if ($database->server_id !== $server->id) {
            abort(404);
        }

        $validated = $request->validated();

        // ✅ CREATE SCHEMA RECORD FIRST with 'pending' status
        $schema = $database->schemas()->create([
            'name' => $validated['name'],
            'character_set' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'status' => TaskStatus::Pending->value,
        ]);

        // Create database user if username and password provided
        $user = null;
        if (! empty($validated['user']) && ! empty($validated['password'])) {
            $user = $database->users()->create([
                'username' => $validated['user'],
                'password' => $validated['password'],
                'host' => '%',
                'privileges' => 'all',
                'status' => TaskStatus::Pending->value,
            ]);

            // Link user to schema via pivot table
            $schema->users()->attach($user->id);
        }

        // ✅ THEN dispatch job with schema and optional user record
        DatabaseSchemaInstallerJob::dispatch($server, $schema, $user);

        return redirect()
            ->route('servers.databases.show', [$server, $database])
            ->with('success', 'Database schema creation started.');
    }

    /**
     * Remove a database schema
     */
    public function destroy(Server $server, ServerDatabase $database, ServerDatabaseSchema $schema): RedirectResponse
    {
        // Authorize user can delete this server
        $this->authorize('delete', $server);

        // Ensure database belongs to this server
        if ($database->server_id !== $server->id) {
            abort(404);
        }

        // Ensure schema belongs to this database
        if ($schema->server_database_id !== $database->id) {
            abort(404);
        }

        // Update schema record to removing status
        $schema->update(['status' => TaskStatus::Removing->value]);

        // Dispatch removal job with schema model
        DatabaseSchemaRemoverJob::dispatch($server, $schema);

        return redirect()
            ->route('servers.databases.show', [$server, $database])
            ->with('success', 'Database schema deletion started.');
    }
}
