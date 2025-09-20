<?php

namespace App\Provision\Database\MySQL;

use App\Provision\Enums\ExecutableUser;
use App\Provision\Enums\ServiceType;
use App\Provision\InstallableService;
use App\Provision\Milestones;

/**
 * MySQL Database Server Provisioning Class
 *
 * Handles installation and uninstallation of MySQL server with progress tracking
 * using ProvisionEvent for real-time status updates.
 */
class MySqlProvision extends InstallableService
{
    protected function serviceType(): string
    {
        return ServiceType::DATABASE;
    }

    protected function milestones(): Milestones
    {
        return new MySqlProvisionMilestones;
    }

    protected function executableUser(): ExecutableUser
    {
        return ExecutableUser::RootUser;
    }

    /**
     * Provision MySQL server installation commands
     */
    public function provision(): void
    {
        $rootPassword = bin2hex(random_bytes(16));

        $this->install($this->commands($rootPassword));
    }

    protected function commands(string $rootPassword): array
    {
        return [
            // Update package lists
            'DEBIAN_FRONTEND=noninteractive apt-get update -y',
            $this->track(MySqlProvisionMilestones::UPDATE_PACKAGES),

            // Install prerequisites
            'DEBIAN_FRONTEND=noninteractive apt-get install -y ca-certificates curl gnupg lsb-release software-properties-common',
            $this->track(MySqlProvisionMilestones::INSTALL_PREREQUISITES),

            // Set MySQL root password before installation to avoid interactive prompts
            "echo 'mysql-server mysql-server/root_password password {$rootPassword}' | debconf-set-selections",
            "echo 'mysql-server mysql-server/root_password_again password {$rootPassword}' | debconf-set-selections",
            $this->track(MySqlProvisionMilestones::CONFIGURE_ROOT_PASSWORD),

            // Install MySQL server
            'DEBIAN_FRONTEND=noninteractive apt-get install -y mysql-server mysql-client',
            $this->track(MySqlProvisionMilestones::INSTALL_MYSQL),

            // Start and enable MySQL service
            'systemctl enable --now mysql',
            $this->track(MySqlProvisionMilestones::START_SERVICE),

            // Secure MySQL installation
            "mysql -u root -p{$rootPassword} -e \"DELETE FROM mysql.user WHERE User=''; DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1'); DROP DATABASE IF EXISTS test; DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%'; FLUSH PRIVILEGES;\"",
            $this->track(MySqlProvisionMilestones::SECURE_INSTALLATION),

            // Create backup directory
            'mkdir -p /var/backups/mysql',
            'chown mysql:mysql /var/backups/mysql',
            $this->track(MySqlProvisionMilestones::CREATE_BACKUP_DIRECTORY),

            // Configure MySQL for remote access (optional - modify bind-address)
            "sed -i 's/bind-address.*/bind-address = 0.0.0.0/' /etc/mysql/mysql.conf.d/mysqld.cnf || true",
            $this->track(MySqlProvisionMilestones::CONFIGURE_REMOTE_ACCESS),

            // Restart MySQL to apply configuration changes
            'systemctl restart mysql',
            $this->track(MySqlProvisionMilestones::RESTART_SERVICE),

            // Open MySQL port in firewall if ufw is active
            'ufw allow 3306/tcp >/dev/null 2>&1 || true',
            $this->track(MySqlProvisionMilestones::CONFIGURE_FIREWALL),

            // Verify MySQL is running
            'systemctl status mysql --no-pager',
            "mysql -u root -p{$rootPassword} -e 'SELECT VERSION();'",
            $this->track(MySqlProvisionMilestones::INSTALLATION_COMPLETE),
        ];
    }
}
