<?php

namespace App\Packages\Services\Sites;

use App\Models\ServerSite;
use App\Packages\Core\Base\PackageRemover;
use App\Packages\Core\Base\ServerPackage;

/**
 * Site Removal Class
 *
 * Handles site removal and cleanup with progress tracking
 */
class SiteRemover extends PackageRemover implements ServerPackage
{
    /**
     * Execute the site removal
     */
    public function execute(array $config): void
    {
        $site = null;
        $domain = null;

        if (isset($config['site']) && $config['site'] instanceof ServerSite) {
            $site = $config['site'];
            $domain = $site->domain;
        }

        if (isset($config['domain'])) {
            $domain = $config['domain'];
        }

        if (! $domain) {
            throw new \LogicException('Site domain must be provided for uninstalling site.');
        }

        $this->remove($this->commands($domain, $site));
    }

    protected function commands(string $domain, ?ServerSite $site): array
    {
        return [
            "rm -f /etc/nginx/sites-enabled/{$domain}",

            'nginx -t',

            'nginx -s reload',

            "[ -f /etc/nginx/sites-available/{$domain} ] && mv /etc/nginx/sites-available/{$domain} /etc/nginx/sites-available/{$domain}.disabled.$(date +%Y%m%d-%H%M%S)",

            // Optional backup command kept as reference for operators who may enable it.
            // "tar -czf /var/backups/sites/{$domain}-$(date +%Y%m%d-%H%M%S).tar.gz /var/www/{$domain} 2>/dev/null || true",

            function () use ($site) {
                if ($site) {
                    $site->update([
                        'status' => 'disabled',
                        'uninstalled_at' => now(),
                    ]);
                }
            },
        ];
    }

    protected function serviceType(): string
    {
        return PackageName::SITE;
    }
}
