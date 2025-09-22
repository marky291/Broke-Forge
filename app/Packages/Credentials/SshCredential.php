<?php

namespace App\Packages\Credentials;

interface SshCredential
{
    public function user(): string;

    public function publicKey(): string;

    public function privateKey(): string;
}
