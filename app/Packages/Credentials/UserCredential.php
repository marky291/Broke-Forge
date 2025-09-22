<?php

namespace App\Packages\Credentials;

class UserCredential implements SshCredential
{
    public function user(): string
    {
        return str(config('app.name'))->lower()->slug();
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
