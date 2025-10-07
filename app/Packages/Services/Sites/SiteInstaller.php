<?php

namespace App\Packages\Services\Sites;

use App\Models\ServerSite;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageInstaller;
use App\Packages\Enums\CredentialType;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

/**
 * Site Installation Class
 *
 * Provisions a site using SSH terminal commands to a remote
 * server that has NGINX installed
 */
class SiteInstaller extends PackageInstaller implements \App\Packages\Base\ServerPackage
{
    /**
     * Execute the site installation
     */
    public function execute(array $config): ServerSite
    {
        // Validate required domain parameter
        if (! isset($config['domain']) || empty($config['domain'])) {
            throw new \InvalidArgumentException('Domain is required for site installation.');
        }

        // Get the app user that will own site directories
        $appUser = $this->server->credential('brokeforge')?->getUsername()
            ?: 'brokeforge';

        $domain = $config['domain'];
        $documentRoot = $config['document_root'] ?? "/home/{$appUser}/{$config['domain']}/public";

        // Detect PHP version from server or use provided/default
        $phpVersion = $config['php_version'] ?? $this->server->defaultPhp?->version ?? '8.3';

        $ssl = $config['ssl'] ?? false;
        $sslCertPath = $config['ssl_cert_path'] ?? null;
        $sslKeyPath = $config['ssl_key_path'] ?? null;

        // Create or update site record in database
        $site = ServerSite::updateOrCreate(
            [
                'server_id' => $this->server->id,
                'domain' => $domain,
            ],
            [
                'document_root' => $documentRoot,
                'php_version' => $phpVersion,
                'ssl_enabled' => $ssl,
                'ssl_cert_path' => $sslCertPath,
                'ssl_key_path' => $sslKeyPath,
                'nginx_config_path' => "/etc/nginx/sites-available/{$domain}",
                'status' => 'provisioning',
                'configuration' => $config,
            ]
        );

        $this->install($this->commands($domain, $documentRoot, $phpVersion, $ssl, $sslCertPath, $sslKeyPath, $site));

        return $site;
    }

    protected function commands(string $domain, string $documentRoot, string $phpVersion, bool $ssl, ?string $sslCertPath, ?string $sslKeyPath, ServerSite $site): array
    {
        // Generate nginx configuration inline (avoid helper methods)
        $nginxConfig = view('nginx.site', [
            'domain' => $domain,
            'documentRoot' => $documentRoot,
            'phpSocket' => "/var/run/php/php{$phpVersion}-fpm.sock",
            'ssl' => $ssl,
            'sslCertPath' => $sslCertPath,
            'sslKeyPath' => $sslKeyPath,
        ])->render();

        // Get the app user for ownership
        $appUser = $this->server->credential('brokeforge')?->getUsername()
            ?: 'brokeforge';

        return [
            $this->track(SiteInstallerMilestones::PREPARE_DIRECTORIES),
            "mkdir -p {$documentRoot}",
            "mkdir -p /var/log/nginx/{$domain}",

            $this->track(SiteInstallerMilestones::CREATE_CONFIG),
            "cat > /etc/nginx/sites-available/{$domain} << 'NGINX_CONFIG_EOF'\n{$nginxConfig}\nNGINX_CONFIG_EOF",

            $this->track(SiteInstallerMilestones::ENABLE_SITE),
            "ln -sf /etc/nginx/sites-available/{$domain} /etc/nginx/sites-enabled/{$domain}",

            $this->track(SiteInstallerMilestones::TEST_CONFIG),
            'nginx -t',

            $this->track(SiteInstallerMilestones::RELOAD_NGINX),
            'nginx -s reload',

            $this->track(SiteInstallerMilestones::SET_PERMISSIONS),
            "chown -R {$appUser}:{$appUser} {$documentRoot}",
            "chmod -R 775 {$documentRoot}",
            "echo '<?php phpinfo();' > {$documentRoot}/index.php",
            "chown {$appUser}:{$appUser} {$documentRoot}/index.php",

            $this->track(SiteInstallerMilestones::COMPLETE),
            function () use ($site) {
                $site->refresh();
                $site->update([
                    'status' => 'active',
                    'provisioned_at' => now(),
                ]);
            },
        ];
    }

    public function packageName(): PackageName
    {
        return PackageName::Site;
    }

    public function packageType(): PackageType
    {
        return PackageType::Site;
    }

    public function milestones(): Milestones
    {
        return new SiteInstallerMilestones;
    }

    public function credentialType(): CredentialType
    {
        return CredentialType::Root;
    }
}
