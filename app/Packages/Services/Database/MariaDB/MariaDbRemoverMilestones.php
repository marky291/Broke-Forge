<?php

namespace App\Packages\Services\Database\MariaDB;

use App\Packages\Base\Milestones;

class MariaDbRemoverMilestones extends Milestones
{
    public const STOP_SERVICE = 'stop_service';

    public const BACKUP_DATABASES = 'backup_databases';

    public const REMOVE_PACKAGES = 'remove_packages';

    public const REMOVE_DATA_DIRECTORIES = 'remove_data_directories';

    public const REMOVE_REPOSITORY = 'remove_repository';

    public const REMOVE_USER_GROUP = 'remove_user_group';

    public const UPDATE_FIREWALL = 'update_firewall';

    public const UNINSTALLATION_COMPLETE = 'uninstallation_complete';

    private const LABELS = [
        self::STOP_SERVICE => 'Stopping MariaDB service',
        self::BACKUP_DATABASES => 'Backing up databases',
        self::REMOVE_PACKAGES => 'Removing MariaDB packages',
        self::REMOVE_DATA_DIRECTORIES => 'Removing data directories',
        self::REMOVE_REPOSITORY => 'Removing MariaDB repository',
        self::REMOVE_USER_GROUP => 'Removing user and group',
        self::UPDATE_FIREWALL => 'Updating firewall rules',
        self::UNINSTALLATION_COMPLETE => 'Uninstallation complete',
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
