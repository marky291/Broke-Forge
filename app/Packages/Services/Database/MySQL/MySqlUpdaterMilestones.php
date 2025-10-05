<?php

namespace App\Packages\Services\Database\MySQL;

use App\Packages\Base\Milestones;

class MySqlUpdaterMilestones extends Milestones
{
    public const BACKUP_DATABASE = 'backup_database';

    public const STOP_SERVICE = 'stop_service';

    public const UPDATE_PACKAGES = 'update_packages';

    public const UPGRADE_MYSQL = 'upgrade_mysql';

    public const START_SERVICE = 'start_service';

    public const RUN_MYSQL_UPGRADE = 'run_mysql_upgrade';

    public const VERIFY_UPDATE = 'verify_update';

    public const UPDATE_COMPLETE = 'update_complete';

    private const LABELS = [
        self::BACKUP_DATABASE => 'Backing up databases',
        self::STOP_SERVICE => 'Stopping MySQL service',
        self::UPDATE_PACKAGES => 'Updating package lists',
        self::UPGRADE_MYSQL => 'Switching MySQL version',
        self::START_SERVICE => 'Starting MySQL service',
        self::RUN_MYSQL_UPGRADE => 'Running mysql_upgrade',
        self::VERIFY_UPDATE => 'Verifying MySQL update',
        self::UPDATE_COMPLETE => 'Update complete',
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
