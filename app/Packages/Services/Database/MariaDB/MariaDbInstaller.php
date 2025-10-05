<?php

namespace App\Packages\Services\Database\MariaDB;

use App\Enums\DatabaseStatus;
use App\Enums\DatabaseType;
use App\Models\ServerDatabase;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageInstaller;
use App\Packages\Enums\CredentialType;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

/**
 * MariaDB Database Server Installation Class
 *
 * Handles installation of MariaDB server with progress tracking
 */
class MariaDbInstaller extends PackageInstaller implements \App\Packages\Base\ServerPackage
{
    public function milestones(): Milestones
    {
        return new MariaDbInstallerMilestones;
    }

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

    /**
     * Execute the MariaDB server installation
     */
    public function execute(): void
    {
        $database = $this->server->databases()->latest()->first();
        $version = $database?->version ?? '11.4';
        $rootPassword = $database?->root_password ?? bin2hex(random_bytes(16));

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
            $this->track(MariaDbInstallerMilestones::UPDATE_PACKAGES),

            // Install prerequisites
            'DEBIAN_FRONTEND=noninteractive apt-get install -y ca-certificates curl gnupg lsb-release software-properties-common',
            $this->track(MariaDbInstallerMilestones::INSTALL_PREREQUISITES),

            // Add MariaDB repository
            'rm -f /usr/share/keyrings/mariadb-keyring.gpg',
            "curl -fsSL https://mariadb.org/mariadb_release_signing_key.asc | gpg --batch --yes --dearmor -o /usr/share/keyrings/mariadb-keyring.gpg",
            "echo \"deb [signed-by=/usr/share/keyrings/mariadb-keyring.gpg] https://mirror.rackspace.com/mariadb/repo/{$repoVersion}/ubuntu {$ubuntuCodename} main\" > /etc/apt/sources.list.d/mariadb.list",
            'DEBIAN_FRONTEND=noninteractive apt-get update -y',
            $this->track(MariaDbInstallerMilestones::ADD_REPOSITORY),

            // Set MariaDB root password before installation
            "echo 'mariadb-server mysql-server/root_password password {$rootPassword}' | debconf-set-selections",
            "echo 'mariadb-server mysql-server/root_password_again password {$rootPassword}' | debconf-set-selections",
            $this->track(MariaDbInstallerMilestones::CONFIGURE_ROOT_PASSWORD),

            // Install MariaDB server
            'DEBIAN_FRONTEND=noninteractive apt-get install -y mariadb-server mariadb-client',
            $this->track(MariaDbInstallerMilestones::INSTALL_MARIADB),

            // Start and enable MariaDB service
            'systemctl enable --now mariadb',
            $this->track(MariaDbInstallerMilestones::START_SERVICE),

            // Secure MariaDB installation
            "mariadb -u root -p{$rootPassword} -e \"DELETE FROM mysql.user WHERE User=''; DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1'); DROP DATABASE IF EXISTS test; DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%'; FLUSH PRIVILEGES;\"",
            $this->track(MariaDbInstallerMilestones::SECURE_INSTALLATION),

            // Create backup directory
            'mkdir -p /var/backups/mariadb',
            'chown mysql:mysql /var/backups/mariadb',
            $this->track(MariaDbInstallerMilestones::CREATE_BACKUP_DIRECTORY),

            // Configure MariaDB for remote access
            "sed -i 's/bind-address.*/bind-address = 0.0.0.0/' /etc/mysql/mariadb.conf.d/50-server.cnf || true",
            $this->track(MariaDbInstallerMilestones::CONFIGURE_REMOTE_ACCESS),

            // Restart MariaDB to apply configuration changes
            'systemctl restart mariadb',
            $this->track(MariaDbInstallerMilestones::RESTART_SERVICE),

            // Open MariaDB port in firewall if ufw is active
            'ufw allow 3306/tcp >/dev/null 2>&1 || true',
            $this->track(MariaDbInstallerMilestones::CONFIGURE_FIREWALL),

            // Verify MariaDB is running
            'systemctl status mariadb --no-pager',
            "mariadb -u root -p{$rootPassword} -e 'SELECT VERSION();'",

            // Update database record
            fn () => $this->server->databases()->latest()->first()?->update([
                'status' => DatabaseStatus::Active->value,
                'version' => $version,
                'root_password' => $rootPassword,
            ]),

            $this->track(MariaDbInstallerMilestones::INSTALLATION_COMPLETE),
        ];
    }
}
