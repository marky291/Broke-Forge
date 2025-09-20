<?php

namespace App\Provision\Database\MySQL;

use App\Provision\Enums\ExecutableUser;
use App\Provision\Enums\ServiceType;
use App\Provision\Milestones;
use App\Provision\RemovableService;

class MySqlDeprovision extends RemovableService
{

    protected function serviceType(): string
    {
        return ServiceType::DATABASE;
    }

    protected function milestones(): Milestones
    {
        return new MySqlDeprovisionMilestones;
    }

    protected function executableUser(): ExecutableUser
    {
        return ExecutableUser::RootUser;
    }

    public function commands(): array
    {
        return [
            // Stop MySQL service
            'systemctl stop mysql >/dev/null 2>&1 || true',
            'systemctl disable mysql >/dev/null 2>&1 || true',
            $this->track(MySqlProvisionMilestones::STOP_SERVICE->value),

            // Backup databases before removal (optional safety measure)
            'mkdir -p /var/backups/mysql-removal-$(date +%Y%m%d-%H%M%S) >/dev/null 2>&1 || true',
            'mysqldump --all-databases > /var/backups/mysql-removal-$(date +%Y%m%d-%H%M%S)/all-databases.sql 2>/dev/null || true',
            $this->track(MySqlProvisionMilestones::BACKUP_DATABASES->value),

            // Remove MySQL packages
            'DEBIAN_FRONTEND=noninteractive apt-get remove -y --purge mysql-server mysql-client mysql-common mysql-server-core-* mysql-client-core-*',
            'DEBIAN_FRONTEND=noninteractive apt-get autoremove -y',
            $this->track(MySqlProvisionMilestones::REMOVE_PACKAGES->value),

            // Remove MySQL data directories (be careful - this removes all data!)
            'rm -rf /var/lib/mysql',
            'rm -rf /var/log/mysql',
            'rm -rf /etc/mysql',
            $this->track(MySqlProvisionMilestones::REMOVE_DATA_DIRECTORIES->value),

            // Remove MySQL user and group
            'userdel mysql >/dev/null 2>&1 || true',
            'groupdel mysql >/dev/null 2>&1 || true',
            $this->track(MySqlProvisionMilestones::REMOVE_USER_GROUP->value),

            // Close MySQL port in firewall
            'ufw delete allow 3306/tcp >/dev/null 2>&1 || true',
            $this->track(MySqlProvisionMilestones::UPDATE_FIREWALL->value),

            // Clean up package cache
            'apt-get clean',
            $this->track(MySqlProvisionMilestones::UNINSTALLATION_COMPLETE->value),
        ];
    }
}
