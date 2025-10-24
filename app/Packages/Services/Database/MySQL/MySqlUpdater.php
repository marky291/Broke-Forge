<?php

namespace App\Packages\Services\Database\MySQL;

use App\Enums\TaskStatus;
use App\Packages\Base\PackageInstaller;

class MySqlUpdater extends PackageInstaller implements \App\Packages\Base\ServerPackage
{
    public function execute(string $targetVersion): void
    {
        $database = $this->server->databases()->latest()->first();
        $rootPassword = $database?->root_password ?? bin2hex(random_bytes(16));

        $this->install($this->commands($targetVersion, $rootPassword));
    }

    protected function commands(string $targetVersion, string $rootPassword): array
    {
        $backupDir = '/var/backups/mysql';

        return [
            "mkdir -p {$backupDir}",
            "mysqldump --all-databases --password='{$rootPassword}' > {$backupDir}/backup_before_update_$(date +%Y%m%d_%H%M%S).sql 2>/dev/null || echo 'Backup skipped'",

            'systemctl stop mysql',

            'DEBIAN_FRONTEND=noninteractive apt-get update -y',

            // Remove and reinstall MySQL to switch versions
            'DEBIAN_FRONTEND=noninteractive apt-get remove -y --purge mysql-server mysql-client mysql-common',

            // Clean up old backup data directories from previous failed attempts
            'rm -rf /var/lib/mysql-*',

            // Remove version flag to prevent dpkg from trying to rename data directory
            'rm -f /var/lib/mysql/debian-*.flag',

            'DEBIAN_FRONTEND=noninteractive apt-get update -y',
            'DEBIAN_FRONTEND=noninteractive apt-get install -y mysql-server mysql-client',

            'systemctl enable --now mysql',

            "mysql_upgrade --password='{$rootPassword}' --force 2>/dev/null || echo 'mysql_upgrade skipped (deprecated in MySQL 8.0+)'",

            'systemctl status mysql --no-pager',
            "mysql --password='{$rootPassword}' -e 'SELECT VERSION();' 2>/dev/null",

            fn () => $this->server->databases()->latest()->first()?->update([
                'status' => TaskStatus::Active->value,
                'version' => $targetVersion,
            ]),

        ];
    }
}
