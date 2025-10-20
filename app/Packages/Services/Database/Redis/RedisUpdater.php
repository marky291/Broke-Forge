<?php

namespace App\Packages\Services\Database\Redis;

use App\Enums\DatabaseStatus;
use App\Packages\Base\PackageInstaller;

class RedisUpdater extends PackageInstaller implements \App\Packages\Base\ServerPackage
{
    public function execute(string $targetVersion): void
    {
        $database = $this->server->databases()->where('type', 'redis')->latest()->first();
        $port = $database?->port ?? 6379;
        $password = $database?->root_password ?? bin2hex(random_bytes(16));

        $this->install($this->commands($targetVersion, $port, $password));
    }

    protected function commands(string $targetVersion, int $port, string $password): array
    {
        $backupDir = '/var/backups/redis';

        return [
            "mkdir -p {$backupDir}",

            // Backup Redis data
            "redis-cli -p {$port} -a {$password} --rdb {$backupDir}/dump_before_update_$(date +%Y%m%d_%H%M%S).rdb 2>/dev/null || echo 'Backup skipped'",

            // Stop Redis service
            'systemctl stop redis-server',

            // Update package lists
            'DEBIAN_FRONTEND=noninteractive apt-get update -y',

            // Upgrade Redis
            'DEBIAN_FRONTEND=noninteractive apt-get install --only-upgrade -y redis-server',

            // Start Redis service
            'systemctl start redis-server',

            // Verify Redis is running with new version
            'systemctl status redis-server --no-pager',
            "redis-cli -p {$port} -a {$password} ping",
            "redis-cli -p {$port} -a {$password} info server | grep redis_version",

            fn () => $this->server->databases()->where('type', 'redis')->latest()->first()?->update([
                'status' => DatabaseStatus::Active->value,
                'version' => $targetVersion,
            ]),

        ];
    }
}
