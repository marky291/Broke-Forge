<?php

namespace App\Packages\Services\Sites\Command;

use App\Packages\Base\Milestones;

/**
 * Site Command Installer Milestones
 *
 * Progress tracking for site command execution
 */
class SiteCommandInstallerMilestones extends Milestones
{
    public const PREPARE_EXECUTION = 'prepare_execution';

    public const COMMAND_COMPLETE = 'command_complete';

    private const LABELS = [
        self::PREPARE_EXECUTION => 'Preparing command execution',
        self::COMMAND_COMPLETE => 'Command execution complete',
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
