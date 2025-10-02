<?php

namespace App\Packages;

use App\Models\Server;
use App\Models\ServerCredential;
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

        // Generate unique SSH credentials for this server (one per credential type)
        // These replace the old shared Keys/ssh_key with per-server encrypted keys

        $rootCredential = $server->credential('root')
            ?? ServerCredential::generateKeyPair($server, 'root');

        $userCredential = $server->credential('user')
            ?? ServerCredential::generateKeyPair($server, 'user');

        $workerCredential = $server->credential('worker')
            ?? ServerCredential::generateKeyPair($server, 'worker');

        $appName = config('app.name');

        $callbackUrls = $this->buildCallbackUrls($server);

        return view('scripts.provision_setup_x64', [
            'sshUser' => $sshUser,
            'appUser' => $appUser,
            'rootPrivateKeyContent' => $rootCredential->private_key,
            'rootPublicKeyContent' => $rootCredential->public_key,
            'userPrivateKeyContent' => $userCredential->private_key,
            'userPublicKeyContent' => $userCredential->public_key,
            'workerPrivateKeyContent' => $workerCredential->private_key,
            'workerPublicKeyContent' => $workerCredential->public_key,
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
