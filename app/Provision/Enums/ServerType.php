<?php

namespace App\Provision\Enums;

enum ServerType: string
{
    /**
     * Web server with PHP and Nginx
     */
    case WebServer = 'webserver';

    /**
     * Database server
     */
    case DatabaseServer = 'database';

    /**
     * Application server (Node.js, Python, etc)
     */
    case ApplicationServer = 'application';

    /**
     * Cache server (Redis, Memcached)
     */
    case CacheServer = 'cache';

    /**
     * Queue worker server
     */
    case QueueWorker = 'queue';

    /**
     * Get human-readable label for the server type
     */
    public function label(): string
    {
        return match ($this) {
            self::WebServer => 'Web Server',
            self::DatabaseServer => 'Database Server',
            self::ApplicationServer => 'Application Server',
            self::CacheServer => 'Cache Server',
            self::QueueWorker => 'Queue Worker',
        };
    }
}
