<?php

namespace App\Packages\Services\PHP;

use App\Enums\PhpStatus;
use App\Models\ServerPhp;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageInstaller;
use App\Packages\Base\ServerPackage;
use App\Packages\Enums\CredentialType;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;
use App\Packages\Enums\PhpVersion;

/**
 * PHP Installation Class
 *
 * Handles installation of PHP-FPM and related modules with progress tracking
 */
class PhpInstaller extends PackageInstaller implements ServerPackage
{
    /**
     * The PHP version being installed
     */
    private PhpVersion $installingVersion;

    public function packageName(): PackageName
    {
        return match ($this->installingVersion) {
            PhpVersion::PHP81 => PackageName::Php81,
            PhpVersion::PHP82 => PackageName::Php82,
            PhpVersion::PHP83 => PackageName::Php83,
            PhpVersion::PHP84 => PackageName::Php84,
        };
    }

    public function packageType(): PackageType
    {
        return PackageType::PHP;
    }

    public function milestones(): Milestones
    {
        return new PhpInstallerMilestones;
    }

    public function credentialType(): CredentialType
    {
        return CredentialType::Root;
    }

    /**
     * Mark PHP installation as failed in database
     */
    protected function markResourceAsFailed(string $errorMessage): void
    {
        ServerPhp::where('server_id', $this->server->id)
            ->where('version', $this->installingVersion->value)
            ->update(['status' => PhpStatus::Failed]);
    }

    /**
     * Execute PHP installation with the specified version
     */
    public function execute(PhpVersion $phpVersion): void
    {
        // Store the version for packageName() method
        $this->installingVersion = $phpVersion;

        // Check if this is the first PHP version being installed
        $isFirstPhp = $this->server->phps()->count() === 0;

        // Create ServerPhp record before installation if it doesn't exist
        ServerPhp::firstOrCreate(
            [
                'server_id' => $this->server->id,
                'version' => $phpVersion->value,
            ],
            [
                'status' => PhpStatus::Installing,
                'is_cli_default' => $isFirstPhp,
                'is_site_default' => $isFirstPhp,
            ]
        );

        // Compose common PHP packages for the chosen version
        $phpPackages = implode(' ', [
            "php{$phpVersion->value}-fpm",
            "php{$phpVersion->value}-cli",
            "php{$phpVersion->value}-common",
            "php{$phpVersion->value}-curl",
            "php{$phpVersion->value}-mbstring",
            "php{$phpVersion->value}-xml",
            "php{$phpVersion->value}-zip",
            "php{$phpVersion->value}-intl",
            "php{$phpVersion->value}-mysql",
            "php{$phpVersion->value}-gd",
            "php{$phpVersion->value}-bcmath",
            "php{$phpVersion->value}-soap",
            "php{$phpVersion->value}-opcache",
            "php{$phpVersion->value}-readline",
        ]);

        $this->install($this->commands($phpVersion, $phpPackages));
    }

    protected function commands(PhpVersion $phpVersion, string $phpPackages): array
    {
        return [
            $this->track(PhpInstallerMilestones::PREPARE_SYSTEM),

            // Update package lists and install prerequisites
            'DEBIAN_FRONTEND=noninteractive apt-get update -y',
            'DEBIAN_FRONTEND=noninteractive apt-get install -y ca-certificates curl gnupg lsb-release software-properties-common',

            $this->track(PhpInstallerMilestones::SETUP_REPOSITORY),

            // Add PHP repository (Ondrej PPA for Ubuntu, standard repos for Debian)
            'if command -v lsb_release >/dev/null 2>&1 && [ "$(lsb_release -is)" = "Ubuntu" ]; then add-apt-repository -y ppa:ondrej/php || true; DEBIAN_FRONTEND=noninteractive apt-get update -y; fi || true',

            $this->track(PhpInstallerMilestones::INSTALL_PHP),

            // Install PHP packages (attempt versioned packages first, then fall back to default php if needed)
            "DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends {$phpPackages} || DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends php-fpm php-cli php-common php-curl php-mbstring php-xml php-zip php-intl php-mysql php-gd php-bcmath php-soap php-opcache php-readline",

            $this->track(PhpInstallerMilestones::CONFIGURE_PHP),

            // Configure PHP-FPM settings
            "sed -i 's/^;*upload_max_filesize.*/upload_max_filesize = 100M/' /etc/php/{$phpVersion->value}/fpm/php.ini 2>/dev/null || true",
            "sed -i 's/^;*post_max_size.*/post_max_size = 100M/' /etc/php/{$phpVersion->value}/fpm/php.ini 2>/dev/null || true",
            "sed -i 's/^;*max_execution_time.*/max_execution_time = 300/' /etc/php/{$phpVersion->value}/fpm/php.ini 2>/dev/null || true",
            "sed -i 's/^;*memory_limit.*/memory_limit = 256M/' /etc/php/{$phpVersion->value}/fpm/php.ini 2>/dev/null || true",

            // Configure CLI settings
            "sed -i 's/^;*memory_limit.*/memory_limit = -1/' /etc/php/{$phpVersion->value}/cli/php.ini 2>/dev/null || true",

            $this->track(PhpInstallerMilestones::ENABLE_SERVICE),

            // Enable and start PHP-FPM service
            "systemctl enable php{$phpVersion->value}-fpm || systemctl enable php-fpm",
            "systemctl restart php{$phpVersion->value}-fpm || systemctl restart php-fpm",

            // Save PHP installation to database
            function () use ($phpVersion) {
                // Only update status - preserve is_cli_default and is_site_default set by controller
                ServerPhp::where('server_id', $this->server->id)
                    ->where('version', $phpVersion->value)
                    ->update(['status' => PhpStatus::Active->value]);
            },

            $this->track(PhpInstallerMilestones::VERIFY_INSTALLATION),

            // Verify PHP installation
            "php{$phpVersion->value} -v || php -v",
            "systemctl status php{$phpVersion->value}-fpm --no-pager || systemctl status php-fpm --no-pager",

            $this->track(PhpInstallerMilestones::COMPLETE),
        ];
    }
}
