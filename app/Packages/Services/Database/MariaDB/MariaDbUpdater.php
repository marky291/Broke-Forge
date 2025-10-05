<?php

namespace App\Packages\Services\Database\MariaDB;

use App\Enums\DatabaseStatus;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageInstaller;
use App\Packages\Enums\CredentialType;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

class MariaDbUpdater extends PackageInstaller implements \App\Packages\Base\ServerPackage
{
    public function milestones(): Milestones
    {
        return new MariaDbUpdaterMilestones;
    }

    public function credentialType(): CredentialType
    {
        return CredentialType::Root;
    }

    public function packageName(): PackageName
    {
        return PackageName::MariaDB;
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
        $backupDir = '/var/backups/mariadb';
        $ubuntuCodename = $this->server->os_codename ?? 'jammy';

        return [
            "mkdir -p {$backupDir}",
            "mysqldump --all-databases --password='{$rootPassword}' > {$backupDir}/backup_before_update_$(date +%Y%m%d_%H%M%S).sql 2>/dev/null || echo 'Backup skipped'",
            $this->track(MariaDbUpdaterMilestones::BACKUP_DATABASE),

            'systemctl stop mariadb 2>/dev/null || true',
            $this->track(MariaDbUpdaterMilestones::STOP_SERVICE),

            'DEBIAN_FRONTEND=noninteractive apt-get update -y',
            $this->track(MariaDbUpdaterMilestones::UPDATE_PACKAGES),

            // Remove old MariaDB packages and configure new repository for target version
            'DEBIAN_FRONTEND=noninteractive apt-get remove -y --purge mariadb-server mariadb-client mariadb-common',

            // Clean up old backup data directories from previous failed attempts
            'rm -rf /var/lib/mysql-*',

            // Remove version flag to prevent dpkg from trying to rename data directory
            'rm -f /var/lib/mysql/debian-*.flag',

            'rm -f /etc/apt/sources.list.d/mariadb.list',
            'rm -f /usr/share/keyrings/mariadb-keyring.gpg',
            'curl -fsSL https://mariadb.org/mariadb_release_signing_key.asc | gpg --batch --yes --dearmor -o /usr/share/keyrings/mariadb-keyring.gpg',
            "echo \"deb [signed-by=/usr/share/keyrings/mariadb-keyring.gpg] https://mirror.rackspace.com/mariadb/repo/{$targetVersion}/ubuntu {$ubuntuCodename} main\" > /etc/apt/sources.list.d/mariadb.list",
            'DEBIAN_FRONTEND=noninteractive apt-get update -y',
            'DEBIAN_FRONTEND=noninteractive apt-get install -y mariadb-server mariadb-client',
            $this->track(MariaDbUpdaterMilestones::UPGRADE_MARIADB),

            'systemctl enable --now mariadb',
            $this->track(MariaDbUpdaterMilestones::START_SERVICE),

            "mysql_upgrade --password='{$rootPassword}' --force 2>/dev/null || echo 'mysql_upgrade completed or skipped'",
            $this->track(MariaDbUpdaterMilestones::RUN_MYSQL_UPGRADE),

            'systemctl status mariadb --no-pager',
            "mysql --password='{$rootPassword}' -e 'SELECT VERSION();' 2>/dev/null",
            $this->track(MariaDbUpdaterMilestones::VERIFY_UPDATE),

            fn () => $this->server->databases()->latest()->first()?->update([
                'status' => DatabaseStatus::Active->value,
                'version' => $targetVersion,
            ]),

            $this->track(MariaDbUpdaterMilestones::UPDATE_COMPLETE),
        ];
    }
}
