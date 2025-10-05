<?php

namespace App\Http\Controllers;

use App\Enums\DatabaseStatus;
use App\Enums\DatabaseType;
use App\Http\Controllers\Concerns\PreparesSiteData;
use App\Http\Requests\Servers\InstallDatabaseRequest;
use App\Models\Server;
use Illuminate\Http\Request;
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
        $database = $server->databases()->latest()->first();
        $databases = $server->databases()->latest()->get();

        return Inertia::render('servers/database', [
            'server' => $server->only([
                'id',
                'vanity_name',
                'public_ip',
                'private_ip',
                'ssh_port',
                'connection', 'monitoring_status',
                'provision_status',
                'created_at',
                'updated_at',
            ]),
            'availableDatabases' => Inertia::defer(
                fn () => $this->databaseConfig->getAvailableTypes($server->os_codename)
            ),
            'installedDatabase' => $database ? [
                'id' => $database->id,
                'service_name' => $database->name,
                'configuration' => [
                    'type' => $database->type,
                    'version' => $database->version,
                    'root_password' => $database->root_password,
                ],
                'status' => $database->status,
                'progress_step' => $database->current_step,
                'progress_total' => $database->total_steps,
                'progress_label' => $database->progress_label ?? null,
                'installed_at' => $database->created_at?->toISOString(),
            ] : null,
            'databases' => $databases->map(fn ($db) => [
                'id' => $db->id,
                'name' => $db->name,
                'type' => $db->type,
                'version' => $db->version,
                'port' => $db->port,
                'status' => $db->status,
                'created_at' => $db->created_at?->toISOString(),
            ]),
            'latestMetrics' => $this->getLatestMetrics($server),
        ]);
    }

    public function store(InstallDatabaseRequest $request, Server $server): RedirectResponse
    {
        $validated = $request->validated();

        $existingDatabase = $server->databases()->exists();
        if ($existingDatabase) {
            return back()->with('error', 'A database is already installed on this server.');
        }

        $databaseType = DatabaseType::from($validated['type']);

        $database = $server->databases()->create([
            'name' => $validated['name'] ?? $validated['type'],
            'type' => $validated['type'],
            'version' => $validated['version'],
            'port' => $validated['port'] ?? $this->databaseConfig->getDefaultPort($databaseType),
            'status' => DatabaseStatus::Installing->value,
            'root_password' => $validated['root_password'],
        ]);

        switch ($databaseType) {
            case DatabaseType::MariaDB:
                MariaDbInstallerJob::dispatch($server);
                break;
            case DatabaseType::MySQL:
                MySqlInstallerJob::dispatch($server);
                break;
            case DatabaseType::PostgreSQL:
                PostgreSqlInstallerJob::dispatch($server);
                break;
            default:
                $database->update(['status' => DatabaseStatus::Failed->value]);

                return back()->with('error', 'Selected database type is not supported yet.');
        }

        return back()->with('success', 'Database installation started.');
    }

    public function update(Request $request, Server $server): RedirectResponse
    {
        $database = $server->databases()->first();

        if (! $database) {
            return back()->with('error', 'No database found to update.');
        }

        $validated = $request->validate([
            'version' => 'required|string',
        ]);

        if ($database->status === DatabaseStatus::Installing ||
            $database->status === DatabaseStatus::Uninstalling ||
            $database->status === DatabaseStatus::Updating) {
            return back()->with('error', 'Database is currently being modified. Please wait.');
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

                return back()->with('error', 'Selected database type cannot be updated automatically yet.');
        }

        return back()->with('success', 'Database update started.');
    }

    public function destroy(Server $server): RedirectResponse
    {
        $database = $server->databases()->first();

        if (! $database) {
            return back()->with('error', 'No database found to uninstall.');
        }

        $database->update(['status' => DatabaseStatus::Uninstalling->value]);

        $databaseType = $database->type instanceof DatabaseType
            ? $database->type
            : DatabaseType::from($database->type);

        switch ($databaseType) {
            case DatabaseType::MariaDB:
                MariaDbRemoverJob::dispatch($server);
                break;
            case DatabaseType::MySQL:
                MySqlRemoverJob::dispatch($server);
                break;
            case DatabaseType::PostgreSQL:
                PostgreSqlRemoverJob::dispatch($server);
                break;
            default:
                $database->update(['status' => DatabaseStatus::Failed->value]);

                return back()->with('error', 'Selected database type cannot be uninstalled automatically yet.');
        }

        return back()->with('success', 'Database uninstallation started.');
    }

    public function status(Server $server): JsonResponse
    {
        $database = $server->databases()->latest()->first();

        // If no database exists, it was uninstalled
        if (! $database) {
            return response()->json([
                'status' => 'uninstalled',
                'database' => null,
            ]);
        }

        // Get progress from the latest database-related server event
        $latestEvent = $server->events()
            ->where('service_type', 'database')
            ->orderBy('id', 'desc')
            ->first();

        $progressStep = $latestEvent?->current_step ?? 0;
        $progressTotal = $latestEvent?->total_steps ?? 0;
        $progressLabel = $latestEvent?->milestone ?? null;

        return response()->json([
            'status' => $database->status,
            'progress_step' => $progressStep,
            'progress_total' => $progressTotal,
            'progress_label' => $progressLabel,
            'error_message' => $database->error_message,
            'database' => [
                'id' => $database->id,
                'name' => $database->name,
                'type' => $database->type,
                'version' => $database->version,
                'port' => $database->port,
                'status' => $database->status,
                'progress_step' => $progressStep,
                'progress_total' => $progressTotal,
                'progress_label' => $progressLabel,
                'error_message' => $database->error_message,
                'created_at' => $database->created_at->toISOString(),
                'updated_at' => $database->updated_at->toISOString(),
            ],
        ]);
    }
}
