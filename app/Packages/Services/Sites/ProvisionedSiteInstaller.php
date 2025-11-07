<?php

namespace App\Packages\Services\Sites;

use App\Enums\TaskStatus;
use App\Models\ServerSite;
use App\Packages\Core\Base\PackageInstaller;

/**
 * Site Installation Class
 *
 * Provisions a site using SSH terminal commands to a remote
 * server that has NGINX installed
 */
class ProvisionedSiteInstaller extends PackageInstaller implements \App\Packages\Core\Base\ServerPackage
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
        // Site root is where deployments are stored
        $siteRoot = "/home/brokeforge/deployments/{$domain}";

        // Site symlink is what nginx points to
        $siteSymlink = "/home/brokeforge/{$domain}";

        // Document root follows the symlink: /home/brokeforge/{domain}/public
        $documentRootPath = "{$siteSymlink}/public";

        // Generate nginx configuration inline (avoid helper methods)
        $nginxConfig = view('nginx.site', [
            'domain' => $domain,
            'documentRoot' => $documentRootPath,
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
            // Create deployments and shared directories
            "mkdir -p {$siteRoot}/shared/storage",
            "mkdir -p {$siteRoot}/shared/vendor",
            "mkdir -p {$siteRoot}/shared/node_modules",
            "touch {$siteRoot}/shared/.env",
            "mkdir -p /var/log/nginx/{$domain}",

            "cat > /etc/nginx/sites-available/{$domain} << 'NGINX_CONFIG_EOF'\n{$nginxConfig}\nNGINX_CONFIG_EOF",

            "ln -sf /etc/nginx/sites-available/{$domain} /etc/nginx/sites-enabled/{$domain}",

            'nginx -t',

            'nginx -s reload',

            // Set ownership on shared directories
            "chown -R {$appUser}:{$appUser} {$siteRoot}/shared",
            "chmod -R 775 {$siteRoot}/shared",

            // Add git clone commands if repository is configured
            ...($this->getGitCloneCommands($config, $siteRoot, $appUser, $site)),

            function () use ($site, $config) {
                $site->refresh();

                $updates = [
                    'status' => 'active',
                    'provisioned_at' => now(),
                ];

                // Update git_status if repository was cloned
                if (isset($config['git_repository'])) {
                    $updates['git_status'] = TaskStatus::Success;
                    $updates['git_installed_at'] = now();
                }

                $site->update($updates);
            },
        ];
    }

    /**
     * Get git clone commands if repository is configured
     */
    protected function getGitCloneCommands(array $config, string $siteRoot, string $appUser, ServerSite $site): array
    {
        if (! isset($config['git_repository'])) {
            // If no git repository, create a simple placeholder deployment
            return $this->createPlaceholderDeployment($siteRoot, $appUser, $site);
        }

        $gitConfig = $config['git_repository'];
        $repository = $gitConfig['repository'] ?? null;
        $branch = $gitConfig['branch'] ?? 'main';

        if (! $repository) {
            return $this->createPlaceholderDeployment($siteRoot, $appUser, $site);
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
            return $this->createPlaceholderDeployment($siteRoot, $appUser, $site);
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

        // Generate timestamp for deployment directory: ddMMYYYY-HHMMSS
        $timestamp = now()->format('dmY-His');
        $deploymentPath = "{$siteRoot}/{$timestamp}";
        $siteSymlink = "/home/brokeforge/{$site->domain}";

        $commands = [
            // Generate SSH config for dedicated deploy keys (if applicable)
            ...$sshConfigCommands,

            // Clone repository to timestamp deployment directory
            sprintf('rm -rf %s', escapeshellarg($deploymentPath)),
            sprintf(
                '%s git clone -b %s %s %s',
                $gitSshCommand,
                escapeshellarg($branch),
                escapeshellarg($transformedRepositoryUrl),
                escapeshellarg($deploymentPath)
            ),

            // Create symlinks from deployment to shared directories
            sprintf('ln -sfn ../shared/storage %s/storage', escapeshellarg($deploymentPath)),
            sprintf('ln -sfn ../shared/.env %s/.env', escapeshellarg($deploymentPath)),
            sprintf('ln -sfn ../shared/vendor %s/vendor', escapeshellarg($deploymentPath)),
            sprintf('ln -sfn ../shared/node_modules %s/node_modules', escapeshellarg($deploymentPath)),

            // Create site symlink pointing to initial deployment
            sprintf('ln -sfn deployments/%s/%s %s', escapeshellarg($site->domain), $timestamp, escapeshellarg($siteSymlink)),

            // Set ownership
            sprintf('chown -R %s:%s %s', $appUser, $appUser, escapeshellarg($siteRoot)),
            sprintf('chmod -R 775 %s', escapeshellarg($siteRoot)),

            // Create initial deployment record in database
            function () use ($site, $deploymentPath, $branch) {
                // Get the commit SHA from the repository
                $commitSha = null;
                try {
                    $process = $site->server->ssh('brokeforge')->execute("cd {$deploymentPath} && git rev-parse HEAD");
                    if ($process->isSuccessful()) {
                        $commitSha = trim($process->getOutput());
                    }
                } catch (\Exception $e) {
                    // Ignore errors getting commit SHA
                }

                // Create initial deployment record
                $initialDeployment = \App\Models\ServerDeployment::create([
                    'server_id' => $site->server_id,
                    'server_site_id' => $site->id,
                    'status' => TaskStatus::Success,
                    'deployment_script' => 'Initial deployment',
                    'deployment_path' => $deploymentPath,
                    'commit_sha' => $commitSha,
                    'branch' => $branch,
                    'started_at' => now(),
                    'completed_at' => now(),
                    'triggered_by' => 'system',
                ]);

                // Set as active deployment
                $site->update(['active_deployment_id' => $initialDeployment->id]);
            },
        ];

        return $commands;
    }

    /**
     * Create placeholder deployment when no Git repository is configured
     */
    protected function createPlaceholderDeployment(string $siteRoot, string $appUser, ServerSite $site): array
    {
        // Generate timestamp for deployment directory: ddMMYYYY-HHMMSS
        $timestamp = now()->format('dmY-His');
        $deploymentPath = "{$siteRoot}/{$timestamp}";
        $siteSymlink = "/home/brokeforge/{$site->domain}";

        return [
            // Create initial deployment directory with placeholder
            sprintf('mkdir -p %s/public', escapeshellarg($deploymentPath)),
            sprintf('echo \'<?php phpinfo();\' > %s/public/index.php', escapeshellarg($deploymentPath)),

            // Create symlinks from deployment to shared directories
            sprintf('ln -sfn ../shared/storage %s/storage', escapeshellarg($deploymentPath)),
            sprintf('ln -sfn ../shared/.env %s/.env', escapeshellarg($deploymentPath)),
            sprintf('ln -sfn ../shared/vendor %s/vendor', escapeshellarg($deploymentPath)),
            sprintf('ln -sfn ../shared/node_modules %s/node_modules', escapeshellarg($deploymentPath)),

            // Create site symlink pointing to initial deployment
            sprintf('ln -sfn deployments/%s/%s %s', escapeshellarg($site->domain), $timestamp, escapeshellarg($siteSymlink)),

            // Set ownership
            sprintf('chown -R %s:%s %s', $appUser, $appUser, escapeshellarg($siteRoot)),
            sprintf('chmod -R 775 %s', escapeshellarg($siteRoot)),
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

    public function milestones(): Milestones
    {
        return new ProvisionedSiteInstallerMilestones;
    }
}
