<?php

namespace App\Packages\Services\PHP;

use App\Enums\TaskStatus;
use App\Models\ServerPhp;
use App\Packages\Core\Base\PackageInstaller;
use App\Packages\Core\Base\ServerPackage;
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

    /**
     * Mark PHP installation as failed in database
     */
    protected function markResourceAsFailed(string $errorMessage): void
    {
        ServerPhp::where('server_id', $this->server->id)
            ->where('version', $this->installingVersion->value)
            ->update(['status' => TaskStatus::Failed]);
    }

    /**
     * Execute PHP installation with the specified version
     *
     * Note: ServerPhp record should already exist with 'pending' status
     * (created by caller before dispatching PhpInstallerJob)
     */
    public function execute(PhpVersion $phpVersion): void
    {
        // Store the version for this installation
        $this->installingVersion = $phpVersion;

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
            "php{$phpVersion->value}-redis",
        ]);

        $this->install($this->commands($phpVersion, $phpPackages));
    }

    protected function commands(PhpVersion $phpVersion, string $phpPackages): array
    {
        return [

            // Update package lists and install prerequisites
            'DEBIAN_FRONTEND=noninteractive apt-get update -y',
            'DEBIAN_FRONTEND=noninteractive apt-get install -y ca-certificates curl gnupg lsb-release software-properties-common',

            // Add PHP repository (Ondrej PPA for Ubuntu, standard repos for Debian)
            'if command -v lsb_release >/dev/null 2>&1 && [ "$(lsb_release -is)" = "Ubuntu" ]; then add-apt-repository -y ppa:ondrej/php || true; DEBIAN_FRONTEND=noninteractive apt-get update -y; fi',

            // Install PHP packages for the specified version
            "DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends {$phpPackages}",

            // Configure PHP-FPM settings
            "sed -i 's/^;*upload_max_filesize.*/upload_max_filesize = 100M/' /etc/php/{$phpVersion->value}/fpm/php.ini 2>/dev/null || true",
            "sed -i 's/^;*post_max_size.*/post_max_size = 100M/' /etc/php/{$phpVersion->value}/fpm/php.ini 2>/dev/null || true",
            "sed -i 's/^;*max_execution_time.*/max_execution_time = 300/' /etc/php/{$phpVersion->value}/fpm/php.ini 2>/dev/null || true",
            "sed -i 's/^;*memory_limit.*/memory_limit = 256M/' /etc/php/{$phpVersion->value}/fpm/php.ini 2>/dev/null || true",

            // Configure CLI settings
            "sed -i 's/^;*memory_limit.*/memory_limit = -1/' /etc/php/{$phpVersion->value}/cli/php.ini 2>/dev/null || true",

            // Enable and start PHP-FPM service
            "systemctl enable php{$phpVersion->value}-fpm",
            "systemctl restart php{$phpVersion->value}-fpm",

            // Note: Status updates are managed by PhpInstallerJob (Reverb Package Lifecycle)
            // Job updates: pending → installing → active/failed

            // Verify PHP installation (use both explicit binary and system default)
            "php{$phpVersion->value} -v",
            'php -v',
            "systemctl status php{$phpVersion->value}-fpm --no-pager",

        ];
    }
}
