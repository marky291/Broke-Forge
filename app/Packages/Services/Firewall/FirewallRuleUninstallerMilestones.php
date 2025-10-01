<?php

namespace App\Packages\Services\Firewall;

use App\Packages\Base\Milestones;

class FirewallRuleUninstallerMilestones extends Milestones
{
    public const PREPARE_REMOVAL = 'prepare_removal';
    public const REMOVAL_COMPLETE = 'removal_complete';

    private const LABELS = [
        self::PREPARE_REMOVAL => 'Preparing firewall rule removal',
        self::REMOVAL_COMPLETE => 'Firewall rule removal complete',
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