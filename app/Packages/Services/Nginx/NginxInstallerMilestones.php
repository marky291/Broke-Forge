<?php

namespace App\Packages\Services\Nginx;

use App\Packages\Base\Milestones;

class NginxInstallerMilestones extends Milestones
{
    public const PREPARE_SYSTEM = 'prepare_system';

    public const SETUP_REPOSITORY = 'setup_repository';

    public const REMOVE_CONFLICTS = 'remove_conflicts';

    public const INSTALL_SOFTWARE = 'install_software';

    public const ENABLE_SERVICES = 'enable_services';

    public const SETUP_DEFAULT_SITE = 'setup_default_site';

    public const SET_PERMISSIONS = 'set_permissions';

    public const CONFIGURE_NGINX = 'configure_nginx';

    public const VERIFY_INSTALL = 'verify_install';

    public const COMPLETE = 'complete';

    private const LABELS = [
        self::PREPARE_SYSTEM => 'Preparing system',
        self::SETUP_REPOSITORY => 'Setting up Nginx repository',
        self::REMOVE_CONFLICTS => 'Removing conflicting web servers',
        self::INSTALL_SOFTWARE => 'Installing Nginx web server',
        self::ENABLE_SERVICES => 'Enabling Nginx service',
        self::SETUP_DEFAULT_SITE => 'Setting up default site',
        self::SET_PERMISSIONS => 'Setting permissions',
        self::CONFIGURE_NGINX => 'Configuring Nginx',
        self::VERIFY_INSTALL => 'Verifying Nginx installation',
        self::COMPLETE => 'Nginx setup complete',
    ];

    /**
     * @return array<string, string>
     */
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
