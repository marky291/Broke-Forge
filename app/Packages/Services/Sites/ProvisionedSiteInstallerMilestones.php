<?php

namespace App\Packages\Services\Sites;

use App\Packages\Base\Milestones;

class ProvisionedSiteInstallerMilestones extends Milestones
{
    public const PREPARE_DIRECTORIES = 'prepare_directories';

    public const CREATE_CONFIG = 'create_config';

    public const ENABLE_SITE = 'enable_site';

    public const TEST_CONFIG = 'test_config';

    public const RELOAD_NGINX = 'reload_nginx';

    public const SET_PERMISSIONS = 'set_permissions';

    public const CLONE_REPOSITORY = 'clone_repository';

    public const COMPLETE = 'complete';

    private const LABELS = [
        self::PREPARE_DIRECTORIES => 'Preparing site directories',
        self::CREATE_CONFIG => 'Creating nginx configuration',
        self::ENABLE_SITE => 'Enabling site',
        self::TEST_CONFIG => 'Testing nginx configuration',
        self::RELOAD_NGINX => 'Reloading nginx',
        self::SET_PERMISSIONS => 'Setting file permissions',
        self::CLONE_REPOSITORY => 'Cloning git repository',
        self::COMPLETE => 'Site setup complete',
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
