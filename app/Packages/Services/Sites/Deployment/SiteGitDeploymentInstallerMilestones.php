<?php

namespace App\Packages\Services\Sites\Deployment;

use App\Packages\Base\Milestones;

/**
 * Site Git Deployment Installer Milestones
 *
 * Progress tracking for site deployment execution
 */
class SiteGitDeploymentInstallerMilestones extends Milestones
{
    public const PREPARE_DEPLOYMENT = 'prepare_deployment';

    public const EXECUTE_DEPLOYMENT_SCRIPT = 'execute_deployment_script';

    public const CAPTURE_DEPLOYMENT_STATUS = 'capture_deployment_status';

    public const COMPLETE = 'complete';

    private const LABELS = [
        self::PREPARE_DEPLOYMENT => 'Preparing deployment',
        self::EXECUTE_DEPLOYMENT_SCRIPT => 'Executing deployment script',
        self::CAPTURE_DEPLOYMENT_STATUS => 'Capturing deployment status',
        self::COMPLETE => 'Deployment complete',
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
