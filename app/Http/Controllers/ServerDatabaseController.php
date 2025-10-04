<?php

namespace App\Http\Controllers;

use App\Enums\DatabaseStatus;
use App\Enums\DatabaseType;
use App\Http\Controllers\Concerns\PreparesSiteData;
use App\Http\Requests\Servers\InstallDatabaseRequest;
use App\Models\Server;
use App\Packages\Services\Database\MariaDB\MariaDbInstallerJob;
use App\Packages\Services\Database\MariaDB\MariaDbRemoverJob;
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
                fn () => $this->databaseConfig->getAvailableTypes()
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
            'status' => 'installing',
            'root_password' => $validated['root_password'],
        ]);

        // Dispatch MariaDB installation job
        MariaDbInstallerJob::dispatch($server);

        return back()->with('success', 'Database installation started.');
    }

    public function destroy(Server $server): RedirectResponse
    {
        $database = $server->databases()->first();

        if (! $database) {
            return back()->with('error', 'No database found to uninstall.');
        }

        $database->update(['status' => DatabaseStatus::Uninstalling]);

        // Dispatch MariaDB removal job
        MariaDbRemoverJob::dispatch($server);

        return back()->with('success', 'Database uninstallation started.');
    }

    public function status(Server $server): JsonResponse
    {
        $database = $server->databases()->latest()->first();

        return response()->json([
            'database' => $database ? [
                'id' => $database->id,
                'name' => $database->name,
                'type' => $database->type,
                'version' => $database->version,
                'port' => $database->port,
                'status' => $database->status,
                'progress_step' => $database->current_step,
                'progress_total' => $database->total_steps,
                'progress_label' => $database->progress_label ?? null,
                'created_at' => $database->created_at->toISOString(),
                'updated_at' => $database->updated_at->toISOString(),
            ] : null,
        ]);
    }
}
