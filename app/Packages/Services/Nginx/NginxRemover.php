<?php

namespace App\Packages\Services\Nginx;

use App\Packages\Base\PackageRemover;

/**
 * Web Server Removal Class
 *
 * Handles removal of NGINX and PHP-FPM with progress tracking
 */
class NginxRemover extends PackageRemover
{
    /**
     * Execute the web server removal
     */
    public function execute(): void
    {
        $phpService = $this->server->packages()->where('service_name', 'php')->latest('id')->first();
        $phpVersion = $phpService ? $phpService->configuration['version'] : '8.3';

        // Compose common PHP packages for the chosen version.
        $phpPackages = implode(' ', [
            "php{$phpVersion}-fpm",
            "php{$phpVersion}-cli",
            "php{$phpVersion}-common",
            "php{$phpVersion}-curl",
            "php{$phpVersion}-mbstring",
            "php{$phpVersion}-xml",
            "php{$phpVersion}-zip",
            "php{$phpVersion}-intl",
            "php{$phpVersion}-mysql",
            "php{$phpVersion}-gd",
        ]);

        $this->remove($this->commands($phpVersion, $phpPackages));
    }

    protected function commands(string $phpVersion, string $phpPackages): array
    {
        return [
            'systemctl stop nginx >/dev/null 2>&1 || true',
            "systemctl stop php{$phpVersion}-fpm >/dev/null 2>&1 || true",
            'systemctl disable nginx >/dev/null 2>&1 || true',
            "systemctl disable php{$phpVersion}-fpm >/dev/null 2>&1 || true",

            'rm -rf /etc/nginx/sites-enabled/*',
            'rm -rf /etc/nginx/sites-available/*',

            "DEBIAN_FRONTEND=noninteractive apt-get remove -y --purge nginx {$phpPackages}",
            'DEBIAN_FRONTEND=noninteractive apt-get autoremove -y',

            'rm -rf /etc/nginx',
            'rm -rf /var/log/nginx',
            'rm -rf /var/www/html',

        ];
    }
}
