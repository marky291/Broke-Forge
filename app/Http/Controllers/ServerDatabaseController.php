<?php

namespace App\Http\Controllers;

use App\Http\Requests\Servers\InstallDatabaseRequest;
use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ServerDatabaseController extends Controller
{
    public function index(Server $server): Response
    {
        $availableDatabases = $this->getAvailableDatabases();

        $installedDatabase = $server->services()
            ->where('service_type', 'database')
            ->whereIn('status', ['installing', 'installed'])
            ->first();

        return Inertia::render('servers/database', [
            'server' => $server->only(['id', 'vanity_name', 'public_ip', 'ssh_port', 'private_ip', 'connection', 'created_at', 'updated_at']),
            'availableDatabases' => $availableDatabases,
            'installedDatabase' => $installedDatabase ? [
                'id' => $installedDatabase->id,
                'service_name' => $installedDatabase->service_name,
                'configuration' => $installedDatabase->configuration,
                'status' => $installedDatabase->status,
                'progress_step' => $installedDatabase->progress_step,
                'progress_total' => $installedDatabase->progress_total,
                'progress_label' => $installedDatabase->progress_label,
                'installed_at' => $installedDatabase->installed_at?->toISOString(),
            ] : null,
        ]);
    }

    public function store(InstallDatabaseRequest $request, Server $server): RedirectResponse
    {
        $validated = $request->validated();

        $existingDatabase = $server->services()
            ->where('service_type', 'database')
            ->whereIn('status', ['installing', 'installed'])
            ->first();

        if ($existingDatabase) {
            return redirect()
                ->route('servers.database', $server)
                ->with('error', 'A database is already installed on this server. Please uninstall it first.');
        }

        $service = $server->services()->create([
            'service_name' => $validated['type'],
            'service_type' => 'database',
            'configuration' => [
                'version' => $validated['version'],
                'root_password' => $validated['root_password'] ?? null,
                'password' => $validated['password'] ?? null,
                'port' => $validated['port'] ?? null,
            ],
            'status' => 'installing',
        ]);

        try {
            dispatch(function () use ($service) {
                try {
                    $runId = (string) Str::uuid();

                    // Database provisioning logic would go here
                    // For now, just simulate installation
                    for ($i = 1; $i <= 5; $i++) {
                        sleep(2);
                        $service->update([
                            'progress_step' => $i,
                            'progress_total' => 5,
                            'progress_label' => "Installing database (step $i of 5)",
                            'status' => 'installing',
                        ]);
                    }
                    $service->update([
                        'status' => 'installed',
                        'installed_at' => now(),
                        'progress_step' => $service->progress_total,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Database installation failed', [
                        'service_id' => $service->id,
                        'error' => $e->getMessage(),
                    ]);
                    $service->update(['status' => 'failed']);
                }
            });

            return redirect()
                ->route('servers.database', $server)
                ->with('success', 'Database installation started. This process may take a few minutes.');

        } catch (\Exception $e) {
            $service->delete();
            Log::error('Database installation setup failed', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('servers.database', $server)
                ->with('error', 'Failed to start database installation: '.$e->getMessage());
        }
    }

    public function destroy(Server $server): RedirectResponse
    {
        $service = $server->services()
            ->where('service_type', 'database')
            ->whereIn('status', ['installed', 'failed'])
            ->first();

        if (! $service) {
            return redirect()
                ->route('servers.database', $server)
                ->with('error', 'No database installation found to uninstall.');
        }

        try {
            $service->update(['status' => 'uninstalling']);

            dispatch(function () use ($service) {
                try {
                    // Database uninstallation logic would go here
                    sleep(3);
                    $service->update([
                        'status' => 'uninstalled',
                        'uninstalled_at' => now(),
                    ]);
                } catch (\Exception $e) {
                    Log::error('Database uninstallation failed', [
                        'service_id' => $service->id,
                        'error' => $e->getMessage(),
                    ]);
                    $service->update(['status' => 'failed']);
                }
            });

            return redirect()
                ->route('servers.database', $server)
                ->with('success', 'Database uninstallation started. This process may take a few minutes.');

        } catch (\Exception $e) {
            Log::error('Database uninstallation setup failed', [
                'server_id' => $server->id,
                'service_id' => $service->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('servers.database', $server)
                ->with('error', 'Failed to start database uninstallation: '.$e->getMessage());
        }
    }

    public function status(Server $server): JsonResponse
    {
        $service = $server->services()
            ->where('service_type', 'database')
            ->orderByDesc('id')
            ->first();

        if (! $service) {
            return response()->json(['status' => 'none']);
        }

        return response()->json([
            'status' => $service->status,
            'progress_step' => $service->progress_step,
            'progress_total' => $service->progress_total,
            'progress_label' => $service->progress_label,
        ]);
    }

    protected function getAvailableDatabases(): array
    {
        return [
            'mysql' => [
                'name' => 'MySQL',
                'description' => 'Popular open-source relational database',
                'icon' => 'database',
                'versions' => [
                    '8.0' => 'MySQL 8.0',
                    '5.7' => 'MySQL 5.7',
                ],
                'default_version' => '8.0',
            ],
            'postgresql' => [
                'name' => 'PostgreSQL',
                'description' => 'Advanced open-source relational database',
                'icon' => 'database',
                'versions' => [
                    '15' => 'PostgreSQL 15',
                    '14' => 'PostgreSQL 14',
                    '13' => 'PostgreSQL 13',
                ],
                'default_version' => '15',
            ],
            'redis' => [
                'name' => 'Redis',
                'description' => 'In-memory data structure store',
                'icon' => 'database',
                'versions' => [
                    '7.0' => 'Redis 7.0',
                    '6.2' => 'Redis 6.2',
                ],
                'default_version' => '7.0',
            ],
        ];
    }
}
