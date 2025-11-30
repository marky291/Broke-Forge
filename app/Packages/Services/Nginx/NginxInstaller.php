<?php

namespace App\Packages\Services\Nginx;

use App\Enums\ReverseProxyType;
use App\Enums\ScheduleFrequency;
use App\Enums\TaskStatus;
use App\Models\AvailableFramework;
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
use App\Packages\Services\TimeSync\TimeSyncInstallerJob;

/**
 * Nginx Web Server Installation Class
 *
 * Handles installation of NGINX web server with PHP dependency
 */
class NginxInstaller extends PackageInstaller implements \App\Packages\Core\Base\ServerPackage
{
    /**
     * Execute the Nginx web server installation
     *
     * @param  int  $resumeFromStep  The step to resume from (5-8). Steps before this are skipped.
     */
    public function execute(PhpVersion $phpVersion, int $resumeFromStep = 5): void
    {
        // Step 5: Firewall installation
        if ($resumeFromStep <= 5) {
            // Make sure the system time is synchronized before package operations
            TimeSyncInstallerJob::dispatchSync($this->server);

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
            $this->server->save();
        }

        // Step 6: PHP installation
        if ($resumeFromStep <= 6) {
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
            $this->server->save();
        }

        // Step 7: Nginx installation
        if ($resumeFromStep <= 7) {
            $this->server->provision_state->put(7, TaskStatus::Installing->value);
            $this->server->save();

            $this->install($this->commands($phpVersion));

            $this->server->provision_state->put(7, TaskStatus::Success->value);
            $this->server->save();
        }

        // Step 8: Final touches (Scheduler, Supervisor, Node)
        if ($resumeFromStep <= 8) {
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
    }

    protected function commands(PhpVersion $phpVersion): array
    {
        // Get the app user that will own site directories
        $appUser = config('app.ssh_user', str_replace(' ', '', strtolower(config('app.name'))));

        // Generate deployment timestamp (ddMMYYYY-HHMMSS format - same as site deployments)
        $timestamp = now()->format('dmY-His');
        $deploymentPath = "/home/{$appUser}/deployments/default/{$timestamp}";

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

            // Create default site deployment directory structure (symlink-based architecture)
            "mkdir -p {$deploymentPath}/public",

            // Create default index.php file from the blade template
            function () use ($deploymentPath) {
                $content = view('provision.default-site')->render();

                return "echo '{$content}' > {$deploymentPath}/public/index.php";
            },

            // Create symlink from default to the deployment directory (enables future default site switching)
            "ln -sfn deployments/default/{$timestamp} /home/{$appUser}/default",

            // Set proper ownership and permissions for app user's site directories
            "chown -R {$appUser}:{$appUser} /home/{$appUser}/",
            "chmod 755 /home/{$appUser}/",
            "chmod 755 /home/{$appUser}/deployments",
            "chmod 755 /home/{$appUser}/deployments/default",
            "chmod 755 /home/{$appUser}/deployments/default/{$timestamp}",
            "chmod 755 /home/{$appUser}/deployments/default/{$timestamp}/public",

            // Add app user to www-data group for PHP-FPM compatibility
            "usermod -a -G www-data {$appUser}",

            // Create default Nginx configuration for the default site (inline config generation)
            function () use ($appUser, $phpVersion) {
                $nginxConfig = view('nginx.default', [
                    'appUser' => $appUser,
                    'phpVersion' => $phpVersion,
                    'publicDirectory' => '/public',
                ])->render();

                return "cat > /etc/nginx/sites-available/default << 'EOF'\n{$nginxConfig}\nEOF";
            },

            // Persist the default Nginx site now that provisioning succeeded
            function () use ($appUser, $phpVersion, $deploymentPath) {
                $staticHtmlFramework = AvailableFramework::findBySlug(AvailableFramework::STATIC_HTML);

                $this->server->sites()->updateOrCreate(
                    ['domain' => 'default'],
                    [
                        'available_framework_id' => $staticHtmlFramework?->id,
                        'document_root' => "/home/{$appUser}/default",
                        'nginx_config_path' => '/etc/nginx/sites-available/default',
                        'php_version' => $phpVersion,
                        'ssl_enabled' => false,
                        'is_default' => true,
                        'default_site_status' => TaskStatus::Active,
                        'configuration' => [
                            'is_default_site' => true,
                            'default_deployment_path' => $deploymentPath,
                        ],
                        'status' => 'active',
                        'installed_at' => now(),
                        'uninstalled_at' => null,
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
