<?php

namespace App\Packages\Services\Database\MySQL;

use App\Enums\DatabaseStatus;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageInstaller;
use App\Packages\Enums\CredentialType;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

class MySqlUpdater extends PackageInstaller implements \App\Packages\Base\ServerPackage
{
    public function milestones(): Milestones
    {
        return new MySqlUpdaterMilestones;
    }

    public function credentialType(): CredentialType
    {
        return CredentialType::Root;
    }

    public function packageName(): PackageName
    {
        return PackageName::MySQL;
    }

    public function packageType(): PackageType
    {
        return PackageType::Database;
    }

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
            $this->track(MySqlUpdaterMilestones::BACKUP_DATABASE),

            'systemctl stop mysql',
            $this->track(MySqlUpdaterMilestones::STOP_SERVICE),

            'DEBIAN_FRONTEND=noninteractive apt-get update -y',
            $this->track(MySqlUpdaterMilestones::UPDATE_PACKAGES),

            // Remove and reinstall MySQL to switch versions
            'DEBIAN_FRONTEND=noninteractive apt-get remove -y --purge mysql-server mysql-client mysql-common',

            // Clean up old backup data directories from previous failed attempts
            'rm -rf /var/lib/mysql-*',

            // Remove version flag to prevent dpkg from trying to rename data directory
            'rm -f /var/lib/mysql/debian-*.flag',

            'DEBIAN_FRONTEND=noninteractive apt-get update -y',
            'DEBIAN_FRONTEND=noninteractive apt-get install -y mysql-server mysql-client',
            $this->track(MySqlUpdaterMilestones::UPGRADE_MYSQL),

            'systemctl enable --now mysql',
            $this->track(MySqlUpdaterMilestones::START_SERVICE),

            "mysql_upgrade --password='{$rootPassword}' --force 2>/dev/null || echo 'mysql_upgrade skipped (deprecated in MySQL 8.0+)'",
            $this->track(MySqlUpdaterMilestones::RUN_MYSQL_UPGRADE),

            'systemctl status mysql --no-pager',
            "mysql --password='{$rootPassword}' -e 'SELECT VERSION();' 2>/dev/null",
            $this->track(MySqlUpdaterMilestones::VERIFY_UPDATE),

            fn () => $this->server->databases()->latest()->first()?->update([
                'status' => DatabaseStatus::Active->value,
                'version' => $targetVersion,
            ]),

            $this->track(MySqlUpdaterMilestones::UPDATE_COMPLETE),
        ];
    }
}
