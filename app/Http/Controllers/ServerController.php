<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PreparesSiteData;
use App\Http\Requests\Servers\StoreServerRequest;
use App\Http\Requests\Servers\UpdateServerRequest;
use App\Models\Server;
use App\Packages\Enums\ProvisionStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ServerController extends Controller
{
    use PreparesSiteData;

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
        // Check server limit
        if (! $request->user()->canCreateServer()) {
            return back()->with('error',
                'You have reached your server limit. Please upgrade your subscription to add more servers.'
            );
        }

        $data = $request->validated();
        $phpVersion = $data['php_version'];
        unset($data['php_version']);

        $data['user_id'] = $request->user()->getKey();
        $data['ssh_root_user'] = 'root';
        $data['ssh_app_user'] = Str::slug(config('app.name'));

        $server = Server::create($data);

        $provisionCommand = $this->buildProvisionCommand($server);

        return redirect()
            ->route('servers.provisioning', $server)
            ->with('provision', [
                'command' => $provisionCommand,
                'root_password' => $server->ssh_root_password,
            ])
            ->with('success', 'Server created');
    }

    public function show(Server $server): RedirectResponse
    {
        // Redirect to provisioning page if not fully provisioned
        if ($server->provision_status !== ProvisionStatus::Completed) {
            return redirect()->route('servers.provisioning', $server);
        }

        return redirect()->route('servers.sites', $server);
    }

    public function edit(Server $server): Response
    {
        return Inertia::render('servers/edit', [
            'server' => $server->only(['id', 'vanity_name', 'provider', 'public_ip', 'ssh_port', 'private_ip']),
        ]);
    }

    public function update(UpdateServerRequest $request, Server $server): RedirectResponse
    {
        $server->update($request->validated());

        return redirect()->route('dashboard')->with('success', 'Server updated');
    }

    public function destroy(Server $server): RedirectResponse
    {
        $this->authorize('delete', $server);

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
