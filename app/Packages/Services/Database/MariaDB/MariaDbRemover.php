<?php

namespace App\Packages\Services\Database\MariaDB;

use App\Enums\DatabaseStatus;
use App\Packages\Base\PackageRemover;

/**
 * MariaDB Database Server Removal Class
 *
 * Handles safe removal of MariaDB server with progress tracking
 */
class MariaDbRemover extends PackageRemover implements \App\Packages\Base\ServerPackage
{
    /**
     * Mark MariaDB removal as failed in database
     */
    protected function markResourceAsFailed(string $errorMessage): void
    {
        $this->server->databases()->latest()->first()?->update([
            'status' => DatabaseStatus::Failed,
            'error_message' => $errorMessage,
        ]);
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

            // Backup databases before removal
            'mkdir -p /var/backups/mariadb-removal-$(date +%Y%m%d-%H%M%S) >/dev/null 2>&1 || true',
            'mariadb-dump --all-databases > /var/backups/mariadb-removal-$(date +%Y%m%d-%H%M%S)/all-databases.sql 2>/dev/null || true',

            // Remove MariaDB packages
            'DEBIAN_FRONTEND=noninteractive apt-get remove -y --purge mariadb-server mariadb-client mariadb-common mariadb-server-core-* mariadb-client-core-*',
            'DEBIAN_FRONTEND=noninteractive apt-get autoremove -y',

            // Remove MariaDB data directories
            'rm -rf /var/lib/mysql',
            'rm -rf /var/log/mysql',
            'rm -rf /etc/mysql',

            // Remove MariaDB repository
            'rm -f /etc/apt/sources.list.d/mariadb.list',
            'rm -f /usr/share/keyrings/mariadb-keyring.gpg',

            // Remove MySQL user and group
            'userdel mysql >/dev/null 2>&1 || true',
            'groupdel mysql >/dev/null 2>&1 || true',

            // Close MariaDB port in firewall
            'ufw delete allow 3306/tcp >/dev/null 2>&1 || true',

            // Delete database record
            fn () => $this->server->databases()->delete(),

            // Clean up package cache
            'apt-get clean',
        ];
    }
}
