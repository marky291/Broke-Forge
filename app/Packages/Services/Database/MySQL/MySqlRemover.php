<?php

namespace App\Packages\Services\Database\MySQL;

use App\Enums\DatabaseStatus;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageRemover;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

/**
 * MySQL Database Server Removal Class
 *
 * Handles safe removal of MySQL server with progress tracking
 */
class MySqlRemover extends PackageRemover implements \App\Packages\Base\ServerPackage
{
    /**
     * Mark MySQL removal as failed in database
     */
    protected function markResourceAsFailed(string $errorMessage): void
    {
        $this->server->databases()->latest()->first()?->update([
            'status' => DatabaseStatus::Failed,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Execute the MySQL server removal
     */
    public function execute(): void
    {
        $this->remove($this->commands());
    }

    protected function commands(): array
    {
        return [
            // Stop MySQL service
            'systemctl stop mysql >/dev/null 2>&1 || true',
            'systemctl disable mysql >/dev/null 2>&1 || true',
            $this->track(MySqlRemoverMilestones::STOP_SERVICE),

            // Backup databases before removal (optional safety measure)
            'mkdir -p /var/backups/mysql-removal-$(date +%Y%m%d-%H%M%S) >/dev/null 2>&1 || true',
            'mysqldump --all-databases > /var/backups/mysql-removal-$(date +%Y%m%d-%H%M%S)/all-databases.sql 2>/dev/null || true',
            $this->track(MySqlRemoverMilestones::BACKUP_DATABASES),

            // Remove MySQL packages
            'DEBIAN_FRONTEND=noninteractive apt-get remove -y --purge mysql-server mysql-client mysql-common mysql-server-core-* mysql-client-core-*',
            'DEBIAN_FRONTEND=noninteractive apt-get autoremove -y',
            $this->track(MySqlRemoverMilestones::REMOVE_PACKAGES),

            // Remove MySQL data directories (be careful - this removes all data!)
            'rm -rf /var/lib/mysql',
            'rm -rf /var/log/mysql',
            'rm -rf /etc/mysql',
            $this->track(MySqlRemoverMilestones::REMOVE_DATA_DIRECTORIES),

            // Remove MySQL user and group
            'userdel mysql >/dev/null 2>&1 || true',
            'groupdel mysql >/dev/null 2>&1 || true',
            $this->track(MySqlRemoverMilestones::REMOVE_USER_GROUP),

            // Close MySQL port in firewall
            'ufw delete allow 3306/tcp >/dev/null 2>&1 || true',
            $this->track(MySqlRemoverMilestones::UPDATE_FIREWALL),

            // Remove database record
            fn () => $this->server->databases()->delete(),

            // Clean up package cache
            'apt-get clean',
            $this->track(MySqlRemoverMilestones::UNINSTALLATION_COMPLETE),
        ];
    }

    public function packageName(): PackageName
    {
        return PackageName::MySql80;
    }

    public function packageType(): PackageType
    {
        return PackageType::Database;
    }

    public function milestones(): Milestones
    {
        return new MySqlRemoverMilestones;
    }
}
