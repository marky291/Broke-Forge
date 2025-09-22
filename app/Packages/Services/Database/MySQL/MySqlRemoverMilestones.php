<?php

namespace App\Packages\Services\Database\MySQL;

use App\Packages\Base\Milestones;

class MySqlRemoverMilestones extends Milestones
{
    public const STOP_SERVICE = 'stop_service';

    public const BACKUP_DATABASES = 'backup_databases';

    public const REMOVE_PACKAGES = 'remove_packages';

    public const REMOVE_DATA_DIRECTORIES = 'remove_data_directories';

    public const REMOVE_USER_GROUP = 'remove_user_group';

    public const UPDATE_FIREWALL = 'update_firewall';

    public const UNINSTALLATION_COMPLETE = 'uninstallation_complete';

    private const LABELS = [
        self::STOP_SERVICE => 'Stopping MySQL service',
        self::BACKUP_DATABASES => 'Backing up databases',
        self::REMOVE_PACKAGES => 'Removing MySQL packages',
        self::REMOVE_DATA_DIRECTORIES => 'Removing MySQL data directories',
        self::REMOVE_USER_GROUP => 'Removing MySQL user and group',
        self::UPDATE_FIREWALL => 'Updating firewall rules',
        self::UNINSTALLATION_COMPLETE => 'MySQL uninstallation completed',
    ];

    /**
     * Get all milestone labels
     */
    public static function labels(): array
    {
        return self::LABELS;
    }

    /**
     * Get label for specific milestone
     */
    public static function label(string $milestone): ?string
    {
        return self::LABELS[$milestone] ?? null;
    }

    /**
     * Count total milestones for progress calculation
     */
    public function countLabels(): int
    {
        return count(self::LABELS);
    }
}
