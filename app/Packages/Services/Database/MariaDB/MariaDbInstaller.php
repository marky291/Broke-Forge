<?php

namespace App\Packages\Services\Database\MariaDB;

use App\Packages\Core\Base\PackageInstaller;

/**
 * MariaDB Database Server Installation Class
 *
 * Handles installation of MariaDB server with progress tracking
 */
class MariaDbInstaller extends PackageInstaller implements \App\Packages\Core\Base\ServerPackage
{
    /**
     * Execute the MariaDB server installation
     */
    public function execute(string $version, string $rootPassword): void
    {
        $this->install($this->commands($version, $rootPassword));
    }

    protected function commands(string $version, string $rootPassword): array
    {
        // Map version to repository version (11.4 -> 11.4, 10.11 -> 10.11, etc.)
        $repoVersion = $version;
        $ubuntuCodename = $this->server->os_codename ?? 'jammy';

        return [
            // Fix any broken package states first
            'dpkg --configure -a',
            'DEBIAN_FRONTEND=noninteractive apt-get -f install -y',

            // Clean up any existing MariaDB installations
            'systemctl stop mariadb 2>/dev/null || true',
            'systemctl stop mysql 2>/dev/null || true',
            'DEBIAN_FRONTEND=noninteractive apt-get remove -y --purge mariadb-* mysql-* 2>/dev/null || true',
            'DEBIAN_FRONTEND=noninteractive apt-get autoremove -y',
            'rm -rf /etc/mysql /var/lib/mysql',
            'rm -f /etc/apt/sources.list.d/mariadb.list',

            // Update package lists
            'DEBIAN_FRONTEND=noninteractive apt-get update -y',

            // Install prerequisites
            'DEBIAN_FRONTEND=noninteractive apt-get install -y ca-certificates curl gnupg lsb-release software-properties-common',

            // Add MariaDB repository
            'rm -f /usr/share/keyrings/mariadb-keyring.gpg',
            'curl -fsSL https://mariadb.org/mariadb_release_signing_key.asc | gpg --batch --yes --dearmor -o /usr/share/keyrings/mariadb-keyring.gpg',
            "echo \"deb [signed-by=/usr/share/keyrings/mariadb-keyring.gpg] https://mirror.rackspace.com/mariadb/repo/{$repoVersion}/ubuntu {$ubuntuCodename} main\" > /etc/apt/sources.list.d/mariadb.list",
            'DEBIAN_FRONTEND=noninteractive apt-get update -y',

            // Set MariaDB root password before installation
            "echo 'mariadb-server mysql-server/root_password password {$rootPassword}' | debconf-set-selections",
            "echo 'mariadb-server mysql-server/root_password_again password {$rootPassword}' | debconf-set-selections",

            // Install MariaDB server
            'DEBIAN_FRONTEND=noninteractive apt-get install -y mariadb-server mariadb-client',

            // Start and enable MariaDB service
            'systemctl enable --now mariadb',

            // Secure MariaDB installation
            "mariadb -u root -p{$rootPassword} -e \"DELETE FROM mysql.user WHERE User=''; DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1'); DROP DATABASE IF EXISTS test; DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%'; FLUSH PRIVILEGES;\"",

            // Create backup directory
            'mkdir -p /var/backups/mariadb',
            'chown mysql:mysql /var/backups/mariadb',

            // Configure MariaDB for remote access
            "sed -i 's/bind-address.*/bind-address = 0.0.0.0/' /etc/mysql/mariadb.conf.d/50-server.cnf || true",

            // Restart MariaDB to apply configuration changes
            'systemctl restart mariadb',

            // Open MariaDB port in firewall if ufw is active
            'ufw allow 3306/tcp >/dev/null 2>&1 || true',

            // Verify MariaDB is running
            'systemctl status mariadb --no-pager',
            "mariadb -u root -p{$rootPassword} -e 'SELECT VERSION();'",

        ];
    }
}
