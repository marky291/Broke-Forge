<?php

namespace App\Packages\Services\Nginx;

use App\Enums\ReverseProxyType;
use App\Enums\ScheduleFrequency;
use App\Enums\TaskStatus;
use App\Models\ServerPhp;
use App\Models\ServerReverseProxy;
use App\Packages\Core\Base\Package;
use App\Packages\Core\Base\PackageInstaller;
use App\Packages\Enums\NodeVersion;
use App\Packages\Enums\PhpVersion;
use App\Packages\Services\Firewall\FirewallInstallerJob;
use App\Packages\Services\Firewall\FirewallRuleInstallerJob;
use App\Packages\Services\Node\NodeInstallerJob;
use App\Packages\Services\PHP\PhpInstallerJob;
use App\Packages\Services\Scheduler\ServerSchedulerInstallerJob;
use App\Packages\Services\Scheduler\Task\ServerScheduleTaskInstallerJob;
use App\Packages\Services\Supervisor\SupervisorInstallerJob;

/**
 * Nginx Web Server Installation Class
 *
 * Handles installation of NGINX web server with PHP dependency
 */
class NginxInstaller extends PackageInstaller implements \App\Packages\Core\Base\ServerPackage
{
    /**
     * Execute the Nginx web server installation
     */
    public function execute(PhpVersion $phpVersion): void
    {
        // Ensure firewall is installed and configured first
        FirewallInstallerJob::dispatchSync($this->server);

        // Get the firewall (should exist after FirewallInstallerJob)
        $firewall = $this->server->firewall()->firstOrFail();

        // Configure firewall rules for HTTP and HTTPS
        $firewallRules = [
            ['port' => '80', 'name' => 'HTTP', 'rule_type' => 'allow', 'from_ip_address' => null],
            ['port' => '443', 'name' => 'HTTPS', 'rule_type' => 'allow', 'from_ip_address' => null],
        ];

        // Create and install each firewall rule
        foreach ($firewallRules as $ruleData) {
            $rule = $firewall->rules()->create([
                'name' => $ruleData['name'],
                'port' => $ruleData['port'],
                'from_ip_address' => $ruleData['from_ip_address'],
                'rule_type' => $ruleData['rule_type'],
                'status' => 'pending',
            ]);

            FirewallRuleInstallerJob::dispatchSync($this->server, $rule);
        }

        $this->server->provision_state->put(5, TaskStatus::Success->value);
        $this->server->provision_state->put(6, TaskStatus::Installing->value);
        $this->server->save();

        // Create ServerPhp record FIRST with 'pending' status (Reverb Package Lifecycle)
        // Use firstOrCreate for idempotency (safe for job retries)
        $isFirstPhp = $this->server->phps()->count() === 0;
        $php = $this->server->phps()->firstOrCreate(
            [
                'server_id' => $this->server->id,
                'version' => $phpVersion->value,
            ],
            [
                'status' => \App\Enums\TaskStatus::Pending,
                'is_cli_default' => $isFirstPhp,
                'is_site_default' => $isFirstPhp,
            ]
        );

        // Pass the record to the job (not the ID)
        PhpInstallerJob::dispatchSync($this->server, $php);

        $this->server->provision_state->put(6, TaskStatus::Success->value);
        $this->server->provision_state->put(7, TaskStatus::Installing->value);
        $this->server->save();

        $this->install($this->commands($phpVersion));

        $this->server->provision_state->put(7, TaskStatus::Success->value);
        $this->server->provision_state->put(8, TaskStatus::Installing->value);
        $this->server->save();

        // Install Task scheduler and default task schedule job.
        ServerSchedulerInstallerJob::dispatchSync($this->server);

        // Create scheduled task record FIRST with 'pending' status (Reverb Package Lifecycle)
        $task = $this->server->scheduledTasks()->create([
            'name' => 'Remove unused packages',
            'command' => 'apt-get autoremove && apt-get autoclean',
            'frequency' => ScheduleFrequency::Weekly,
            'status' => \App\Enums\TaskStatus::Pending,
        ]);

        // Pass the record to the job (not the ID)
        ServerScheduleTaskInstallerJob::dispatchSync($this->server, $task);

        // Install the supervisor as it has low overhead and provides benefit to user.
        SupervisorInstallerJob::dispatchSync($this->server);

        // Install Node.js 22 by default (Composer will be installed automatically with first Node)
        // Use firstOrCreate for idempotency (safe for job retries)
        $isFirstNode = $this->server->nodes()->count() === 0;
        $node = $this->server->nodes()->firstOrCreate(
            [
                'server_id' => $this->server->id,
                'version' => NodeVersion::Node22->value,
            ],
            [
                'status' => TaskStatus::Pending,
                'is_default' => $isFirstNode,
            ]
        );

        // Pass the record to the job (not the ID)
        NodeInstallerJob::dispatchSync($this->server, $node);

        $this->server->provision_state->put(8, TaskStatus::Success->value);
        $this->server->save();
    }

    protected function commands(PhpVersion $phpVersion): array
    {
        // Get the app user that will own site directories
        $appUser = config('app.ssh_user', str_replace(' ', '', strtolower(config('app.name'))));

        return [

            // Update package lists and install prerequisites
            'DEBIAN_FRONTEND=noninteractive apt-get update -y',
            'DEBIAN_FRONTEND=noninteractive apt-get install -y ca-certificates curl gnupg lsb-release software-properties-common',

            // On Ubuntu, add Ondrej PPA for NGINX (ignore errors on non-Ubuntu)
            // PHP repository is handled by PhpInstaller
            'if command -v lsb_release >/dev/null 2>&1 && [ "$(lsb_release -is)" = "Ubuntu" ]; then add-apt-repository -y ppa:ondrej/nginx || true; DEBIAN_FRONTEND=noninteractive apt-get update -y; fi',

            // Ensure Apache is not competing for port 80 (stop, disable, and mask if present)
            'systemctl stop apache2 >/dev/null 2>&1 || true',
            'systemctl disable apache2 >/dev/null 2>&1 || true',
            'systemctl mask apache2 >/dev/null 2>&1 || true',

            // Remove apache packages if installed
            'DEBIAN_FRONTEND=noninteractive apt-get remove -y --purge apache2 apache2-bin apache2-data apache2-utils libapache2-mod-php libapache2-mod-php* >/dev/null 2>&1 || true',

            // Install NGINX only (PHP is already installed via PhpInstallerJob)
            'DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends nginx',

            // Enable and start Nginx service (PHP-FPM is already running from PhpInstaller)
            'systemctl enable --now nginx',

            // Create default site structure in app user's home directory
            "mkdir -p /home/{$appUser}/default/public",

            // Create default index.php file from the blade template
            function () use ($appUser) {
                $content = view('provision.default-site')->render();

                return "echo '{$content}' > /home/{$appUser}/default/public/index.php";
            },

            // Set proper ownership and permissions for app user's site directories
            "chown -R {$appUser}:{$appUser} /home/{$appUser}/",
            "chmod 755 /home/{$appUser}/",
            "chmod 755 /home/{$appUser}/default",
            "chmod 755 /home/{$appUser}/default/public",

            // Add app user to www-data group for PHP-FPM compatibility
            "usermod -a -G www-data {$appUser}",

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
                    'status' => TaskStatus::Active->value,
                ]);
            },

            // Mark installation as completed
        ];
    }
}
