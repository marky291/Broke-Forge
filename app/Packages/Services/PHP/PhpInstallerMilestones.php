<?php

namespace App\Packages\Services\PHP;

use App\Packages\Base\Milestones;

/**
 * PHP Installation Milestones
 *
 * Defines progress tracking milestones for PHP installation
 */
class PhpInstallerMilestones extends Milestones
{
    public const PREPARE_SYSTEM = 'prepare_system';

    public const SETUP_REPOSITORY = 'setup_repository';

    public const INSTALL_PHP = 'install_php';

    public const CONFIGURE_PHP = 'configure_php';

    public const ENABLE_SERVICE = 'enable_service';

    public const VERIFY_INSTALLATION = 'verify_installation';

    public const COMPLETE = 'complete';

    private const LABELS = [
        self::PREPARE_SYSTEM => 'Preparing system for PHP installation',
        self::SETUP_REPOSITORY => 'Setting up PHP repository',
        self::INSTALL_PHP => 'Installing PHP packages',
        self::CONFIGURE_PHP => 'Configuring PHP settings',
        self::ENABLE_SERVICE => 'Enabling PHP-FPM service',
        self::VERIFY_INSTALLATION => 'Verifying PHP installation',
        self::COMPLETE => 'PHP installation complete',
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
