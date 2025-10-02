<?php

namespace App\Packages\Services\Firewall;

use App\Packages\Base\Milestones;

/**
 * Firewall Rule Installation Milestones
 *
 * Defines progress tracking milestones for firewall rule configuration
 */
class FirewallRuleInstallerMilestones extends Milestones
{
    public const VERIFY_FIREWALL = 'verify_firewall';

    public const APPLY_RULES = 'apply_rules';

    public const RELOAD_FIREWALL = 'reload_firewall';

    public const COMPLETE = 'complete';

    private const LABELS = [
        self::VERIFY_FIREWALL => 'Verifying firewall status',
        self::APPLY_RULES => 'Applying firewall rules',
        self::RELOAD_FIREWALL => 'Reloading firewall configuration',
        self::COMPLETE => 'Firewall rules applied',
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
