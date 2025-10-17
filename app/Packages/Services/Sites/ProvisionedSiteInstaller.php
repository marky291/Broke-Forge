<?php

namespace App\Packages\Services\Sites;

use App\Models\ServerSite;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageInstaller;
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
        $brokeforgeCredential = $this->server->credentials()
            ->where('user', 'brokeforge')
            ->first();
        $appUser = $brokeforgeCredential?->getUsername() ?: 'brokeforge';

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
        $brokeforgeCredential = $this->server->credentials()
            ->where('user', 'brokeforge')
            ->first();
        $appUser = $brokeforgeCredential?->getUsername() ?: 'brokeforge';

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
            ...($this->getGitCloneCommands($config, $documentRoot, $appUser, $site)),

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
    protected function getGitCloneCommands(array $config, string $documentRoot, string $appUser, ServerSite $site): array
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

        // Check if site has dedicated deploy key
        $hasDedicatedKey = $site->has_dedicated_deploy_key ?? false;
        $sshConfigCommands = [];
        $transformedRepositoryUrl = $repositorySshUrl;

        if ($hasDedicatedKey) {
            // Generate SSH config for dedicated deploy key
            $sshConfigCommands = $this->generateSshConfigCommands($site);
            // Transform URL to use host alias
            $transformedRepositoryUrl = $this->transformGitUrlForSshConfig($repositorySshUrl, $site->id);
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
            // Generate SSH config for dedicated deploy keys (if applicable)
            ...$sshConfigCommands,

            $this->track(ProvisionedSiteInstallerMilestones::CLONE_REPOSITORY),
            sprintf('rm -rf %1$s && mkdir -p %1$s', escapeshellarg($siteDirectory)),
            sprintf(
                '%s git clone -b %s %s %s',
                $gitSshCommand,
                escapeshellarg($branch),
                escapeshellarg($transformedRepositoryUrl),
                escapeshellarg($siteDirectory)
            ),
            sprintf('chown -R %s:%s %s', $appUser, $appUser, escapeshellarg($siteDirectory)),
            sprintf('chmod -R 775 %s', escapeshellarg($siteDirectory)),
        ];
    }

    /**
     * Generate SSH config commands for site-specific deploy keys
     *
     * Creates an SSH config entry that uses a host alias to route Git traffic
     * through the site-specific SSH key instead of the server-wide key.
     *
     * @param  ServerSite  $site  The site with a dedicated deploy key
     * @return array Array of SSH commands to configure host alias
     */
    protected function generateSshConfigCommands(ServerSite $site): array
    {
        $siteId = $site->id;
        $keyPath = "/home/brokeforge/.ssh/site_{$siteId}_rsa";

        // SSH config entry using host alias pattern
        // This allows Git to use site-specific keys via URL transformation
        $sshConfigEntry = <<<EOF
Host github.com-site-{$siteId}
  HostName github.com
  IdentityFile {$keyPath}
  IdentitiesOnly yes
  StrictHostKeyChecking accept-new
  UserKnownHostsFile /home/brokeforge/.ssh/known_hosts
EOF;

        return [
            // Ensure SSH config file exists
            'mkdir -p /home/brokeforge/.ssh',
            'touch /home/brokeforge/.ssh/config',
            'chmod 600 /home/brokeforge/.ssh/config',

            // Check if config entry already exists, append if not
            sprintf(
                'if ! grep -q "Host github.com-site-%d" /home/brokeforge/.ssh/config; then cat >> /home/brokeforge/.ssh/config << \'SSH_CONFIG_EOF\'\n%s\nSSH_CONFIG_EOF\nfi',
                $siteId,
                $sshConfigEntry
            ),
        ];
    }

    /**
     * Transform Git URL to use SSH config host alias
     *
     * Converts standard GitHub SSH URLs to use the site-specific host alias
     * defined in SSH config. This routes Git operations through the dedicated
     * deploy key for this site.
     *
     * Examples:
     *   git@github.com:owner/repo.git → git@github.com-site-123:owner/repo.git
     *   ssh://git@github.com/owner/repo.git → ssh://git@github.com-site-123/owner/repo.git
     *
     * @param  string  $originalUrl  The original Git SSH URL
     * @param  int  $siteId  The site ID for the host alias
     * @return string The transformed URL using the host alias
     */
    protected function transformGitUrlForSshConfig(string $originalUrl, int $siteId): string
    {
        // Transform git@github.com: format to use host alias
        if (str_starts_with($originalUrl, 'git@github.com:')) {
            return str_replace('git@github.com:', "git@github.com-site-{$siteId}:", $originalUrl);
        }

        // Transform ssh://git@github.com/ format to use host alias
        if (str_starts_with($originalUrl, 'ssh://git@github.com/')) {
            return str_replace('ssh://git@github.com/', "ssh://git@github.com-site-{$siteId}/", $originalUrl);
        }

        // Return unchanged if not a recognized GitHub SSH URL
        return $originalUrl;
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
}
