<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServerMetricsRequest;
use App\Http\Resources\ServerMetricResource;
use App\Models\Server;
use App\Models\ServerMetric;
use App\Packages\Services\Monitoring\ServerMonitoringInstallerJob;
use App\Packages\Services\Monitoring\ServerMonitoringRemoverJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServerMonitoringController extends Controller
{
    /**
     * Display monitoring page with installation status and metrics
     */
    public function index(Server $server): Response
    {
        // Get recent metrics (last 24 hours)
        $recentMetrics = $server->metrics()
            ->where('collected_at', '>=', now()->subDay())
            ->orderBy('collected_at', 'desc')
            ->get();

        // Get latest metrics
        $latestMetrics = $server->metrics()
            ->latest('collected_at')
            ->first();

        return Inertia::render('servers/monitoring', [
            'server' => $server->only(['id', 'vanity_name', 'public_ip', 'ssh_port', 'private_ip', 'connection', 'created_at', 'updated_at', 'monitoring_status', 'monitoring_token', 'monitoring_collection_interval', 'monitoring_installed_at', 'monitoring_uninstalled_at']),
            'latestMetrics' => $latestMetrics,
            'recentMetrics' => $recentMetrics,
        ]);
    }

    /**
     * Install monitoring on the server
     */
    public function install(Server $server): RedirectResponse
    {
        // Check if monitoring already exists
        if ($server->monitoringIsActive()) {
            return redirect()
                ->route('servers.monitoring', $server)
                ->with('error', 'Monitoring is already installed on this server');
        }

        // Dispatch monitoring installation job
        ServerMonitoringInstallerJob::dispatch($server);

        return redirect()
            ->route('servers.monitoring', $server)
            ->with('success', 'Monitoring installation started');
    }

    /**
     * Uninstall monitoring from the server
     */
    public function uninstall(Server $server): RedirectResponse
    {
        // Check if monitoring exists
        if (! $server->monitoringIsActive()) {
            return redirect()
                ->route('servers.monitoring', $server)
                ->with('error', 'Monitoring is not installed on this server');
        }

        // Dispatch monitoring removal job
        ServerMonitoringRemoverJob::dispatch($server);

        return redirect()
            ->route('servers.monitoring', $server)
            ->with('success', 'Monitoring uninstallation started');
    }

    /**
     * Store metrics from remote server (API endpoint)
     */
    public function storeMetrics(StoreServerMetricsRequest $request, Server $server): JsonResponse
    {
        // Store the metrics
        $metric = ServerMetric::create([
            'server_id' => $server->id,
            ...$request->validated(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Metrics stored successfully',
            'metric_id' => $metric->id,
        ], 201);
    }

    /**
     * Get metrics for a specific time range (API endpoint)
     */
    public function getMetrics(Request $request, Server $server): JsonResponse
    {
        $hours = $request->integer('hours', 24);

        $metrics = $server->metrics()
            ->where('collected_at', '>=', now()->subHours($hours))
            ->orderBy('collected_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ServerMetricResource::collection($metrics),
        ]);
    }
}
