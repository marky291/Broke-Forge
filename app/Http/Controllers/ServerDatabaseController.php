<?php

namespace App\Http\Controllers;

use App\Http\Requests\Servers\InstallDatabaseRequest;
use App\Models\Server;
use App\Packages\Services\Database\MySQL\MySqlInstallerJob;
use App\Packages\Services\Database\MySQL\MySqlRemoverJob;
use App\Services\DatabaseConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ServerDatabaseController extends Controller
{
    public function __construct(
        private readonly DatabaseConfigurationService $databaseConfig
    ) {}

    public function index(Server $server): Response
    {
        $database = $server->databases()->latest()->first();

        return Inertia::render('servers/database', [
            'server' => $server->only([
                'id',
                'vanity_name',
                'public_ip',
                'private_ip',
                'ssh_port',
                'connection',
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
        ]);
    }

    public function store(InstallDatabaseRequest $request, Server $server): RedirectResponse
    {
        $validated = $request->validated();

        $existingDatabase = $server->databases()->exists();
        if ($existingDatabase) {
            return back()->with('error', 'A database is already installed on this server.');
        }

        $database = $server->databases()->create([
            'name' => $validated['name'] ?? $validated['type'],
            'type' => $validated['type'],
            'version' => $validated['version'],
            'port' => $validated['port'] ?? $this->databaseConfig->getDefaultPort($validated['type']),
            'status' => 'installing',
            'root_password' => $validated['root_password'],
        ]);

        // Dispatch MySQL installation job following existing pattern
        MySqlInstallerJob::dispatch($server);

        return back()->with('success', 'Database installation started.');
    }

    public function destroy(Server $server): RedirectResponse
    {
        $database = $server->databases()->first();

        if (! $database) {
            return back()->with('error', 'No database found to uninstall.');
        }

        $database->update(['status' => 'uninstalling']);

        // Use existing MySqlRemover job architecture
        MySqlRemoverJob::dispatch($server);

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
