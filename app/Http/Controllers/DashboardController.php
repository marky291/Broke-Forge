<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $servers = $this->getServers();
        $sites = $this->getSites();
        $activities = $this->getRecentActivities();

        return Inertia::render('dashboard', [
            'servers' => $servers,
            'sites' => $sites,
            'activities' => $activities,
        ]);
    }

    protected function getRecentActivities(): \Illuminate\Support\Collection
    {
        return Activity::query()
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($activity) {
                $detail = $this->getActivityDetail($activity);

                return [
                    'id' => $activity->id,
                    'type' => $activity->event ?? 'unknown',
                    'label' => $this->getActivityLabel($activity->event ?? 'unknown'),
                    'description' => $activity->description,
                    'detail' => $detail,
                    'created_at' => $activity->created_at,
                    'created_at_human' => $activity->created_at->diffForHumans(),
                ];
            });
    }

    protected function getActivityDetail(Activity $activity): ?string
    {
        return match ($activity->event) {
            'server.created' => $this->getServerDetail($activity->properties->toArray()),
            'server.provision.started',
            'server.provision.completed',
            'server.provision.failed' => $this->getProvisioningDetail($activity->properties->toArray()),
            'auth.login' => data_get($activity->properties, 'email'),
            default => null,
        };
    }

    protected function getServerDetail(array $properties): ?string
    {
        $name = data_get($properties, 'name');
        $publicIp = data_get($properties, 'public_ip');

        $detail = $name ?: null;
        if ($publicIp) {
            $detail = trim(($detail ? $detail.' — ' : '').$publicIp);
        }

        return $detail;
    }

    protected function getProvisioningDetail(array $properties): ?string
    {
        $name = data_get($properties, 'name');
        $publicIp = data_get($properties, 'public_ip');
        $port = data_get($properties, 'ssh_port');

        $detail = $name ?: null;
        if ($publicIp) {
            $detail = trim(($detail ? $detail.' — ' : '').$publicIp.(is_null($port) ? '' : ":$port"));
        }

        return $detail;
    }

    protected function getActivityLabel(string $type): string
    {
        return match ($type) {
            'server.created' => 'Server created',
            'server.provision.started' => 'Provisioning started',
            'server.provision.completed' => 'Provisioning completed',
            'server.provision.failed' => 'Provisioning failed',
            'site.created' => 'Site created',
            'site.status_changed' => 'Site status changed',
            'site.deleted' => 'Site deleted',
            'auth.login' => 'User logged in',
            default => $type,
        };
    }

    protected function getServers(): \Illuminate\Support\Collection
    {
        return Server::query()
            ->with(['defaultPhp', 'sites', 'supervisorTasks', 'scheduledTasks'])
            ->select(['id', 'vanity_name', 'provider', 'public_ip', 'ssh_port', 'created_at'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function (Server $server) {
                return [
                    'id' => $server->id,
                    'name' => $server->vanity_name,
                    'provider' => $server->provider?->value,
                    'public_ip' => $server->public_ip,
                    'ssh_port' => $server->ssh_port,
                    'php_version' => $server->defaultPhp?->version,
                    'sites_count' => $server->sites->count(),
                    'supervisor_tasks_count' => $server->supervisorTasks->count(),
                    'scheduled_tasks_count' => $server->scheduledTasks->count(),
                ];
            });
    }

    protected function getSites(): \Illuminate\Support\Collection
    {
        return \App\Models\ServerSite::query()
            ->with(['server'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function (\App\Models\ServerSite $site) {
                $gitConfig = $site->getGitConfiguration();

                return [
                    'id' => $site->id,
                    'domain' => $site->domain,
                    'repository' => $gitConfig['repository'],
                    'php_version' => $site->php_version,
                    'server_name' => $site->server->vanity_name,
                    'last_deployed_at' => $site->last_deployed_at,
                ];
            });
    }
}
