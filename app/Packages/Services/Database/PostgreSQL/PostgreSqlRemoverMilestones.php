<?php

namespace App\Packages\Services\Database\PostgreSQL;

use App\Packages\Base\Milestones;

class PostgreSqlRemoverMilestones extends Milestones
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
        self::STOP_SERVICE => 'Stopping PostgreSQL service',
        self::BACKUP_DATABASES => 'Backing up databases',
        self::REMOVE_PACKAGES => 'Removing PostgreSQL packages',
        self::REMOVE_DATA_DIRECTORIES => 'Removing PostgreSQL data directories',
        self::REMOVE_REPOSITORY => 'Removing PostgreSQL repository',
        self::REMOVE_USER_GROUP => 'Removing postgres user and group',
        self::UPDATE_FIREWALL => 'Updating firewall rules',
        self::UNINSTALLATION_COMPLETE => 'PostgreSQL uninstallation completed',
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
