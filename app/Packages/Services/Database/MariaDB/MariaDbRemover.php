<?php

namespace App\Packages\Services\Database\MariaDB;

use App\Packages\Base\Milestones;
use App\Packages\Base\PackageRemover;
use App\Packages\Enums\CredentialType;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

/**
 * MariaDB Database Server Removal Class
 *
 * Handles safe removal of MariaDB server with progress tracking
 */
class MariaDbRemover extends PackageRemover implements \App\Packages\Base\ServerPackage
{
    public function credentialType(): CredentialType
    {
        return CredentialType::Root;
    }

    public function packageName(): PackageName
    {
        return PackageName::MariaDb;
    }

    public function packageType(): PackageType
    {
        return PackageType::Database;
    }

    public function milestones(): Milestones
    {
        return new MariaDbRemoverMilestones;
    }

    /**
     * Execute the MariaDB server removal
     */
    public function execute(): void
    {
        $this->remove($this->commands());
    }

    protected function commands(): array
    {
        return [
            // Stop MariaDB service
            'systemctl stop mariadb >/dev/null 2>&1 || true',
            'systemctl disable mariadb >/dev/null 2>&1 || true',
            $this->track(MariaDbRemoverMilestones::STOP_SERVICE),

            // Backup databases before removal
            'mkdir -p /var/backups/mariadb-removal-$(date +%Y%m%d-%H%M%S) >/dev/null 2>&1 || true',
            'mariadb-dump --all-databases > /var/backups/mariadb-removal-$(date +%Y%m%d-%H%M%S)/all-databases.sql 2>/dev/null || true',
            $this->track(MariaDbRemoverMilestones::BACKUP_DATABASES),

            // Remove MariaDB packages
            'DEBIAN_FRONTEND=noninteractive apt-get remove -y --purge mariadb-server mariadb-client mariadb-common mariadb-server-core-* mariadb-client-core-*',
            'DEBIAN_FRONTEND=noninteractive apt-get autoremove -y',
            $this->track(MariaDbRemoverMilestones::REMOVE_PACKAGES),

            // Remove MariaDB data directories
            'rm -rf /var/lib/mysql',
            'rm -rf /var/log/mysql',
            'rm -rf /etc/mysql',
            $this->track(MariaDbRemoverMilestones::REMOVE_DATA_DIRECTORIES),

            // Remove MariaDB repository
            'rm -f /etc/apt/sources.list.d/mariadb.list',
            'rm -f /usr/share/keyrings/mariadb-keyring.gpg',
            $this->track(MariaDbRemoverMilestones::REMOVE_REPOSITORY),

            // Remove MySQL user and group
            'userdel mysql >/dev/null 2>&1 || true',
            'groupdel mysql >/dev/null 2>&1 || true',
            $this->track(MariaDbRemoverMilestones::REMOVE_USER_GROUP),

            // Close MariaDB port in firewall
            'ufw delete allow 3306/tcp >/dev/null 2>&1 || true',
            $this->track(MariaDbRemoverMilestones::UPDATE_FIREWALL),

            // Delete database record
            fn () => $this->server->databases()->delete(),

            // Clean up package cache
            'apt-get clean',
            $this->track(MariaDbRemoverMilestones::UNINSTALLATION_COMPLETE),
        ];
    }
}
