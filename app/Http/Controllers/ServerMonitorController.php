<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServerMonitorRequest;
use App\Http\Requests\UpdateServerMonitorRequest;
use App\Http\Resources\ServerMonitorResource;
use App\Models\Server;
use App\Models\ServerMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServerMonitorController extends Controller
{
    /**
     * Display a listing of monitors for the server.
     */
    public function index(Server $server): JsonResponse
    {
        $this->authorize('view', $server);

        $monitors = $server->monitors()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'monitors' => ServerMonitorResource::collection($monitors),
        ]);
    }

    /**
     * Store a newly created monitor.
     */
    public function store(StoreServerMonitorRequest $request, Server $server): RedirectResponse
    {
        $monitor = $server->monitors()->create([
            'user_id' => $request->user()->id,
            'name' => $request->input('name'),
            'metric_type' => $request->input('metric_type'),
            'operator' => $request->input('operator'),
            'threshold' => $request->input('threshold'),
            'duration_minutes' => $request->input('duration_minutes'),
            'notification_emails' => $request->input('notification_emails'),
            'enabled' => $request->input('enabled', true),
            'cooldown_minutes' => $request->input('cooldown_minutes', 60),
            'status' => 'normal',
        ]);

        return redirect()
            ->route('servers.monitoring', $server)
            ->with('success', 'Monitor created successfully');
    }

    /**
     * Update the specified monitor.
     */
    public function update(UpdateServerMonitorRequest $request, Server $server, ServerMonitor $monitor): RedirectResponse
    {
        $monitor->update($request->validated());

        return redirect()
            ->route('servers.monitoring', $server)
            ->with('success', 'Monitor updated successfully');
    }

    /**
     * Remove the specified monitor.
     */
    public function destroy(Server $server, ServerMonitor $monitor): RedirectResponse
    {
        $this->authorize('view', $server);

        if ($monitor->user_id !== auth()->id()) {
            abort(403, 'Unauthorized to delete this monitor');
        }

        $monitor->delete();

        return redirect()
            ->route('servers.monitoring', $server)
            ->with('success', 'Monitor deleted successfully');
    }

    /**
     * Toggle the enabled status of a monitor.
     */
    public function toggle(Request $request, Server $server, ServerMonitor $monitor): JsonResponse
    {
        $this->authorize('view', $server);

        if ($monitor->user_id !== auth()->id()) {
            abort(403, 'Unauthorized to toggle this monitor');
        }

        $monitor->update([
            'enabled' => ! $monitor->enabled,
        ]);

        return response()->json([
            'monitor' => new ServerMonitorResource($monitor),
        ]);
    }
}
