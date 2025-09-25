<?php

namespace App\Packages\Services\Sites;

use App\Models\ServerSite;
use App\Packages\Base\Milestones;
use App\Packages\Base\Package;
use App\Packages\Base\PackageInstaller;
use App\Packages\Credentials\SshCredential;
use App\Packages\Credentials\UserCredential;
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
        if (!isset($config['domain']) || empty($config['domain'])) {
            throw new \InvalidArgumentException('Domain is required for site installation.');
        }

        // Get the app user that will own site directories
        $userCredential = new UserCredential;
        $appUser = $userCredential->user();

        $domain = $config['domain'];
        $documentRoot = $config['document_root'] ?? "/home/{$appUser}/{$config['domain']}/public";

        // Detect PHP version from server services (inline logic)
        $phpService = $this->server->packages()
            ->where('service_name', 'php')
            ->latest('id')
            ->first();
        $phpVersion = $config['php_version'] ?? (
            ($phpService && is_array($phpService->configuration) && isset($phpService->configuration['version']))
                ? (string) $phpService->configuration['version']
                : '8.3'
        );

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
        $appUser = $this->sshCredential()->user();

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
            "chmod -R 755 {$documentRoot}",
            "echo '<?php phpinfo();' > {$documentRoot}/index.php",

            $this->track(SiteInstallerMilestones::COMPLETE),
            function () use ($site) {
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
        // TODO: Implement milestones() method.
    }

    public function sshCredential(): SshCredential
    {
        // TODO: Implement sshCredential() method.
    }
}
