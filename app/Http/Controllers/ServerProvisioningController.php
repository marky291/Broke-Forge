<?php

namespace App\Http\Controllers;

use App\Http\Resources\ServerProvisioningResource;
use App\Models\Server;
use App\Packages\Enums\Connection;
use App\Packages\Enums\ProvisionStatus;
use App\Packages\ProvisionAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ServerProvisioningController extends Controller
{
    public function show(Server $server): Response|\Illuminate\Http\RedirectResponse
    {
        // Redirect to server page if fully provisioned
        if ($server->provision_status === ProvisionStatus::Completed) {
            return redirect()->route('servers.show', $server);
        }

        $server->load(['databases', 'defaultPhp', 'events']);

        return Inertia::render('servers/provisioning', [
            'server' => new ServerProvisioningResource($server),
            'provision' => $this->getProvisionData($server),
        ]);
    }

    public function provision(Server $server): HttpResponse
    {
        $script = (new ProvisionAccess)->makeScriptFor($server, $server->ssh_root_password);

        return response($script, 200, [
            'Content-Type' => 'text/x-shellscript; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function services(Server $server): Response
    {
        return Inertia::render('servers/provision/services', [
            'server' => $server->only(['id', 'vanity_name', 'provider', 'public_ip', 'ssh_port', 'private_ip', 'connection', 'created_at', 'updated_at']),
        ]);
    }

    public function storeServices(Server $server): RedirectResponse
    {
        // TODO: Implement service provisioning logic
        return redirect()
            ->route('servers.provision.services', $server)
            ->with('success', 'Services configured successfully');
    }

    /**
     * Reset the server so provisioning can be attempted again.
     */
    public function retry(Server $server): RedirectResponse
    {
        if ($server->provision_status !== ProvisionStatus::Failed) {
            return redirect()
                ->route('servers.provisioning', $server)
                ->with('error', 'Provisioning is not in a failed state.');
        }

        // Clear any recorded events so progress restarts cleanly
        $server->events()->delete();

        // Generate new root password for the next attempt
        $server->ssh_root_password = null;

        $server->connection = Connection::PENDING;
        $server->provision_status = ProvisionStatus::Pending;
        $server->save();

        return redirect()
            ->route('servers.provisioning', $server)
            ->with('success', 'Provisioning reset. Run the provisioning command again.');
    }

    protected function getProvisionData(Server $server): ?array
    {
        return [
            'command' => $this->buildProvisionCommand($server),
            'root_password' => $server->ssh_root_password,
        ];
    }

    protected function buildProvisionCommand(Server $server): string
    {
        $provisionUrl = route('servers.provision', ['server' => $server->id]);
        $filename = Str::slug(config('app.name')).'.sh';

        return sprintf('wget -O %1$s "%2$s"; bash %1$s', $filename, $provisionUrl);
    }
}
