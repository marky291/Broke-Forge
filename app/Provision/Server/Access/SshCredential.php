<?php

namespace App\Provision\Server\Access;

interface SshCredential
{
    public function user(): string;

    public function publicKey(): string;

    public function privateKey(): string;
}
