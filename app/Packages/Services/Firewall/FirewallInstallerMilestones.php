<?php

namespace App\Packages\Services\Firewall;

use App\Packages\Base\Milestones;

/**
 * Firewall Installation Milestones
 *
 * Defines progress tracking milestones for UFW firewall installation
 */
class FirewallInstallerMilestones extends Milestones
{
    public const PREPARE_SYSTEM = 'prepare_system';

    public const INSTALL_FIREWALL = 'install_firewall';

    public const CONFIGURE_DEFAULTS = 'configure_defaults';

    public const ENABLE_FIREWALL = 'enable_firewall';

    public const COMPLETE = 'complete';

    private const LABELS = [
        self::PREPARE_SYSTEM => 'Preparing system for firewall installation',
        self::INSTALL_FIREWALL => 'Installing UFW firewall',
        self::CONFIGURE_DEFAULTS => 'Configuring default firewall policies',
        self::ENABLE_FIREWALL => 'Enabling firewall',
        self::COMPLETE => 'Firewall installation complete',
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
