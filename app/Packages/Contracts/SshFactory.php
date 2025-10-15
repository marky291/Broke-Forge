<?php

namespace App\Packages\Contracts;

use Spatie\Ssh\Ssh;

/**
 * SSH Factory Contract
 *
 * Creates SSH connection instances (mockable in tests)
 */
interface SshFactory
{
    /**
     * Create an SSH connection
     */
    public function create(string $user, string $host, int $port = 22): Ssh;
}
