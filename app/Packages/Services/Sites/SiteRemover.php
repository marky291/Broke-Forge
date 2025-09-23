<?php

namespace App\Packages\Services\Sites;

use App\Models\ServerSite;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageRemover;
use App\Packages\Credentials\SshCredential;
use App\Packages\Credentials\UserCredential;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

/**
 * Site Removal Class
 *
 * Handles site removal and cleanup with progress tracking
 */
class SiteRemover extends PackageRemover
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
            throw new \LogicException('Site domain must be provided for deprovisioning.');
        }

        $this->remove($this->commands($domain, $site));
    }

    protected function commands(string $domain, ?ServerSite $site): array
    {
        return [
            $this->track(SiteRemoverMilestones::DISABLE_SITE),
            "rm -f /etc/nginx/sites-enabled/{$domain}",

            $this->track(SiteRemoverMilestones::TEST_CONFIGURATION),
            'nginx -t',

            $this->track(SiteRemoverMilestones::RELOAD_NGINX),
            'nginx -s reload',

            $this->track(SiteRemoverMilestones::ARCHIVE_CONFIGURATION),
            "[ -f /etc/nginx/sites-available/{$domain} ] && mv /etc/nginx/sites-available/{$domain} /etc/nginx/sites-available/{$domain}.disabled.$(date +%Y%m%d-%H%M%S)",

            // Optional backup command kept as reference for operators who may enable it.
            // "tar -czf /var/backups/sites/{$domain}-$(date +%Y%m%d-%H%M%S).tar.gz /var/www/{$domain} 2>/dev/null || true",

            $this->track(SiteRemoverMilestones::COMPLETE),
            function () use ($site) {
                if ($site) {
                    $site->update([
                        'status' => 'disabled',
                        'deprovisioned_at' => now(),
                    ]);
                }
            },
        ];
    }

    protected function serviceType(): string
    {
        return PackageName::SITE;
    }

    public function milestones(): Milestones
    {
        return new SiteRemoverMilestones;
    }

    public function sshCredential(): SshCredential
    {
        return new UserCredential;
    }

    public function packageName(): PackageName
    {
        return PackageName::Site;
    }

    public function packageType(): PackageType
    {
        return PackageType::Site;
    }
}
