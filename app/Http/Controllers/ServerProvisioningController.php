<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\ServerEvent;
use App\Packages\Enums\Connection;
use App\Packages\Enums\PackageType;
use App\Packages\Enums\ProvisionStatus;
use App\Packages\ProvisionAccess;
use App\Packages\Services\Nginx\NginxInstallerMilestones;
use Illuminate\Http\JsonResponse;
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

        // Get all events for comprehensive progress tracking
        $events = $server->events()
            ->orderBy('created_at')
            ->get()
            ->map(function (ServerEvent $event) {
                $label = null;

                if (is_string($event->milestone)) {
                    $label = NginxInstallerMilestones::label($event->milestone)
                        ?? Str::headline($event->milestone);
                } elseif (is_array($event->milestone) && array_key_exists('label', $event->milestone)) {
                    $label = $event->milestone['label'];
                }

                return [
                    'id' => $event->id,
                    'server_id' => $event->server_id,
                    'service_type' => $event->service_type,
                    'provision_type' => $event->provision_type,
                    'milestone' => $event->milestone,
                    'current_step' => $event->current_step,
                    'total_steps' => $event->total_steps,
                    'progress_percentage' => $event->progress_percentage,
                    'details' => $event->details,
                    'label' => $label,
                    'status' => $event->status,
                    'error_log' => $event->error_log,
                    'created_at' => $event->created_at->toISOString(),
                ];
            })
            ->values()
            ->all();

        // Get the latest progress for each service type
        $latestProgress = collect($events)
            ->groupBy('service_type')
            ->map(fn ($serviceEvents) => $serviceEvents->last())
            ->values()
            ->all();

        return Inertia::render('servers/provisioning', [
            'server' => array_merge(
                $server->only(['id', 'vanity_name', 'public_ip', 'ssh_port', 'private_ip', 'connection', 'created_at', 'updated_at']),
                [
                    'provision_status' => $server->provision_status->value,
                    'provision_status_label' => $server->provision_status->label(),
                    'provision_status_color' => $server->provision_status->color(),
                ]
            ),
            'provision' => $this->getProvisionData($server),
            'events' => $events,
            'latestProgress' => $latestProgress,
            'webServiceMilestones' => NginxInstallerMilestones::labels(),
            'packageNameLabels' => [
                PackageType::ReverseProxy->value => 'Reverse Proxy',
                PackageType::Database->value => 'Database',
                PackageType::Git->value => 'Git',
                PackageType::Site->value => 'Site',
                PackageType::Command->value => 'Command',
            ],
            'statusLabels' => ProvisionStatus::statusLabels(),
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
            'server' => $server->only(['id', 'vanity_name', 'public_ip', 'ssh_port', 'private_ip', 'connection', 'created_at', 'updated_at']),
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

    /**
     * Get package events for a server
     *
     * Returns all package install/uninstall events for tracking on the frontend
     */
    public function events(Server $server): JsonResponse
    {
        $events = $server->events()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (ServerEvent $event) {
                return [
                    'id' => $event->id,
                    'service_type' => $event->service_type,
                    'provision_type' => $event->provision_type,
                    'milestone' => $event->milestone,
                    'current_step' => $event->current_step,
                    'total_steps' => $event->total_steps,
                    'progress_percentage' => $event->progress_percentage,
                    'details' => $event->details,
                    'status' => $event->status,
                    'error_log' => $event->error_log,
                    'created_at' => $event->created_at->toISOString(),
                ];
            });

        return response()->json([
            'events' => $events,
            'server_id' => $server->id,
        ]);
    }

    protected function getProvisionData(Server $server): ?array
    {
        // Show provision data only when connection is pending
        if ($server->connection !== 'pending') {
            return null;
        }

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