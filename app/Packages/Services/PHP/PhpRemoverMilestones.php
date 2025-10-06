<?php

namespace App\Packages\Services\PHP;

use App\Packages\Base\Milestones;

/**
 * PHP Removal Milestones
 *
 * Defines progress tracking milestones for PHP removal
 */
class PhpRemoverMilestones extends Milestones
{
    public const STOP_SERVICES = 'stop_services';

    public const REMOVE_PACKAGES = 'remove_packages';

    public const CLEANUP_CONFIG = 'cleanup_config';

    public const COMPLETE = 'complete';

    private const LABELS = [
        self::STOP_SERVICES => 'Stopping PHP-FPM service',
        self::REMOVE_PACKAGES => 'Removing PHP packages',
        self::CLEANUP_CONFIG => 'Cleaning up configuration files',
        self::COMPLETE => 'PHP removal complete',
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
