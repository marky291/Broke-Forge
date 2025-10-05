<?php

namespace App\Packages\Services\Database\PostgreSQL;

use App\Packages\Base\Milestones;

class PostgreSqlUpdaterMilestones extends Milestones
{
    public const BACKUP_DATABASE = 'backup_database';

    public const STOP_SERVICE = 'stop_service';

    public const UPDATE_PACKAGES = 'update_packages';

    public const UPGRADE_POSTGRESQL = 'upgrade_postgresql';

    public const MIGRATE_DATA = 'migrate_data';

    public const START_SERVICE = 'start_service';

    public const VERIFY_UPDATE = 'verify_update';

    public const UPDATE_COMPLETE = 'update_complete';

    private const LABELS = [
        self::BACKUP_DATABASE => 'Backing up databases',
        self::STOP_SERVICE => 'Stopping PostgreSQL service',
        self::UPDATE_PACKAGES => 'Updating package lists',
        self::UPGRADE_POSTGRESQL => 'Switching PostgreSQL version',
        self::MIGRATE_DATA => 'Migrating database data',
        self::START_SERVICE => 'Starting PostgreSQL service',
        self::VERIFY_UPDATE => 'Verifying PostgreSQL update',
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
