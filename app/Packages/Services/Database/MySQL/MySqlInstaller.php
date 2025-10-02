<?php

namespace App\Packages\Services\Database\MySQL;

use App\Enums\DatabaseStatus;
use App\Enums\DatabaseType;
use App\Models\ServerDatabase;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageInstaller;
use App\Packages\Enums\CredentialType;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

/**
 * MySQL Database Server Installation Class
 *
 * Handles installation of MySQL server with progress tracking
 * using ServerPackageEvent for real-time status updates
 */
class MySqlInstaller extends PackageInstaller implements \App\Packages\Base\ServerPackage
{
    public function milestones(): Milestones
    {
        return new MySqlInstallerMilestones;
    }

    public function credentialType(): CredentialType
    {
        return CredentialType::Root;
    }

    /**
     * Execute the MySQL server installation
     */
    public function execute(): void
    {
        $rootPassword = bin2hex(random_bytes(16));

        $this->install($this->commands($rootPassword));
    }

    protected function commands(string $rootPassword): array
    {
        return [
            // Update package lists
            'DEBIAN_FRONTEND=noninteractive apt-get update -y',
            $this->track(MySqlInstallerMilestones::UPDATE_PACKAGES),

            // Install prerequisites
            'DEBIAN_FRONTEND=noninteractive apt-get install -y ca-certificates curl gnupg lsb-release software-properties-common',
            $this->track(MySqlInstallerMilestones::INSTALL_PREREQUISITES),

            // Set MySQL root password before installation to avoid interactive prompts
            "echo 'mysql-server mysql-server/root_password password {$rootPassword}' | debconf-set-selections",
            "echo 'mysql-server mysql-server/root_password_again password {$rootPassword}' | debconf-set-selections",
            $this->track(MySqlInstallerMilestones::CONFIGURE_ROOT_PASSWORD),

            // Install MySQL server
            'DEBIAN_FRONTEND=noninteractive apt-get install -y mysql-server mysql-client',
            $this->track(MySqlInstallerMilestones::INSTALL_MYSQL),

            // Start and enable MySQL service
            'systemctl enable --now mysql',
            $this->track(MySqlInstallerMilestones::START_SERVICE),

            // Secure MySQL installation
            "mysql -u root -p{$rootPassword} -e \"DELETE FROM mysql.user WHERE User=''; DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1'); DROP DATABASE IF EXISTS test; DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%'; FLUSH PRIVILEGES;\"",
            $this->track(MySqlInstallerMilestones::SECURE_INSTALLATION),

            // Create backup directory
            'mkdir -p /var/backups/mysql',
            'chown mysql:mysql /var/backups/mysql',
            $this->track(MySqlInstallerMilestones::CREATE_BACKUP_DIRECTORY),

            // Configure MySQL for remote access (optional - modify bind-address)
            "sed -i 's/bind-address.*/bind-address = 0.0.0.0/' /etc/mysql/mysql.conf.d/mysqld.cnf || true",
            $this->track(MySqlInstallerMilestones::CONFIGURE_REMOTE_ACCESS),

            // Restart MySQL to apply configuration changes
            'systemctl restart mysql',
            $this->track(MySqlInstallerMilestones::RESTART_SERVICE),

            // Open MySQL port in firewall if ufw is active
            'ufw allow 3306/tcp >/dev/null 2>&1 || true',
            $this->track(MySqlInstallerMilestones::CONFIGURE_FIREWALL),

            // Verify MySQL is running
            'systemctl status mysql --no-pager',
            "mysql -u root -p{$rootPassword} -e 'SELECT VERSION();'",

            // Save MySQL installation to database
            fn () => ServerDatabase::create([
                'server_id' => $this->server->id,
                'name' => 'mysql',
                'type' => DatabaseType::MySQL->value,
                'version' => '8.0',
                'port' => 3306,
                'status' => DatabaseStatus::Active->value,
                'root_password' => $rootPassword,
            ]),

            $this->track(MySqlInstallerMilestones::INSTALLATION_COMPLETE),
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
}
