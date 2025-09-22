<?php

namespace App\Packages\Credentials;

class WorkerCredential implements SshCredential
{
    public function user(): string
    {
        return 'worker';
    }

    public function publicKey(): string
    {
        return __DIR__.'/Keys/ssh_key.pub';
    }

    public function privateKey(): string
    {
        return __DIR__.'/Keys/ssh_key';
    }
}
