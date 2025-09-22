<?php

namespace App\Packages\Credentials;

use App\Models\Server;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ProvisionAccess
{
    /**
     * Build the provisioning script executed on first boot of a server.
     *
     * Note: This generates a standalone script that runs on the server.
     * InstallMilestone tracking for this script should be handled by the calling code
     * that monitors the server's provisioning status.
     */
    public function makeScriptFor(Server $server, string $rootPassword): string
    {
        // SSH root user is always 'root' based on the error logs
        $sshUser = $server->ssh_root_user ?: 'root';
        // App user from server model or default to slugified app name
        $appUser = $server->ssh_app_user ?: Str::slug(config('app.name'));
        $sshKeyPath = __DIR__.'/Keys/ssh_key.pub';
        $pubKeyContent = trim(file_get_contents($sshKeyPath));
        $appName = config('app.name');

        $callbackUrls = $this->buildCallbackUrls($server);

        return view('scripts.provision_setup_x64', [
            'sshUser' => $sshUser,
            'appUser' => $appUser,
            'pubKeyContent' => $pubKeyContent,
            'appName' => $appName,
            'sshPort' => $server->ssh_port,
            'callbackUrls' => $callbackUrls,
            'rootPassword' => $rootPassword,
        ])->render();
    }

    /**
     * Build signed callback URLs for provisioning lifecycle stages.
     */
    protected function buildCallbackUrls(Server $server): array
    {
        $ttlMinutes = max(1, (int) (config('provision.callback_ttl') ?? 60));
        $expiresAt = now()->addMinutes($ttlMinutes);

        return [
            'started' => URL::temporarySignedRoute('servers.provision.callback', $expiresAt, [
                'server' => $server->getKey(),
                'status' => 'started',
            ]),
            'completed' => URL::temporarySignedRoute('servers.provision.callback', $expiresAt, [
                'server' => $server->getKey(),
                'status' => 'completed',
            ]),
        ];
    }
}
