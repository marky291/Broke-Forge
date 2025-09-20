<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Server;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $activities = $this->getRecentActivities();
        $servers = $this->getServers();

        return Inertia::render('dashboard', [
            'activities' => $activities,
            'servers' => $servers,
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
                    'type' => $activity->type,
                    'label' => $this->getActivityLabel($activity->type),
                    'detail' => $detail,
                    'created_at' => $activity->created_at,
                ];
            });
    }

    protected function getActivityDetail(Activity $activity): ?string
    {
        return match ($activity->type) {
            'server.created' => $this->getServerDetail($activity->properties),
            'server.provision.started',
            'server.provision.completed',
            'server.provision.failed' => $this->getProvisioningDetail($activity->properties),
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
            'auth.login' => 'User logged in',
            default => $type,
        };
    }

    protected function getServers(): \Illuminate\Support\Collection
    {
        return Server::query()
            ->select(['id', 'vanity_name', 'public_ip', 'private_ip', 'ssh_port', 'connection', 'provision_status', 'created_at'])
            ->latest()
            ->get()
            ->map(fn (Server $server) => [
                'id' => $server->id,
                'name' => $server->vanity_name,
                'public_ip' => $server->public_ip,
                'private_ip' => $server->private_ip,
                'ssh_port' => $server->ssh_port,
                'connection' => $server->connection,
                'provision_status' => $server->provision_status->value,
                'created_at' => $server->created_at,
            ]);
    }
}
