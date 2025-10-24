<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServerMetricsRequest;
use App\Http\Resources\ServerMetricResource;
use App\Http\Resources\ServerResource;
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
        // Authorize user can view this server
        $this->authorize('view', $server);

        // Validate and get timeframe in hours (default 24)
        $hours = $request->validate([
            'hours' => 'nullable|integer|in:24,72,168',
        ])['hours'] ?? 24;

        return Inertia::render('servers/monitoring', [
            'server' => new ServerResource($server),
            'selectedTimeframe' => (int) $hours,
        ]);
    }

    /**
     * Install monitoring on the server
     */
    public function install(Server $server): RedirectResponse
    {
        // Authorize user can update this server
        $this->authorize('update', $server);

        // Check if monitoring already exists
        if ($server->monitoringIsActive()) {
            return redirect()
                ->route('servers.monitoring', $server)
                ->with('error', 'Monitoring is already installed on this server');
        }

        // Set status to installing immediately
        $server->update(['monitoring_status' => 'installing']);

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
        // Authorize user can update this server
        $this->authorize('update', $server);

        // Check if monitoring exists
        if (! $server->monitoringIsActive()) {
            return redirect()
                ->route('servers.monitoring', $server)
                ->with('error', 'Monitoring is not installed on this server');
        }

        // Set status to uninstalling immediately
        $server->update(['monitoring_status' => 'removing']);

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
        // Authorize user can view this server
        $this->authorize('view', $server);

        // Validate timeframe parameter
        $hours = $request->validate([
            'hours' => 'nullable|integer|in:1,24,72,168',
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
        // Authorize user can update this server
        $this->authorize('update', $server);

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

    /**
     * Retry a failed monitoring installation
     */
    public function retry(Server $server): RedirectResponse
    {
        $this->authorize('update', $server);

        // Only allow retry for failed monitoring
        if ($server->monitoring_status !== \App\Enums\TaskStatus::Failed) {
            return back()->with('error', 'Only failed monitoring installations can be retried');
        }

        // Audit log
        \Illuminate\Support\Facades\Log::info('Monitoring installation retry initiated', [
            'user_id' => auth()->id(),
            'server_id' => $server->id,
            'ip_address' => request()->ip(),
        ]);

        // Reset status to 'installing'
        // Model events will broadcast automatically via Reverb
        $server->update(['monitoring_status' => \App\Enums\TaskStatus::Installing]);

        // Re-dispatch installer job
        ServerMonitoringInstallerJob::dispatch($server);

        // No redirect needed - frontend will update via Reverb
        return back();
    }
}
