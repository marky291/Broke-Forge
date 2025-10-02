<?php

namespace App\Packages\Services\Credential;

use App\Models\Server;
use App\Packages\Enums\CredentialType;
use Spatie\Ssh\Ssh;

/**
 * SSH Connection Builder
 *
 * Service for creating authenticated SSH connections using server credentials.
 * Handles private key deployment and connection configuration.
 */
class SshConnectionBuilder
{
    /**
     * Keeps temp key files alive until after SSH execution.
     * This prevents the destructor from deleting files before SSH reads them.
     */
    private static array $activeTempFiles = [];

    /**
     * Create an authenticated SSH connection for the server.
     *
     * @param  Server  $server  The server to connect to
     * @param  CredentialType  $credentialType  The credential type to use
     * @return Ssh Configured SSH connection
     *
     * @throws \RuntimeException If credential not found or connection cannot be created
     */
    public function build(Server $server, CredentialType $credentialType): Ssh
    {
        $credential = $server->credentials()
            ->where('credential_type', $credentialType->value)
            ->first();

        if (! $credential) {
            throw new \RuntimeException(
                "No {$credentialType->value} credential found for server #{$server->id}. ".
                'Ensure provisioning completed successfully.'
            );
        }

        // Create temporary key file for SSH to use
        $tempKeyFile = new TempKeyFile(
            $credential->private_key,
            $server->id,
            $credentialType->value
        );

        // Store reference to prevent premature destruction
        // Will be cleaned up at script end
        self::$activeTempFiles[] = $tempKeyFile;

        // Create SSH connection with private key
        return Ssh::create($credentialType->username(), $server->public_ip, $server->ssh_port)
            ->usePrivateKey($tempKeyFile->path())
            ->disableStrictHostKeyChecking();
    }
}
