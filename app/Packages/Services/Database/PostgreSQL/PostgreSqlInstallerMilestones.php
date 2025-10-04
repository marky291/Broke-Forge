<?php

namespace App\Packages\Services\Database\PostgreSQL;

use App\Packages\Base\Milestones;

class PostgreSqlInstallerMilestones extends Milestones
{
    public const UPDATE_PACKAGES = 'update_packages';

    public const INSTALL_PREREQUISITES = 'install_prerequisites';

    public const ADD_REPOSITORY = 'add_repository';

    public const INSTALL_POSTGRESQL = 'install_postgresql';

    public const START_SERVICE = 'start_service';

    public const CONFIGURE_ROOT_PASSWORD = 'configure_root_password';

    public const CONFIGURE_REMOTE_ACCESS = 'configure_remote_access';

    public const RESTART_SERVICE = 'restart_service';

    public const CONFIGURE_FIREWALL = 'configure_firewall';

    public const VERIFY_INSTALLATION = 'verify_installation';

    public const INSTALLATION_COMPLETE = 'installation_complete';

    private const LABELS = [
        self::UPDATE_PACKAGES => 'Updating package lists',
        self::INSTALL_PREREQUISITES => 'Installing prerequisites',
        self::ADD_REPOSITORY => 'Adding PostgreSQL repository',
        self::INSTALL_POSTGRESQL => 'Installing PostgreSQL server',
        self::START_SERVICE => 'Starting PostgreSQL service',
        self::CONFIGURE_ROOT_PASSWORD => 'Configuring postgres superuser password',
        self::CONFIGURE_REMOTE_ACCESS => 'Configuring remote access',
        self::RESTART_SERVICE => 'Restarting PostgreSQL service',
        self::CONFIGURE_FIREWALL => 'Configuring firewall rules',
        self::VERIFY_INSTALLATION => 'Verifying PostgreSQL installation',
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
