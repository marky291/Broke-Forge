<?php

namespace App\Packages\Services\WebServer;

use App\Packages\Base\Milestones;

class WebServiceInstallerMilestones extends Milestones
{
    public const PREPARE_SYSTEM = 'prepare_system';

    public const SETUP_REPOSITORY = 'setup_repository';

    public const REMOVE_CONFLICTS = 'remove_conflicts';

    public const INSTALL_SOFTWARE = 'install_software';

    public const ENABLE_SERVICES = 'enable_services';

    public const CONFIGURE_FIREWALL = 'configure_firewall';

    public const SETUP_DEFAULT_SITE = 'setup_default_site';

    public const SET_PERMISSIONS = 'set_permissions';

    public const CONFIGURE_NGINX = 'configure_nginx';

    public const VERIFY_INSTALL = 'verify_install';

    public const COMPLETE = 'complete';

    private const LABELS = [
        self::PREPARE_SYSTEM => 'Preparing system',
        self::SETUP_REPOSITORY => 'Setting up software sources',
        self::REMOVE_CONFLICTS => 'Removing conflicting software',
        self::INSTALL_SOFTWARE => 'Installing required software',
        self::ENABLE_SERVICES => 'Enabling and starting services',
        self::CONFIGURE_FIREWALL => 'Configuring firewall rules',
        self::SETUP_DEFAULT_SITE => 'Setting up default site',
        self::SET_PERMISSIONS => 'Setting permissions',
        self::CONFIGURE_NGINX => 'Configuring Nginx',
        self::VERIFY_INSTALL => 'Verifying installation',
        self::COMPLETE => 'Setup complete',
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
