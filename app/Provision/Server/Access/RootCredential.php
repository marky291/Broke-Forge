<?php

namespace App\Provision\Server\Access;

class RootCredential implements SshCredential
{
    public function user(): string
    {
        return 'root';
    }

    public function publicKey(): string
    {
        return __DIR__.'/ssh_key.pub';
    }

    public function privateKey(): string
    {
        return __DIR__.'/ssh_key';
    }
}
