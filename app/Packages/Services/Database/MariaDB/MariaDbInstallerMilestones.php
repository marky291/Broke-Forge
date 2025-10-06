<?php

namespace App\Packages\Services\Database\MariaDB;

use App\Packages\Base\Milestones;

class MariaDbInstallerMilestones extends Milestones
{
    public const UPDATE_PACKAGES = 'update_packages';

    public const INSTALL_PREREQUISITES = 'install_prerequisites';

    public const ADD_REPOSITORY = 'add_repository';

    public const CONFIGURE_ROOT_PASSWORD = 'configure_root_password';

    public const INSTALL_MARIADB = 'install_mariadb';

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
        self::ADD_REPOSITORY => 'Adding MariaDB repository',
        self::CONFIGURE_ROOT_PASSWORD => 'Configuring root password',
        self::INSTALL_MARIADB => 'Installing MariaDB server',
        self::START_SERVICE => 'Starting MariaDB service',
        self::SECURE_INSTALLATION => 'Securing MariaDB installation',
        self::CREATE_BACKUP_DIRECTORY => 'Creating backup directory',
        self::CONFIGURE_REMOTE_ACCESS => 'Configuring remote access',
        self::RESTART_SERVICE => 'Restarting MariaDB service',
        self::CONFIGURE_FIREWALL => 'Configuring firewall rules',
        self::INSTALLATION_COMPLETE => 'Installation complete',
    ];

    public static function labels(): array
    {
        return self::LABELS;
    }

    public static function label(string $milestone): ?string
    {
        return self::LABELS[$milestone] ?? null;
    }

    public function countLabels(): int
    {
        return count(self::LABELS);
    }
}
