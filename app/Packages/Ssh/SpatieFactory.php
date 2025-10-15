<?php

namespace App\Packages\Ssh;

use App\Packages\Contracts\SshFactory;
use App\Packages\Services\WindowsCompatibleSsh;
use Spatie\Ssh\Ssh;

/**
 * Spatie SSH Factory
 *
 * Creates appropriate Ssh instances (Windows-compatible or standard)
 */
class SpatieFactory implements SshFactory
{
    /**
     * Create an SSH connection
     */
    public function create(string $user, string $host, int $port = 22): Ssh
    {
        // Use Windows-compatible SSH wrapper on Windows to avoid heredoc issues
        $sshClass = PHP_OS_FAMILY === 'Windows' ? WindowsCompatibleSsh::class : Ssh::class;

        return $sshClass::create($user, $host, $port);
    }
}
