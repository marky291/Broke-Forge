<?php

namespace App\Http\Controllers;

use App\Http\Requests\Servers\StoreServerRequest;
use App\Http\Requests\Servers\UpdateServerRequest;
use App\Models\Server;
use App\Packages\Credentials\TemporaryCredentialCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ServerController extends Controller
{
    public function index(): Response
    {
        $servers = Server::query()
            ->select(['id', 'vanity_name', 'public_ip', 'private_ip', 'connection', 'created_at'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('servers/index', [
            'servers' => $servers,
        ]);
    }

    public function store(StoreServerRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $phpVersion = $data['php_version'];
        unset($data['php_version']);

        $data['user_id'] = $request->user()->getKey();
        $data['ssh_root_user'] = 'root';
        $data['ssh_app_user'] = Str::slug(config('app.name'));

        $server = Server::create($data);

        $rootPassword = TemporaryCredentialCache::rootPassword($server);
        $provisionCommand = $this->buildProvisionCommand($server);

        return redirect()
            ->route('servers.provisioning', $server)
            ->with('provision', [
                'command' => $provisionCommand,
                'root_password' => $rootPassword,
            ])
            ->with('success', 'Server created');
    }

    public function show(Server $server): Response
    {
        return Inertia::render('servers/show', [
            'server' => $server->only(['id', 'vanity_name', 'public_ip', 'ssh_port', 'private_ip', 'connection', 'created_at', 'updated_at']),
        ]);
    }

    public function edit(Server $server): Response
    {
        return Inertia::render('servers/edit', [
            'server' => $server->only(['id', 'vanity_name', 'public_ip', 'ssh_port', 'private_ip']),
        ]);
    }

    public function update(UpdateServerRequest $request, Server $server): RedirectResponse
    {
        $server->update($request->validated());

        return redirect()->route('dashboard')->with('success', 'Server updated');
    }

    public function destroy(Server $server): RedirectResponse
    {
        $server->delete();

        return redirect()->route('dashboard')->with('success', 'Server deleted');
    }

    protected function buildProvisionCommand(Server $server): string
    {
        $provisionUrl = route('servers.provision', ['server' => $server->id]);
        $filename = Str::slug(config('app.name')).'.sh';

        return sprintf('wget -O %1$s "%2$s"; bash %1$s', $filename, $provisionUrl);
    }
}
