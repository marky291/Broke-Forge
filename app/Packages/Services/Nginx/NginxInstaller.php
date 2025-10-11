<?php

namespace App\Packages\Services\Nginx;

use App\Enums\ReverseProxyStatus;
use App\Enums\ReverseProxyType;
use App\Enums\ScheduleFrequency;
use App\Models\ServerReverseProxy;
use App\Packages\Base\Milestones;
use App\Packages\Base\Package;
use App\Packages\Base\PackageInstaller;
use App\Packages\Enums\CredentialType;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;
use App\Packages\Enums\PhpVersion;
use App\Packages\Enums\ProvisionStatus;
use App\Packages\Services\Firewall\FirewallInstallerJob;
use App\Packages\Services\Firewall\FirewallRuleInstallerJob;
use App\Packages\Services\PHP\PhpInstallerJob;
use App\Packages\Services\Scheduler\ServerSchedulerInstallerJob;
use App\Packages\Services\Scheduler\Task\ServerScheduleTaskInstallerJob;

/**
 * Nginx Web Server Installation Class
 *
 * Handles installation of NGINX web server with PHP dependency
 */
class NginxInstaller extends PackageInstaller implements \App\Packages\Base\ServerPackage
{
    public function packageName(): PackageName
    {
        return PackageName::Nginx;
    }

    public function packageType(): PackageType
    {
        return PackageType::ReverseProxy;
    }

    public function milestones(): Milestones
    {
        return new NginxInstallerMilestones;
    }

    public function credentialType(): CredentialType
    {
        return CredentialType::Root;
    }

    /**
     * Execute the Nginx web server installation
     */
    public function execute(PhpVersion $phpVersion): void
    {
        // Ensure firewall is installed and configured first
        FirewallInstallerJob::dispatchSync($this->server);

        // Configure firewall rules for HTTP and HTTPS
        $firewallRules = [
            ['port' => '80', 'name' => 'HTTP', 'rule_type' => 'allow', 'from_ip_address' => null],
            ['port' => '443', 'name' => 'HTTPS', 'rule_type' => 'allow', 'from_ip_address' => null],
        ];

        // Install each firewall rule (job will create DB record and install on remote server)
        foreach ($firewallRules as $ruleData) {
            FirewallRuleInstallerJob::dispatchSync($this->server, $ruleData);
        }

        $this->server->provision->put(5, ProvisionStatus::Completed->value);
        $this->server->provision->put(6, ProvisionStatus::Installing->value);
        $this->server->save();

        PhpInstallerJob::dispatchSync($this->server, $phpVersion);

        $this->server->provision->put(6, ProvisionStatus::Completed->value);
        $this->server->provision->put(7, ProvisionStatus::Installing->value);
        $this->server->save();

        $this->install($this->commands($phpVersion));

        $this->server->provision->put(7, ProvisionStatus::Completed->value);
        $this->server->provision->put(8, ProvisionStatus::Installing->value);
        $this->server->save();

        // Install Task scheduler and default task schedule job.
        ServerSchedulerInstallerJob::dispatchSync($this->server);

        // Install default scheduled task (job will create DB record and install on remote server)
        ServerScheduleTaskInstallerJob::dispatchSync($this->server, [
            'name' => 'Remove unused packages',
            'command' => 'apt-get autoremove && apt-get autoclean',
            'frequency' => ScheduleFrequency::Weekly,
        ]);

        $this->server->provision->put(8, ProvisionStatus::Completed->value);
        $this->server->save();
    }

    protected function commands(PhpVersion $phpVersion): array
    {
        // Get the app user that will own site directories
        $appUser = config('app.ssh_user', str_replace(' ', '', strtolower(config('app.name'))));

        return [

            $this->track(NginxInstallerMilestones::PREPARE_SYSTEM),

            // Update package lists and install prerequisites
            'DEBIAN_FRONTEND=noninteractive apt-get update -y',
            'DEBIAN_FRONTEND=noninteractive apt-get install -y ca-certificates curl gnupg lsb-release software-properties-common',

            $this->track(NginxInstallerMilestones::SETUP_REPOSITORY),

            // On Ubuntu, add Ondrej PPA for NGINX (ignore errors on non-Ubuntu)
            // PHP repository is handled by PhpInstaller
            'if command -v lsb_release >/dev/null 2>&1 && [ "$(lsb_release -is)" = "Ubuntu" ]; then add-apt-repository -y ppa:ondrej/nginx || true; DEBIAN_FRONTEND=noninteractive apt-get update -y; fi',

            $this->track(NginxInstallerMilestones::REMOVE_CONFLICTS),
            // Ensure Apache is not competing for port 80 (stop, disable, and mask if present)
            'systemctl stop apache2 >/dev/null 2>&1 || true',
            'systemctl disable apache2 >/dev/null 2>&1 || true',
            'systemctl mask apache2 >/dev/null 2>&1 || true',

            $this->track(NginxInstallerMilestones::INSTALL_SOFTWARE),

            // Remove apache packages if installed
            'DEBIAN_FRONTEND=noninteractive apt-get remove -y --purge apache2 apache2-bin apache2-data apache2-utils libapache2-mod-php libapache2-mod-php* >/dev/null 2>&1 || true',

            // Install NGINX only (PHP is already installed via PhpInstallerJob)
            'DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends nginx',

            $this->track(NginxInstallerMilestones::ENABLE_SERVICES),
            // Enable and start Nginx service (PHP-FPM is already running from PhpInstaller)
            'systemctl enable --now nginx',

            $this->track(NginxInstallerMilestones::SETUP_DEFAULT_SITE),
            // Create default site structure in app user's home directory
            "mkdir -p /home/{$appUser}/default/public",

            // Create default index.php file from the blade template
            function () use ($appUser) {
                $content = view('provision.default-site')->render();

                return "echo '{$content}' > /home/{$appUser}/default/public/index.php";
            },

            $this->track(NginxInstallerMilestones::SET_PERMISSIONS),
            // Set proper ownership and permissions for app user's site directories
            "chown -R {$appUser}:{$appUser} /home/{$appUser}/",
            "chmod 755 /home/{$appUser}/",
            "chmod 755 /home/{$appUser}/default",
            "chmod 755 /home/{$appUser}/default/public",

            // Add app user to www-data group for PHP-FPM compatibility
            "usermod -a -G www-data {$appUser}",

            $this->track(NginxInstallerMilestones::CONFIGURE_NGINX),
            // Create default Nginx configuration for the default site (inline config generation)
            function () use ($appUser, $phpVersion) {
                $nginxConfig = view('nginx.default', [
                    'appUser' => $appUser,
                    'phpVersion' => $phpVersion,
                ])->render();

                return "cat > /etc/nginx/sites-available/default << 'EOF'\n{$nginxConfig}\nEOF";
            },

            // Persist the default Nginx site now that provisioning succeeded
            function () use ($appUser, $phpVersion) {
                $this->server->sites()->updateOrCreate(
                    ['domain' => 'default'],
                    [
                        'document_root' => "/home/{$appUser}/default",
                        'nginx_config_path' => '/etc/nginx/sites-available/default',
                        'php_version' => $phpVersion,
                        'ssl_enabled' => false,
                        'configuration' => ['is_default_site' => true],
                        'status' => 'active',
                        'provisioned_at' => now(),
                        'deprovisioned_at' => null,
                    ]
                );
            },

            // Enable the default site
            'ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default',

            $this->track(NginxInstallerMilestones::VERIFY_INSTALL),
            // Test Nginx configuration
            'nginx -t',
            // Reload Nginx to apply configuration
            'systemctl reload nginx',
            // Get the status of nginx
            'systemctl status nginx',

            // Save Nginx installation to database
            function () {
                ServerReverseProxy::create([
                    'server_id' => $this->server->id,
                    'type' => ReverseProxyType::Nginx->value,
                    'version' => null,
                    'worker_processes' => 'auto',
                    'worker_connections' => 1024,
                    'status' => ReverseProxyStatus::Active->value,
                ]);
            },

            // Mark installation as completed
            $this->track(NginxInstallerMilestones::COMPLETE),
        ];
    }
}
