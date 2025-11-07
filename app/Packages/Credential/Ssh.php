<?php

namespace App\Packages\Credential;

use App\Models\Server;
use Spatie\Ssh\Ssh as SpatieProcess;

/**
 * SSH Connection Service
 *
 * Simple, unified service for creating authenticated SSH connections.
 * Handles credential lookup, temp key file management, and platform-specific SSH creation.
 */
class Ssh
{
    /**
     * Temporary key files created during this request.
     * Cleaned up automatically via shutdown function.
     */
    private static array $tempFiles = [];

    /**
     * Track if shutdown function has been registered
     */
    private static bool $shutdownRegistered = false;

    /**
     * Create an authenticated SSH connection to a server.
     *
     * @param  Server  $server  The server to connect to
     * @param  string  $user  The SSH user ('root' or 'brokeforge')
     * @return SpatieProcess Configured SSH connection ready to execute commands
     *
     * @throws \RuntimeException If credential not found
     */
    public function connect(Server $server, string $user): SpatieProcess
    {
        // 1. Fetch credential from database
        $credential = $server->credentials()
            ->where('user', $user)
            ->first();

        if (! $credential) {
            throw new \RuntimeException(
                "No {$user} credential found for server #{$server->id}. ".
                'Ensure provisioning completed successfully.'
            );
        }

        // 2. Create temporary key file for SSH to use
        $keyFilePath = $this->createTempKeyFile(
            $credential->private_key,
            $server->id,
            $user
        );

        // 3. Create platform-appropriate SSH instance
        $sshClass = PHP_OS_FAMILY === 'Windows'
            ? WindowsCompatibleSsh::class
            : SpatieProcess::class;

        $ssh = $sshClass::create($user, $server->public_ip, $server->ssh_port)
            ->usePrivateKey($keyFilePath)
            ->disableStrictHostKeyChecking()
            ->enableQuietMode();

        // 4. Configure SSH timeouts and keep-alive
        $ssh->addExtraOption('-o ConnectTimeout=60')
            ->addExtraOption('-o ServerAliveInterval=15')
            ->addExtraOption('-o ServerAliveCountMax=3');

        return $ssh;
    }

    /**
     * Create a temporary SSH key file.
     *
     * The file will be automatically deleted at the end of the request.
     *
     * @param  string  $privateKey  The private key content
     * @param  int  $serverId  Server ID for unique naming
     * @param  string  $user  SSH user for unique naming
     * @return string Absolute path to the created temp file
     *
     * @throws \RuntimeException If file cannot be created
     */
    private function createTempKeyFile(string $privateKey, int $serverId, string $user): string
    {
        $tempDir = sys_get_temp_dir();
        $path = sprintf(
            '%s%sssh_key_%d_%s_%s',
            $tempDir,
            DIRECTORY_SEPARATOR,
            $serverId,
            $user,
            uniqid()
        );

        if (file_put_contents($path, $privateKey) === false) {
            throw new \RuntimeException("Failed to write private key to temporary file: {$path}");
        }

        chmod($path, 0600);

        // Track for cleanup
        self::$tempFiles[] = $path;

        // Register cleanup function once
        if (! self::$shutdownRegistered) {
            register_shutdown_function([self::class, 'cleanup']);
            self::$shutdownRegistered = true;
        }

        return $path;
    }

    /**
     * Clean up all temporary key files.
     *
     * Called automatically at shutdown. Can also be called manually if needed.
     */
    public static function cleanup(): void
    {
        foreach (self::$tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        self::$tempFiles = [];
    }
}
