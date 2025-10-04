<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PreparesSiteData;
use App\Http\Requests\Servers\StoreServerRequest;
use App\Http\Requests\Servers\UpdateServerRequest;
use App\Models\Server;
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

    public function show(Server $server): Response
    {
        $sites = $server->sites()
            ->select(['id', 'domain', 'document_root', 'php_version', 'ssl_enabled', 'status', 'configuration', 'git_status', 'provisioned_at'])
            ->latest()
            ->get()
            ->map(fn ($site) => array_merge(
                $site->only(['id', 'domain', 'document_root', 'php_version', 'ssl_enabled', 'status', 'configuration', 'git_status', 'provisioned_at']),
                ['provisioned_at_human' => $site->provisioned_at?->diffForHumans()]
            ));

        return Inertia::render('servers/show', [
            'server' => $server->only(['id', 'vanity_name', 'public_ip', 'ssh_port', 'private_ip', 'connection', 'monitoring_status', 'created_at', 'updated_at']),
            'sites' => ['data' => $sites],
            'latestMetrics' => $this->getLatestMetrics($server),
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
