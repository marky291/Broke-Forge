<?php

namespace App\Provision\Sites;

use App\Models\Site;
use App\Provision\Enums\ServiceType;
use App\Provision\Milestones;
use App\Provision\RemovableService;
use App\Provision\Server\Access\SshCredential;
use App\Provision\Server\Access\UserCredential;

class DeprovisionSite extends RemovableService
{
    protected ?Site $site = null;

    protected string $domain;

    public function setSite(Site $site): self
    {
        $this->site = $site;
        $this->domain = $site->domain;

        return $this;
    }

    public function setConfiguration(array $config): self
    {
        if (isset($config['site']) && $config['site'] instanceof Site) {
            $this->setSite($config['site']);
        }

        if (isset($config['domain'])) {
            $this->domain = $config['domain'];
        } elseif ($this->site) {
            $this->domain = $this->site->domain;
        }

        return $this;
    }

    public function deprovision(): void
    {
        if (! isset($this->domain)) {
            throw new \LogicException('Site configuration must be set before deprovisioning.');
        }

        $this->remove($this->commands());
    }

    protected function commands(): array
    {
        return [
            $this->track(DeprovisionSiteMilestones::DISABLE_SITE),
            "rm -f /etc/nginx/sites-enabled/{$this->domain}",

            $this->track(DeprovisionSiteMilestones::TEST_CONFIGURATION),
            'nginx -t',

            $this->track(DeprovisionSiteMilestones::RELOAD_NGINX),
            'nginx -s reload',

            $this->track(DeprovisionSiteMilestones::ARCHIVE_CONFIGURATION),
            "[ -f /etc/nginx/sites-available/{$this->domain} ] && mv /etc/nginx/sites-available/{$this->domain} /etc/nginx/sites-available/{$this->domain}.disabled.$(date +%Y%m%d-%H%M%S)",

            // Optional backup command kept as reference for operators who may enable it.
            // "tar -czf /var/backups/sites/{$this->domain}-$(date +%Y%m%d-%H%M%S).tar.gz /var/www/{$this->domain} 2>/dev/null || true",

            $this->track(DeprovisionSiteMilestones::COMPLETE),
            function () {
                if ($this->site) {
                    $this->site->update([
                        'status' => 'disabled',
                        'deprovisioned_at' => now(),
                    ]);
                }
            },
        ];
    }

    protected function serviceType(): string
    {
        return ServiceType::SITE;
    }

    protected function milestones(): Milestones
    {
        return new DeprovisionSiteMilestones;
    }

    protected function sshCredential(): SshCredential
    {
        return new UserCredential;
    }
}
