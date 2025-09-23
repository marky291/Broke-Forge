<?php

namespace App\Packages\Credentials;

use App\Models\Server;
use Illuminate\Support\Facades\Cache;

class TemporaryCredentialCache
{
    public static function rootPassword(Server $server): string
    {
        $cacheKey = self::cacheKey($server);

        return Cache::rememberForever($cacheKey, static fn () => self::generatePassword());
    }

    public static function forgetRootPassword(Server $server): void
    {
        Cache::forget(self::cacheKey($server));
    }

    protected static function cacheKey(Server $server): string
    {
        return sprintf('servers:%s:root_password', $server->getKey());
    }

    protected static function generatePassword(int $length = 24): string
    {
        // Limit to URL-safe characters to make display and copy simple.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $alphabetLength = strlen($alphabet) - 1;

        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $alphabetLength)];
        }

        return $password;
    }
}
