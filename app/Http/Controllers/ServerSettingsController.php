<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PreparesSiteData;
use App\Models\Server;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ServerSettingsController extends Controller
{
    use PreparesSiteData;

    public function index(Request $request, Server $server): \Inertia\Response
    {
        return Inertia::render('servers/settings', [
            'server' => $server,
            'latestMetrics' => $this->getLatestMetrics($server),
        ]);
    }

    public function update(Request $request, Server $server): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'vanity_name' => ['required', 'string', 'max:255'],
            'public_ip' => ['required', 'ip'],
            'private_ip' => ['nullable', 'ip'],
            'ssh_port' => ['required', 'integer', 'min:1', 'max:65535'],
        ]);

        $server->update($validated);

        return redirect()
            ->route('servers.settings', $server)
            ->with('success', 'Server settings updated successfully.');
    }
}
