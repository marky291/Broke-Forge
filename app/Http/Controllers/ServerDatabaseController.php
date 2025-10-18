<?php

namespace App\Http\Controllers;

use App\Enums\DatabaseStatus;
use App\Enums\DatabaseType;
use App\Http\Controllers\Concerns\PreparesSiteData;
use App\Http\Requests\Servers\InstallDatabaseRequest;
use App\Http\Resources\ServerResource;
use App\Models\Server;
use App\Packages\Services\Database\MariaDB\MariaDbInstallerJob;
use App\Packages\Services\Database\MariaDB\MariaDbRemoverJob;
use App\Packages\Services\Database\MariaDB\MariaDbUpdaterJob;
use App\Packages\Services\Database\MySQL\MySqlInstallerJob;
use App\Packages\Services\Database\MySQL\MySqlRemoverJob;
use App\Packages\Services\Database\MySQL\MySqlUpdaterJob;
use App\Packages\Services\Database\PostgreSQL\PostgreSqlInstallerJob;
use App\Packages\Services\Database\PostgreSQL\PostgreSqlRemoverJob;
use App\Packages\Services\Database\PostgreSQL\PostgreSqlUpdaterJob;
use App\Services\DatabaseConfigurationService;
use Illuminate\Http\JsonResponse;
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

    public function index(Server $server): Response
    {
        // Authorize user can view this server
        $this->authorize('view', $server);

        return Inertia::render('servers/database', [
            'server' => new ServerResource($server),
            'availableDatabases' => Inertia::defer(
                fn () => $this->databaseConfig->getAvailableTypes($server->os_codename)
            ),
        ]);
    }

    public function store(InstallDatabaseRequest $request, Server $server): RedirectResponse
    {
        // Authorize user can update this server
        $this->authorize('update', $server);

        $validated = $request->validated();

        $existingDatabase = $server->databases()->exists();
        if ($existingDatabase) {
            return redirect()
                ->route('servers.database', $server)
                ->with('error', 'A database is already installed on this server.');
        }

        $databaseType = DatabaseType::from($validated['type']);

        // ✅ CREATE RECORD FIRST with 'pending' status
        $database = $server->databases()->create([
            'name' => $validated['name'] ?? $validated['type'],
            'type' => $validated['type'],
            'version' => $validated['version'],
            'port' => $validated['port'] ?? $this->databaseConfig->getDefaultPort($databaseType),
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
            default:
                $database->update(['status' => DatabaseStatus::Failed->value]);

                return redirect()
                    ->route('servers.database', $server)
                    ->with('error', 'Selected database type is not supported yet.');
        }

        return redirect()
            ->route('servers.database', $server)
            ->with('success', 'Database installation started.');
    }

    public function update(Request $request, Server $server): RedirectResponse
    {
        // Authorize user can update this server
        $this->authorize('update', $server);

        $database = $server->databases()->first();

        if (! $database) {
            return redirect()
                ->route('servers.database', $server)
                ->with('error', 'No database found to update.');
        }

        $validated = $request->validate([
            'version' => 'required|string',
        ]);

        if ($database->status === DatabaseStatus::Installing ||
            $database->status === DatabaseStatus::Uninstalling ||
            $database->status === DatabaseStatus::Updating) {
            return redirect()
                ->route('servers.database', $server)
                ->with('error', 'Database is currently being modified. Please wait.');
        }

        $databaseType = $database->type instanceof DatabaseType
            ? $database->type
            : DatabaseType::from($database->type);

        $database->update(['status' => DatabaseStatus::Updating->value]);

        switch ($databaseType) {
            case DatabaseType::MariaDB:
                MariaDbUpdaterJob::dispatch($server, $validated['version']);
                break;
            case DatabaseType::MySQL:
                MySqlUpdaterJob::dispatch($server, $validated['version']);
                break;
            case DatabaseType::PostgreSQL:
                PostgreSqlUpdaterJob::dispatch($server, $validated['version']);
                break;
            default:
                $database->update(['status' => DatabaseStatus::Failed->value]);

                return redirect()
                    ->route('servers.database', $server)
                    ->with('error', 'Selected database type cannot be updated automatically yet.');
        }

        return redirect()
            ->route('servers.database', $server)
            ->with('success', 'Database update started.');
    }

    public function destroy(Server $server): RedirectResponse
    {
        // Authorize user can delete this server
        $this->authorize('delete', $server);

        $database = $server->databases()->first();

        if (! $database) {
            return redirect()
                ->route('servers.database', $server)
                ->with('error', 'No database found to uninstall.');
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
            default:
                $database->update(['status' => DatabaseStatus::Failed->value]);

                return redirect()
                    ->route('servers.database', $server)
                    ->with('error', 'Selected database type cannot be uninstalled automatically yet.');
        }

        return redirect()
            ->route('servers.database', $server)
            ->with('success', 'Database uninstallation started.');
    }

    public function status(Server $server): JsonResponse
    {
        // Authorize user can view this server
        $this->authorize('view', $server);

        $database = $server->databases()->latest()->first();

        // If no database exists, it was uninstalled
        if (! $database) {
            return response()->json([
                'status' => 'uninstalled',
                'database' => null,
            ]);
        }

        return response()->json([
            'status' => $database->status,
            'error_message' => $database->error_message,
            'database' => [
                'id' => $database->id,
                'name' => $database->name,
                'type' => $database->type,
                'version' => $database->version,
                'port' => $database->port,
                'status' => $database->status,
                'error_message' => $database->error_message,
                'created_at' => $database->created_at->toISOString(),
                'updated_at' => $database->updated_at->toISOString(),
            ],
        ]);
    }
}
