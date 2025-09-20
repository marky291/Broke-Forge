<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ServerSettingsController extends Controller
{
    public function index(Server $server): \Inertia\Response
    {
        return Inertia::render('servers/settings', [
            'server' => $server,
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
