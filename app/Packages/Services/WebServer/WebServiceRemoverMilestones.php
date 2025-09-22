<?php

namespace App\Packages\Services\WebServer;

use App\Packages\Base\Milestones;

class WebServiceRemoverMilestones extends Milestones
{
    public const STOP_SERVICES = 'stop_services';

    public const REMOVE_SITES = 'remove_sites';

    public const REMOVE_PACKAGES = 'remove_packages';

    public const CLEANUP_CONFIG = 'cleanup_config';

    public const COMPLETE = 'complete';

    private const LABELS = [
        self::STOP_SERVICES => 'Stopping web services',
        self::REMOVE_SITES => 'Removing site configurations',
        self::REMOVE_PACKAGES => 'Removing web server packages',
        self::CLEANUP_CONFIG => 'Cleaning up configuration files',
        self::COMPLETE => 'Web server removal complete',
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
