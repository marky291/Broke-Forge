<?php

namespace App\Provision\Sites;

use App\Models\ServerSite;
use App\Provision\Enums\ServiceType;
use App\Provision\InstallableService;
use App\Provision\Milestones;
use App\Provision\Server\Access\SshCredential;
use App\Provision\Server\Access\UserCredential;

/**
 * Provisions a site using SSH terminal commands to a remote
 * server that has NGINX installed.
 */
class ProvisionSite extends InstallableService
{
    protected ?ServerSite $site = null;

    protected string $domain;

    protected string $documentRoot;

    protected string $phpVersion = '8.3';

    protected bool $ssl = false;

    protected ?string $sslCertPath = null;

    protected ?string $sslKeyPath = null;

    /**
     * Set configuration for site provisioning
     */
    public function setConfiguration(array $config): self
    {
        // Get the app user that will own site directories
        $userCredential = new \App\Provision\Server\Access\UserCredential;
        $appUser = $userCredential->user();

        $this->domain = $config['domain'];
        $this->documentRoot = $config['document_root'] ?? "/home/{$appUser}/{$config['domain']}/public";
        $this->phpVersion = $config['php_version'] ?? $this->detectPhpVersion();
        $this->ssl = $config['ssl'] ?? false;
        $this->sslCertPath = $config['ssl_cert_path'] ?? null;
        $this->sslKeyPath = $config['ssl_key_path'] ?? null;

        // Create or update site record in database
        $this->site = ServerSite::updateOrCreate(
            [
                'server_id' => $this->server->id,
                'domain' => $this->domain,
            ],
            [
                'document_root' => $this->documentRoot,
                'php_version' => $this->phpVersion,
                'ssl_enabled' => $this->ssl,
                'ssl_cert_path' => $this->sslCertPath,
                'ssl_key_path' => $this->sslKeyPath,
                'nginx_config_path' => "/etc/nginx/sites-available/{$this->domain}",
                'status' => 'provisioning',
                'configuration' => $config,
            ]
        );

        return $this;
    }

    protected function serviceType(): string
    {
        return ServiceType::SITE;
    }

    public function provision(): void
    {
        if (! isset($this->domain)) {
            throw new \LogicException('Site configuration must be set before provisioning.');
        }

        $this->install($this->commands());
    }

    protected function commands(): array
    {
        $nginxConfig = $this->generateNginxConfig();

        // Get the app user for ownership
        $appUser = $this->sshCredential()->user();

        return [
            $this->track(ProvisionSiteMilestones::PREPARE_DIRECTORIES),
            "mkdir -p {$this->documentRoot}",
            "mkdir -p /var/log/nginx/{$this->domain}",

            $this->track(ProvisionSiteMilestones::CREATE_CONFIG),
            "cat > /etc/nginx/sites-available/{$this->domain} << 'NGINX_CONFIG_EOF'\n{$nginxConfig}\nNGINX_CONFIG_EOF",

            $this->track(ProvisionSiteMilestones::ENABLE_SITE),
            "ln -sf /etc/nginx/sites-available/{$this->domain} /etc/nginx/sites-enabled/{$this->domain}",

            $this->track(ProvisionSiteMilestones::TEST_CONFIG),
            'nginx -t',

            $this->track(ProvisionSiteMilestones::RELOAD_NGINX),
            'nginx -s reload',

            $this->track(ProvisionSiteMilestones::SET_PERMISSIONS),
            "chown -R {$appUser}:{$appUser} {$this->documentRoot}",
            "chmod -R 755 {$this->documentRoot}",
            "echo '<?php phpinfo();' > {$this->documentRoot}/index.php",

            $this->track(ProvisionSiteMilestones::COMPLETE),
            function () {
                if ($this->site) {
                    $this->site->update([
                        'status' => 'active',
                        'provisioned_at' => now(),
                    ]);
                }
            },
        ];
    }

    /**
     * Detect PHP version from server services
     */
    protected function detectPhpVersion(): string
    {
        $phpService = $this->server->services()
            ->where('service_name', 'php')
            ->latest('id')
            ->first();

        if ($phpService && is_array($phpService->configuration) && isset($phpService->configuration['version'])) {
            return (string) $phpService->configuration['version'];
        }

        return '8.3'; // Default fallback
    }

    /**
     * Generate nginx configuration for the site using Blade template
     */
    protected function generateNginxConfig(): string
    {
        return view('nginx.site', [
            'domain' => $this->domain,
            'documentRoot' => $this->documentRoot,
            'phpSocket' => "/var/run/php/php{$this->phpVersion}-fpm.sock",
            'ssl' => $this->ssl,
            'sslCertPath' => $this->sslCertPath,
            'sslKeyPath' => $this->sslKeyPath,
        ])->render();
    }

    protected function milestones(): Milestones
    {
        return new ProvisionSiteMilestones;
    }

    protected function sshCredential(): SshCredential
    {
        return new UserCredential;
    }
}
