<?php

namespace App\Services;

use App\Enums\DatabaseType;
use App\Models\Server;

class DatabaseConfigurationService
{
    /**
     * Get all available database types (excluding cache/queue services)
     */
    public function getAvailableDatabases(?string $osCodename = null): array
    {
        return [
            DatabaseType::MySQL->value => [
                'name' => 'MySQL',
                'description' => 'Widely adopted relational database trusted for PHP applications.',
                'versions' => [
                    '8.0' => 'MySQL 8.0',
                ],
                'default_version' => '8.0',
                'default_port' => 3306,
            ],
            DatabaseType::MariaDB->value => [
                'name' => 'MariaDB',
                'description' => 'High-performance MySQL-compatible database',
                'versions' => [
                    '11.4' => 'MariaDB 11.4 LTS',
                    '10.11' => 'MariaDB 10.11 LTS',
                ],
                'default_version' => '11.4',
                'default_port' => 3306,
            ],
            DatabaseType::PostgreSQL->value => [
                'name' => 'PostgreSQL',
                'description' => 'Advanced open-source relational database with strong SQL compliance.',
                'versions' => [
                    '16' => 'PostgreSQL 16',
                ],
                'default_version' => '16',
                'default_port' => 5432,
            ],
        ];
    }

    /**
     * Get all available cache/queue services
     */
    public function getAvailableCacheQueue(?string $osCodename = null): array
    {
        return [
            DatabaseType::Redis->value => [
                'name' => 'Redis',
                'description' => 'In-memory data structure store for caching and queuing.',
                'versions' => [
                    '7.2' => 'Redis 7.2',
                    '7.0' => 'Redis 7.0',
                    '6.2' => 'Redis 6.2',
                ],
                'default_version' => '7.2',
                'default_port' => 6379,
            ],
        ];
    }

    /**
     * Get all available types (databases + cache/queue services)
     * @deprecated Use getAvailableDatabases() or getAvailableCacheQueue() instead
     */
    public function getAvailableTypes(?string $osCodename = null): array
    {
        return array_merge(
            $this->getAvailableDatabases($osCodename),
            $this->getAvailableCacheQueue($osCodename)
        );
    }

    public function getTypeConfiguration(DatabaseType $type): array
    {
        $configs = $this->getAvailableTypes();

        return $configs[$type->value] ?? [];
    }

    public function getDefaultPort(DatabaseType $type): int
    {
        return $this->getTypeConfiguration($type)['default_port'] ?? 3306;
    }

    public function getDefaultVersion(DatabaseType $type): string
    {
        return $this->getTypeConfiguration($type)['default_version'] ?? '8.0';
    }

    /**
     * Find the next available port for a database type on the given server.
     * Starts with the default port and increments until an unused port is found.
     */
    public function getNextAvailablePort(Server $server, DatabaseType $type, ?int $requestedPort = null): int
    {
        // If a specific port is requested, validate it's available
        if ($requestedPort !== null) {
            $isPortTaken = $server->databases()
                ->where('port', $requestedPort)
                ->exists();

            if (! $isPortTaken) {
                return $requestedPort;
            }

            // Port is taken, fall through to find next available
        }

        // Get all used ports on this server
        $usedPorts = $server->databases()
            ->pluck('port')
            ->toArray();

        // Start with default port for this database type
        $port = $this->getDefaultPort($type);

        // Keep incrementing until we find an available port
        while (in_array($port, $usedPorts)) {
            $port++;
        }

        return $port;
    }
}
