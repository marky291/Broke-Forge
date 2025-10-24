<?php

namespace App\Packages\Services\PHP;

use App\Enums\TaskStatus;
use App\Models\ServerPhp;
use App\Packages\Base\PackageRemover;
use App\Packages\Base\ServerPackage;
use App\Packages\Enums\PhpVersion;

/**
 * PHP Removal Class
 *
 * Handles removal of PHP-FPM and related modules with progress tracking
 */
class PhpRemover extends PackageRemover implements ServerPackage
{
    /**
     * The PHP version being removed
     */
    private PhpVersion $removingVersion;

    /**
     * The ServerPhp model ID being removed
     */
    private int $phpId;

    /**
     * Mark PHP removal as failed in database
     */
    protected function markResourceAsFailed(string $errorMessage): void
    {
        ServerPhp::where('id', $this->phpId)
            ->update(['status' => TaskStatus::Failed]);
    }

    /**
     * Execute PHP removal with the specified version
     */
    public function execute(PhpVersion $phpVersion, int $phpId): void
    {
        // Store the version and ID for failure handling
        $this->removingVersion = $phpVersion;
        $this->phpId = $phpId;

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

        $this->remove($this->commands($phpVersion, $phpPackages));
    }

    protected function commands(PhpVersion $phpVersion, string $phpPackages): array
    {
        return [

            // Stop and disable PHP-FPM service
            "systemctl stop php{$phpVersion->value}-fpm 2>/dev/null || true",
            "systemctl disable php{$phpVersion->value}-fpm 2>/dev/null || true",

            // Remove PHP packages
            "DEBIAN_FRONTEND=noninteractive apt-get remove -y --purge {$phpPackages} 2>/dev/null || true",
            'DEBIAN_FRONTEND=noninteractive apt-get autoremove -y',

            // Clean up configuration files
            "rm -rf /etc/php/{$phpVersion->value} 2>/dev/null || true",
            "rm -rf /var/log/php{$phpVersion->value}-fpm 2>/dev/null || true",

            // Delete PHP record from database
            fn () => ServerPhp::where('id', $this->phpId)->delete(),

        ];
    }
}
