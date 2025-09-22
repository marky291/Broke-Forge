<?php

namespace App\Packages\Services\Sites;

use App\Packages\Base\Milestones;

class SiteRemoverMilestones extends Milestones
{
    public const DISABLE_SITE = 'disable_site';

    public const TEST_CONFIGURATION = 'test_configuration';

    public const RELOAD_NGINX = 'reload_nginx';

    public const ARCHIVE_CONFIGURATION = 'archive_configuration';

    public const COMPLETE = 'complete';

    private const LABELS = [
        self::DISABLE_SITE => 'Disabling site',
        self::TEST_CONFIGURATION => 'Testing nginx configuration',
        self::RELOAD_NGINX => 'Reloading nginx',
        self::ARCHIVE_CONFIGURATION => 'Archiving site configuration',
        self::COMPLETE => 'Site deprovisioning complete',
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
