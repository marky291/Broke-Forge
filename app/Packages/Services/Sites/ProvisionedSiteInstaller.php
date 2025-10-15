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
class ProvisionedSiteInstaller extends PackageInstaller implements \App\Packages\Base\ServerPackage
{
    /**
     * Execute the site installation
     */
    public function execute(int $siteId): ServerSite
    {
        // Load the existing site record
        $site = ServerSite::findOrFail($siteId);

        // Get the app user that will own site directories
        $appUser = $this->server->credential('brokeforge')?->getUsername()
            ?: 'brokeforge';

        $domain = $site->domain;
        $documentRoot = $site->document_root;
        $phpVersion = $site->php_version;
        $ssl = $site->ssl_enabled;
        $sslCertPath = $site->ssl_cert_path;
        $sslKeyPath = $site->ssl_key_path;

        // Get git configuration from site configuration
        $config = $site->configuration ?? [];

        $this->install($this->commands($domain, $documentRoot, $phpVersion, $ssl, $sslCertPath, $sslKeyPath, $site, $config));

        return $site;
    }

    protected function commands(string $domain, string $documentRoot, string $phpVersion, bool $ssl, ?string $sslCertPath, ?string $sslKeyPath, ServerSite $site, array $config): array
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
            $this->track(ProvisionedSiteInstallerMilestones::PREPARE_DIRECTORIES),
            "mkdir -p {$documentRoot}",
            "mkdir -p /var/log/nginx/{$domain}",

            $this->track(ProvisionedSiteInstallerMilestones::CREATE_CONFIG),
            "cat > /etc/nginx/sites-available/{$domain} << 'NGINX_CONFIG_EOF'\n{$nginxConfig}\nNGINX_CONFIG_EOF",

            $this->track(ProvisionedSiteInstallerMilestones::ENABLE_SITE),
            "ln -sf /etc/nginx/sites-available/{$domain} /etc/nginx/sites-enabled/{$domain}",

            $this->track(ProvisionedSiteInstallerMilestones::TEST_CONFIG),
            'nginx -t',

            $this->track(ProvisionedSiteInstallerMilestones::RELOAD_NGINX),
            'nginx -s reload',

            $this->track(ProvisionedSiteInstallerMilestones::SET_PERMISSIONS),
            "chown -R {$appUser}:{$appUser} {$documentRoot}",
            "chmod -R 775 {$documentRoot}",
            "echo '<?php phpinfo();' > {$documentRoot}/index.php",
            "chown {$appUser}:{$appUser} {$documentRoot}/index.php",

            // Add git clone commands if repository is configured
            ...($this->getGitCloneCommands($config, $documentRoot, $appUser)),

            $this->track(ProvisionedSiteInstallerMilestones::COMPLETE),
            function () use ($site, $config) {
                $site->refresh();

                $updates = [
                    'status' => 'active',
                    'provisioned_at' => now(),
                ];

                // Update git_status if repository was cloned
                if (isset($config['git_repository'])) {
                    $updates['git_status'] = \App\Packages\Enums\GitStatus::Installed;
                    $updates['git_installed_at'] = now();
                }

                $site->update($updates);
            },
        ];
    }

    /**
     * Get git clone commands if repository is configured
     */
    protected function getGitCloneCommands(array $config, string $documentRoot, string $appUser): array
    {
        if (! isset($config['git_repository'])) {
            return [];
        }

        $gitConfig = $config['git_repository'];
        $repository = $gitConfig['repository'] ?? null;
        $branch = $gitConfig['branch'] ?? 'main';

        if (! $repository) {
            return [];
        }

        // Normalize repository to SSH URL - ALWAYS use SSH for git clone
        if (str_starts_with($repository, 'git@') || str_starts_with($repository, 'ssh://')) {
            $repositorySshUrl = $repository;
        } elseif (str_starts_with($repository, 'https://github.com/')) {
            // Convert HTTPS to SSH URL
            $repositorySshUrl = preg_replace('#^https://github\.com/(.+?)(?:\.git)?$#', 'git@github.com:$1.git', $repository);
        } elseif (preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $repository)) {
            $repositorySshUrl = sprintf('git@github.com:%s.git', $repository);
        } else {
            return [];
        }

        // Configure Git SSH command to use brokeforge's private key
        $sshKeyPath = '/home/brokeforge/.ssh/id_rsa';
        $gitSshCommand = sprintf(
            'GIT_SSH_COMMAND="ssh -i %s -o StrictHostKeyChecking=accept-new -o IdentitiesOnly=yes -o UserKnownHostsFile=/home/brokeforge/.ssh/known_hosts"',
            $sshKeyPath
        );

        // Determine the site directory (parent of document root)
        $siteDirectory = dirname($documentRoot);

        return [
            $this->track(ProvisionedSiteInstallerMilestones::CLONE_REPOSITORY),
            sprintf('rm -rf %1$s && mkdir -p %1$s', escapeshellarg($siteDirectory)),
            sprintf(
                '%s git clone -b %s %s %s',
                $gitSshCommand,
                escapeshellarg($branch),
                escapeshellarg($repositorySshUrl),
                escapeshellarg($siteDirectory)
            ),
            sprintf('chown -R %s:%s %s', $appUser, $appUser, escapeshellarg($siteDirectory)),
            sprintf('chmod -R 775 %s', escapeshellarg($siteDirectory)),
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
        return new ProvisionedSiteInstallerMilestones;
    }

    public function credentialType(): CredentialType
    {
        return CredentialType::Root;
    }
}
