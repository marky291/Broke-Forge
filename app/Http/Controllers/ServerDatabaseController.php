<?php

namespace App\Http\Controllers;

use App\Enums\DatabaseEngine;
use App\Enums\TaskStatus;
use App\Http\Controllers\Concerns\PreparesSiteData;
use App\Http\Requests\Servers\InstallDatabaseRequest;
use App\Http\Resources\ServerResource;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Packages\Services\Database\MariaDB\MariaDbInstallerJob;
use App\Packages\Services\Database\MariaDB\MariaDbRemoverJob;
use App\Packages\Services\Database\MariaDB\MariaDbUpdaterJob;
use App\Packages\Services\Database\MySQL\MySqlInstallerJob;
use App\Packages\Services\Database\MySQL\MySqlRemoverJob;
use App\Packages\Services\Database\MySQL\MySqlUpdaterJob;
use App\Packages\Services\Database\PostgreSQL\PostgreSqlInstallerJob;
use App\Packages\Services\Database\PostgreSQL\PostgreSqlRemoverJob;
use App\Packages\Services\Database\PostgreSQL\PostgreSqlUpdaterJob;
use App\Packages\Services\Database\Redis\RedisInstallerJob;
use App\Packages\Services\Database\Redis\RedisRemoverJob;
use App\Packages\Services\Database\Redis\RedisUpdaterJob;
use App\Services\DatabaseConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServerDatabaseController extends Controller
{
    use PreparesSiteData;

    public function __construct(
        private readonly DatabaseConfigurationService $databaseConfig
    ) {}

    public function services(Server $server): Response
    {
        // Authorize user can view this server
        $this->authorize('view', $server);

        return Inertia::render('servers/services', [
            'server' => new ServerResource($server),
            'availableDatabases' => $this->databaseConfig->getAvailableDatabases($server->os_codename),
            'availableCacheQueue' => $this->databaseConfig->getAvailableCacheQueue($server->os_codename),
        ]);
    }

    public function show(Server $server, ServerDatabase $database): Response
    {
        // Authorize user can view this server
        $this->authorize('view', $server);

        // Ensure database belongs to this server
        if ($database->server_id !== $server->id) {
            abort(404);
        }

        $databaseType = $database->engine instanceof DatabaseEngine
            ? $database->engine
            : DatabaseEngine::from($database->engine);

        // Only allow detail pages for MySQL, MariaDB, and PostgreSQL
        if (! in_array($databaseType, [DatabaseEngine::MySQL, DatabaseEngine::MariaDB, DatabaseEngine::PostgreSQL])) {
            abort(404, 'This database type does not have a detail page.');
        }

        // Get schemas and managed users
        $schemas = $database->schemas()->latest()->get()->map(fn ($schema) => [
            'id' => $schema->id,
            'name' => $schema->name,
            'character_set' => $schema->character_set,
            'collation' => $schema->collation,
            'status' => $schema->status?->value ?? $schema->status,
            'error_log' => $schema->error_log,
            'created_at' => $schema->created_at?->toISOString(),
        ])->toArray();

        $managedUsers = $database->users()->latest()->with('schemas')->get()->map(fn ($user) => [
            'id' => $user->id,
            'is_root' => $user->is_root,
            'username' => $user->username,
            'host' => $user->host,
            'privileges' => $user->privileges,
            'status' => $user->status?->value ?? $user->status,
            'error_log' => $user->error_log,
            'update_status' => $user->update_status?->value ?? $user->update_status,
            'update_error_log' => $user->update_error_log,
            'schemas' => $user->schemas->map(fn ($schema) => [
                'id' => $schema->id,
                'name' => $schema->name,
            ])->toArray(),
            'created_at' => $user->created_at?->toISOString(),
        ])->toArray();

        return Inertia::render('servers/database-details', [
            'server' => new ServerResource($server),
            'database' => [
                'id' => $database->id,
                'name' => $database->name,
                'engine' => $database->engine?->value ?? $database->engine,
                'version' => $database->version,
                'port' => $database->port,
                'status' => $database->status?->value ?? $database->status,
                'error_log' => $database->error_log,
                'created_at' => $database->created_at?->toISOString(),
                'updated_at' => $database->updated_at?->toISOString(),
            ],
            'schemas' => $schemas,
            'managedUsers' => $managedUsers,
        ]);
    }

    public function store(InstallDatabaseRequest $request, Server $server): RedirectResponse
    {
        // Authorize user can update this server
        $this->authorize('update', $server);

        $validated = $request->validated();
        $databaseType = DatabaseEngine::from($validated['engine']);

        // Get next available port (uses requested port if available, otherwise auto-assigns)
        $port = $this->databaseConfig->getNextAvailablePort(
            $server,
            $databaseType,
            $validated['port'] ?? null
        );

        // Use engine name as default for Redis (cache/queue services don't need custom names)
        $name = $validated['name'] ?? $databaseType->value;

        // ✅ CREATE RECORD FIRST with 'pending' status
        $database = $server->databases()->create([
            'name' => $name,
            'engine' => $validated['engine'],
            'storage_type' => $databaseType->storageType(),
            'version' => $validated['version'],
            'port' => $port,
            'status' => TaskStatus::Pending->value,
            'root_password' => $validated['root_password'] ?? null,
        ]);

        // ✅ THEN dispatch job with database record
        switch ($databaseType) {
            case DatabaseEngine::MariaDB:
                MariaDbInstallerJob::dispatch($server, $database);
                break;
            case DatabaseEngine::MySQL:
                MySqlInstallerJob::dispatch($server, $database);
                break;
            case DatabaseEngine::PostgreSQL:
                PostgreSqlInstallerJob::dispatch($server, $database);
                break;
            case DatabaseEngine::Redis:
                RedisInstallerJob::dispatch($server, $database);
                break;
            default:
                $database->update(['status' => TaskStatus::Failed->value]);

                return redirect()
                    ->route('servers.services', $server)
                    ->with('error', 'Selected database type is not supported yet.');
        }

        return redirect()
            ->route('servers.services', $server)
            ->with('success', 'Database installation started.');
    }

    public function update(Request $request, Server $server, ServerDatabase $database): RedirectResponse
    {
        // Authorize user can update this server
        $this->authorize('update', $server);

        // Ensure database belongs to this server
        if ($database->server_id !== $server->id) {
            abort(404);
        }

        $validated = $request->validate([
            'version' => 'required|string',
        ]);

        if ($database->status === TaskStatus::Installing ||
            $database->status === TaskStatus::Removing ||
            $database->status === TaskStatus::Updating) {
            return redirect()
                ->route('servers.services', $server)
                ->with('error', 'Database is currently being modified. Please wait.');
        }

        $databaseType = $database->engine instanceof DatabaseEngine
            ? $database->engine
            : DatabaseEngine::from($database->engine);

        // Store new version on database record and set status to updating
        $database->update([
            'version' => $validated['version'],
            'status' => TaskStatus::Updating,
        ]);

        // Dispatch updater job with database model
        switch ($databaseType) {
            case DatabaseEngine::MariaDB:
                MariaDbUpdaterJob::dispatch($server, $database);
                break;
            case DatabaseEngine::MySQL:
                MySqlUpdaterJob::dispatch($server, $database);
                break;
            case DatabaseEngine::PostgreSQL:
                PostgreSqlUpdaterJob::dispatch($server, $database);
                break;
            case DatabaseEngine::Redis:
                RedisUpdaterJob::dispatch($server, $database);
                break;
            default:
                $database->update(['status' => TaskStatus::Failed->value]);

                return redirect()
                    ->route('servers.services', $server)
                    ->with('error', 'Selected database type cannot be updated automatically yet.');
        }

        return redirect()
            ->route('servers.services', $server)
            ->with('success', 'Database update started.');
    }

    public function destroy(Server $server, ServerDatabase $database): RedirectResponse
    {
        // Authorize user can delete this server
        $this->authorize('delete', $server);

        // Ensure database belongs to this server
        if ($database->server_id !== $server->id) {
            abort(404);
        }

        // Check if any sites are using this database
        $sitesUsingDatabase = $database->sites()->get();
        if ($sitesUsingDatabase->isNotEmpty()) {
            $sitesList = $sitesUsingDatabase->pluck('domain')->join(', ');
            $count = $sitesUsingDatabase->count();
            $siteWord = $count === 1 ? 'site' : 'sites';

            $databaseTypeName = $database->engine instanceof DatabaseEngine ? $database->engine->value : $database->engine;

            return redirect()
                ->route('servers.services', $server)
                ->with('error', "Cannot uninstall {$databaseTypeName} database. {$count} {$siteWord} currently depend on it: {$sitesList}. To proceed, either delete these sites or migrate them to a different database.");
        }

        // Update database record to pending status
        $database->update(['status' => TaskStatus::Pending->value]);

        $databaseType = $database->engine instanceof DatabaseEngine
            ? $database->engine
            : DatabaseEngine::from($database->engine);

        // Dispatch removal job with database model
        switch ($databaseType) {
            case DatabaseEngine::MariaDB:
                MariaDbRemoverJob::dispatch($server, $database);
                break;
            case DatabaseEngine::MySQL:
                MySqlRemoverJob::dispatch($server, $database);
                break;
            case DatabaseEngine::PostgreSQL:
                PostgreSqlRemoverJob::dispatch($server, $database);
                break;
            case DatabaseEngine::Redis:
                RedisRemoverJob::dispatch($server, $database);
                break;
            default:
                $database->update(['status' => TaskStatus::Failed->value]);

                return redirect()
                    ->route('servers.services', $server)
                    ->with('error', 'Selected database type cannot be uninstalled automatically yet.');
        }

        return redirect()
            ->route('servers.services', $server)
            ->with('success', 'Database uninstallation started.');
    }

    /**
     * Retry a failed database installation
     */
    public function retry(Server $server, ServerDatabase $database): RedirectResponse
    {
        $this->authorize('update', $server);

        // Ensure database belongs to this server
        if ($database->server_id !== $server->id) {
            abort(404);
        }

        // Only allow retry for failed databases
        if ($database->status !== TaskStatus::Failed) {
            return back()->with('error', 'Only failed databases can be retried');
        }

        // Audit log
        \Illuminate\Support\Facades\Log::info('Database installation retry initiated', [
            'user_id' => auth()->id(),
            'server_id' => $server->id,
            'database_id' => $database->id,
            'database_engine' => $database->engine?->value ?? $database->engine,
            'database_version' => $database->version,
            'ip_address' => request()->ip(),
        ]);

        // Reset status to 'pending' and clear error log
        // Model events will broadcast automatically via Reverb
        $database->update([
            'status' => TaskStatus::Pending,
            'error_log' => null,
        ]);

        $databaseType = $database->engine instanceof DatabaseEngine
            ? $database->engine
            : DatabaseEngine::from($database->engine);

        // Re-dispatch installer job based on database type
        switch ($databaseType) {
            case DatabaseEngine::MariaDB:
                MariaDbInstallerJob::dispatch($server, $database);
                break;
            case DatabaseEngine::MySQL:
                MySqlInstallerJob::dispatch($server, $database);
                break;
            case DatabaseEngine::PostgreSQL:
                PostgreSqlInstallerJob::dispatch($server, $database);
                break;
            case DatabaseEngine::Redis:
                RedisInstallerJob::dispatch($server, $database);
                break;
            default:
                $database->update(['status' => TaskStatus::Failed->value]);

                return back()->with('error', 'Selected database type is not supported yet.');
        }

        // No redirect needed - frontend will update via Reverb
        return back();
    }
}
