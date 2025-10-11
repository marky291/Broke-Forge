<?php

namespace App\Packages;

use App\Models\Server;
use App\Models\ServerCredential;
use App\Packages\Enums\CredentialType;
use Illuminate\Support\Facades\URL;

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
        // Generate unique SSH credentials for this server
        // Two credential types:
        // - Root: for system-level operations (package installs, service management)
        // - BrokeForge: for site-level operations (Git, deployments, app code)

        $rootCredential = $server->credential(CredentialType::Root)
            ?? ServerCredential::generateKeyPair($server, CredentialType::Root);

        $brokeforgeCredential = $server->credential(CredentialType::BrokeForge)
            ?? ServerCredential::generateKeyPair($server, CredentialType::BrokeForge);

        $appName = config('app.name');

        $callbackUrls = $this->buildCallbackUrls($server);

        return view('scripts.provision_setup_x64', [
            'sshUser' => $rootCredential->getUsername(),
            'appUser' => $brokeforgeCredential->getUsername(),
            'rootPrivateKeyContent' => $rootCredential->private_key,
            'rootPublicKeyContent' => $rootCredential->public_key,
            'userPrivateKeyContent' => $brokeforgeCredential->private_key,
            'userPublicKeyContent' => $brokeforgeCredential->public_key,
            'appName' => $appName,
            'sshPort' => $server->ssh_port,
            'callbackUrls' => $callbackUrls,
            'rootPassword' => $rootPassword,
        ])->render();
    }

    /**
     * Build signed callback URLs for provisioning steps.
     */
    protected function buildCallbackUrls(Server $server): array
    {
        $ttlMinutes = max(1, (int) (config('provision.callback_ttl') ?? 60));
        $expiresAt = now()->addMinutes($ttlMinutes);

        return [
            'step' => URL::temporarySignedRoute('servers.provision.step', $expiresAt, [
                'server' => $server->getKey(),
            ]),
        ];
    }
}
