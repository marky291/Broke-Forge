<?php

namespace App\Provision\Database\MySQL;

use App\Provision\Milestones;

class MySqlProvisionMilestones extends Milestones
{
    // Installation milestones
    public const UPDATE_PACKAGES = 'update_packages';
    public const INSTALL_PREREQUISITES = 'install_prerequisites';
    public const CONFIGURE_ROOT_PASSWORD = 'configure_root_password';
    public const INSTALL_MYSQL = 'install_mysql';
    public const START_SERVICE = 'start_service';
    public const SECURE_INSTALLATION = 'secure_installation';
    public const CREATE_BACKUP_DIRECTORY = 'create_backup_directory';
    public const CONFIGURE_REMOTE_ACCESS = 'configure_remote_access';
    public const RESTART_SERVICE = 'restart_service';
    public const CONFIGURE_FIREWALL = 'configure_firewall';
    public const INSTALLATION_COMPLETE = 'installation_complete';

    private const LABELS = [
        self::UPDATE_PACKAGES => 'Updating package lists',
        self::INSTALL_PREREQUISITES => 'Installing prerequisites',
        self::CONFIGURE_ROOT_PASSWORD => 'Configuring MySQL root password',
        self::INSTALL_MYSQL => 'Installing MySQL server',
        self::START_SERVICE => 'Starting MySQL service',
        self::SECURE_INSTALLATION => 'Securing MySQL installation',
        self::CREATE_BACKUP_DIRECTORY => 'Creating backup directory',
        self::CONFIGURE_REMOTE_ACCESS => 'Configuring remote access',
        self::RESTART_SERVICE => 'Restarting MySQL',
        self::CONFIGURE_FIREWALL => 'Configuring firewall',
        self::INSTALLATION_COMPLETE => 'MySQL installation completed',
    ];

    public function countLabels(): int
    {
        return 11;
    }
}
