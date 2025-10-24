<?php

namespace App\Packages\Services\Database\Redis;

use App\Enums\TaskStatus;
use App\Packages\Base\PackageRemover;

/**
 * Redis Server Removal Class
 *
 * Handles safe removal of Redis server with progress tracking
 */
class RedisRemover extends PackageRemover implements \App\Packages\Base\ServerPackage
{
    /**
     * Mark Redis removal as failed in database
     */
    protected function markResourceAsFailed(string $errorMessage): void
    {
        $this->server->databases()->where('type', 'redis')->latest()->first()?->update([
            'status' => TaskStatus::Failed,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Execute the Redis server removal
     */
    public function execute(): void
    {
        $database = $this->server->databases()->where('type', 'redis')->latest()->first();
        $port = $database?->port ?? 6379;

        $this->remove($this->commands($port));
    }

    protected function commands(int $port): array
    {
        return [
            // Stop Redis service
            'systemctl stop redis-server >/dev/null 2>&1 || true',
            'systemctl disable redis-server >/dev/null 2>&1 || true',

            // Backup Redis data before removal (optional safety measure)
            'mkdir -p /var/backups/redis-removal-$(date +%Y%m%d-%H%M%S) >/dev/null 2>&1 || true',
            'cp -r /var/lib/redis /var/backups/redis-removal-$(date +%Y%m%d-%H%M%S)/ 2>/dev/null || true',

            // Remove Redis packages
            'DEBIAN_FRONTEND=noninteractive apt-get remove -y --purge redis-server redis-tools',
            'DEBIAN_FRONTEND=noninteractive apt-get autoremove -y',

            // Remove Redis data directories (be careful - this removes all data!)
            'rm -rf /var/lib/redis',
            'rm -rf /var/log/redis',
            'rm -rf /etc/redis',

            // Remove Redis user and group
            'userdel redis >/dev/null 2>&1 || true',
            'groupdel redis >/dev/null 2>&1 || true',

            // Close Redis port in firewall
            "ufw delete allow {$port}/tcp >/dev/null 2>&1 || true",

            // Clean up package cache
            'apt-get clean',
        ];
    }

    public function milestones(): Milestones
    {
        return new RedisRemoverMilestones;
    }
}
