<?php

namespace App\Packages\Enums;

class ServiceType
{
    public const DATABASE = 'database';

    public const SERVER = 'server';

    public const WEBSERVER = 'webserver';

    public const SITE = 'site';

    /**
     * Get human-readable labels for service types
     */
    public static function labels(): array
    {
        return [
            self::DATABASE => 'Database',
            self::SERVER => 'Server',
            self::WEBSERVER => 'Web Server',
            self::SITE => 'Site',
        ];
    }

    /**
     * Get label for specific service type
     */
    public static function label(string $serviceType): ?string
    {
        return self::labels()[$serviceType] ?? null;
    }

    /**
     * Get all service type constants
     */
    public static function all(): array
    {
        return [
            self::DATABASE,
            self::SERVER,
            self::WEBSERVER,
            self::SITE,
        ];
    }
}
