<?php

namespace App\Packages\Services\Database\MySQL;

use App\Packages\Core\Base\PackageInstaller;

/**
 * MySQL Database Server Installation Class
 *
 * Handles installation of MySQL server with progress tracking
 * using ServerPackageEvent for real-time status updates
 */
class MySqlInstaller extends PackageInstaller implements \App\Packages\Core\Base\ServerPackage
{
    /**
     * Execute the MySQL server installation
     */
    public function execute(string $version, string $rootPassword): void
    {
        $this->install($this->commands($version, $rootPassword));
    }

    protected function commands(string $version, string $rootPassword): array
    {
        return [
            // Update package lists
            'DEBIAN_FRONTEND=noninteractive apt-get update -y',

            // Install prerequisites
            'DEBIAN_FRONTEND=noninteractive apt-get install -y ca-certificates curl gnupg lsb-release software-properties-common',

            // Set MySQL root password before installation to avoid interactive prompts
            "echo 'mysql-server mysql-server/root_password password {$rootPassword}' | debconf-set-selections",
            "echo 'mysql-server mysql-server/root_password_again password {$rootPassword}' | debconf-set-selections",

            // Install MySQL server
            'DEBIAN_FRONTEND=noninteractive apt-get install -y mysql-server mysql-client',

            // Start and enable MySQL service
            'systemctl enable --now mysql',

            // Secure MySQL installation
            "mysql -u root -p{$rootPassword} -e \"DELETE FROM mysql.user WHERE User=''; DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1'); DROP DATABASE IF EXISTS test; DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%'; FLUSH PRIVILEGES;\"",

            // Create backup directory
            'mkdir -p /var/backups/mysql',
            'chown mysql:mysql /var/backups/mysql',

            // Configure MySQL for remote access (optional - modify bind-address)
            "sed -i 's/bind-address.*/bind-address = 0.0.0.0/' /etc/mysql/mysql.conf.d/mysqld.cnf || true",

            // Restart MySQL to apply configuration changes
            'systemctl restart mysql',

            // Open MySQL port in firewall if ufw is active
            'ufw allow 3306/tcp >/dev/null 2>&1 || true',

            // Verify MySQL is running
            'systemctl status mysql --no-pager',
            "mysql -u root -p{$rootPassword} -e 'SELECT VERSION();'",

        ];
    }
}
