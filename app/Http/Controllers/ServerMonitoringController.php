<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServerMetricsRequest;
use App\Http\Resources\ServerMetricResource;
use App\Models\Server;
use App\Models\ServerMetric;
use App\Packages\Services\Monitoring\ServerMonitoringInstallerJob;
use App\Packages\Services\Monitoring\ServerMonitoringRemoverJob;
use App\Packages\Services\Monitoring\ServerMonitoringTimerUpdaterJob;
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
    public function index(Request $request, Server $server): Response
    {
        // Validate and get timeframe in hours (default 24)
        $hours = $request->validate([
            'hours' => 'nullable|integer|in:24,72,168',
        ])['hours'] ?? 24;

        // Get recent metrics for the selected timeframe
        $recentMetrics = $server->metrics()
            ->where('collected_at', '>=', now()->subHours($hours))
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
            'selectedTimeframe' => $hours,
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
        // Validate timeframe parameter
        $hours = $request->validate([
            'hours' => 'nullable|integer|in:24,72,168',
        ])['hours'] ?? 24;

        $metrics = $server->metrics()
            ->where('collected_at', '>=', now()->subHours($hours))
            ->orderBy('collected_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ServerMetricResource::collection($metrics),
        ]);
    }

    /**
     * Update monitoring collection interval
     */
    public function updateInterval(Request $request, Server $server): RedirectResponse
    {
        // Validate interval (in seconds: 1min, 5min, 10min, 20min, 30min, 1hour)
        // Also validate the hours parameter to preserve viewing timeframe
        $validated = $request->validate([
            'interval' => 'required|integer|in:60,300,600,1200,1800,3600',
            'hours' => 'nullable|integer|in:24,72,168',
        ]);

        // Check if monitoring is active
        if (! $server->monitoringIsActive()) {
            return redirect()
                ->route('servers.monitoring', $server)
                ->with('error', 'Monitoring must be active to update collection interval');
        }

        // Dispatch timer update job
        ServerMonitoringTimerUpdaterJob::dispatch($server, $validated['interval']);

        // Preserve the current viewing timeframe when redirecting (default to 24)
        $currentTimeframe = $validated['hours'] ?? 24;

        return redirect()
            ->route('servers.monitoring', ['server' => $server, 'hours' => $currentTimeframe])
            ->with('success', 'Monitoring collection interval update started');
    }
}
