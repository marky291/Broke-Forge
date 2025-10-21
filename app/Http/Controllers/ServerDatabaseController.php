<?php

namespace App\Http\Controllers;

use App\Enums\DatabaseStatus;
use App\Enums\DatabaseType;
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

    public function store(InstallDatabaseRequest $request, Server $server): RedirectResponse
    {
        // Authorize user can update this server
        $this->authorize('update', $server);

        $validated = $request->validated();
        $databaseType = DatabaseType::from($validated['type']);

        // Get next available port (uses requested port if available, otherwise auto-assigns)
        $port = $this->databaseConfig->getNextAvailablePort(
            $server,
            $databaseType,
            $validated['port'] ?? null
        );

        // ✅ CREATE RECORD FIRST with 'pending' status
        $database = $server->databases()->create([
            'name' => $validated['name'] ?? $validated['type'],
            'type' => $validated['type'],
            'version' => $validated['version'],
            'port' => $port,
            'status' => DatabaseStatus::Pending->value,
            'root_password' => $validated['root_password'],
        ]);

        // ✅ THEN dispatch job with database record ID
        switch ($databaseType) {
            case DatabaseType::MariaDB:
                MariaDbInstallerJob::dispatch($server, $database->id);
                break;
            case DatabaseType::MySQL:
                MySqlInstallerJob::dispatch($server, $database->id);
                break;
            case DatabaseType::PostgreSQL:
                PostgreSqlInstallerJob::dispatch($server, $database->id);
                break;
            case DatabaseType::Redis:
                RedisInstallerJob::dispatch($server, $database->id);
                break;
            default:
                $database->update(['status' => DatabaseStatus::Failed->value]);

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

        if ($database->status === DatabaseStatus::Installing ||
            $database->status === DatabaseStatus::Uninstalling ||
            $database->status === DatabaseStatus::Updating) {
            return redirect()
                ->route('servers.services', $server)
                ->with('error', 'Database is currently being modified. Please wait.');
        }

        $databaseType = $database->type instanceof DatabaseType
            ? $database->type
            : DatabaseType::from($database->type);

        // Store new version on database record and set status to updating
        $database->update([
            'version' => $validated['version'],
            'status' => DatabaseStatus::Updating,
        ]);

        // Dispatch updater job with database ID
        switch ($databaseType) {
            case DatabaseType::MariaDB:
                MariaDbUpdaterJob::dispatch($server, $database->id);
                break;
            case DatabaseType::MySQL:
                MySqlUpdaterJob::dispatch($server, $database->id);
                break;
            case DatabaseType::PostgreSQL:
                PostgreSqlUpdaterJob::dispatch($server, $database->id);
                break;
            case DatabaseType::Redis:
                RedisUpdaterJob::dispatch($server, $database->id);
                break;
            default:
                $database->update(['status' => DatabaseStatus::Failed->value]);

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

        // Update database record to uninstalling status
        $database->update(['status' => DatabaseStatus::Uninstalling->value]);

        $databaseType = $database->type instanceof DatabaseType
            ? $database->type
            : DatabaseType::from($database->type);

        // Dispatch removal job with database record ID
        switch ($databaseType) {
            case DatabaseType::MariaDB:
                MariaDbRemoverJob::dispatch($server, $database->id);
                break;
            case DatabaseType::MySQL:
                MySqlRemoverJob::dispatch($server, $database->id);
                break;
            case DatabaseType::PostgreSQL:
                PostgreSqlRemoverJob::dispatch($server, $database->id);
                break;
            case DatabaseType::Redis:
                RedisRemoverJob::dispatch($server, $database->id);
                break;
            default:
                $database->update(['status' => DatabaseStatus::Failed->value]);

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
        if ($database->status !== DatabaseStatus::Failed) {
            return back()->with('error', 'Only failed databases can be retried');
        }

        // Audit log
        \Illuminate\Support\Facades\Log::info('Database installation retry initiated', [
            'user_id' => auth()->id(),
            'server_id' => $server->id,
            'database_id' => $database->id,
            'database_type' => $database->type,
            'database_version' => $database->version,
            'ip_address' => request()->ip(),
        ]);

        // Reset status to 'pending' and clear error log
        // Model events will broadcast automatically via Reverb
        $database->update([
            'status' => DatabaseStatus::Pending,
            'error_log' => null,
        ]);

        $databaseType = $database->type instanceof DatabaseType
            ? $database->type
            : DatabaseType::from($database->type);

        // Re-dispatch installer job based on database type
        switch ($databaseType) {
            case DatabaseType::MariaDB:
                MariaDbInstallerJob::dispatch($server, $database->id);
                break;
            case DatabaseType::MySQL:
                MySqlInstallerJob::dispatch($server, $database->id);
                break;
            case DatabaseType::PostgreSQL:
                PostgreSqlInstallerJob::dispatch($server, $database->id);
                break;
            case DatabaseType::Redis:
                RedisInstallerJob::dispatch($server, $database->id);
                break;
            default:
                $database->update(['status' => DatabaseStatus::Failed->value]);

                return back()->with('error', 'Selected database type is not supported yet.');
        }

        // No redirect needed - frontend will update via Reverb
        return back();
    }
}
