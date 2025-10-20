<?php

namespace App\Packages\Services\Database\Redis;

use App\Enums\DatabaseStatus;
use App\Packages\Base\PackageInstaller;

/**
 * Redis Server Installation Class
 *
 * Handles installation of Redis server with progress tracking
 * using ServerPackageEvent for real-time status updates
 */
class RedisInstaller extends PackageInstaller implements \App\Packages\Base\ServerPackage
{
    /**
     * Mark Redis installation as failed in database
     */
    protected function markResourceAsFailed(string $errorMessage): void
    {
        $this->server->databases()->where('type', 'redis')->latest()->first()?->update([
            'status' => DatabaseStatus::Failed,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Execute the Redis server installation
     */
    public function execute(): void
    {
        $database = $this->server->databases()->where('type', 'redis')->latest()->first();
        $version = $database?->version ?? '7.2';
        $port = $database?->port ?? 6379;
        $password = $database?->root_password ?? bin2hex(random_bytes(16));

        $this->install($this->commands($version, $port, $password));
    }

    protected function commands(string $version, int $port, string $password): array
    {
        return [
            // Update package lists
            'DEBIAN_FRONTEND=noninteractive apt-get update -y',

            // Install prerequisites
            'DEBIAN_FRONTEND=noninteractive apt-get install -y ca-certificates curl gnupg lsb-release software-properties-common',

            // Install Redis server
            'DEBIAN_FRONTEND=noninteractive apt-get install -y redis-server',

            // Configure Redis for remote access and authentication
            "sed -i 's/^bind .*/bind 0.0.0.0/' /etc/redis/redis.conf",
            "sed -i 's/^# requirepass .*/requirepass {$password}/' /etc/redis/redis.conf",
            "sed -i 's/^port .*/port {$port}/' /etc/redis/redis.conf",
            "sed -i 's/^supervised no/supervised systemd/' /etc/redis/redis.conf",

            // Enable persistence (AOF + RDB)
            "sed -i 's/^appendonly no/appendonly yes/' /etc/redis/redis.conf",

            // Set max memory policy (LRU eviction)
            "echo 'maxmemory-policy allkeys-lru' >> /etc/redis/redis.conf",

            // Create backup directory
            'mkdir -p /var/backups/redis',
            'chown redis:redis /var/backups/redis',

            // Start and enable Redis service
            'systemctl enable --now redis-server',

            // Open Redis port in firewall if ufw is active
            "ufw allow {$port}/tcp >/dev/null 2>&1 || true",

            // Verify Redis is running
            'systemctl status redis-server --no-pager',
            "redis-cli -p {$port} -a {$password} ping",

            // Update database record with installation details
            fn () => $this->server->databases()->where('type', 'redis')->latest()->first()?->update([
                'status' => DatabaseStatus::Active->value,
                'version' => $version,
                'port' => $port,
                'root_password' => $password,
            ]),

        ];
    }
}
