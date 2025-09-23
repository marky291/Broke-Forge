<?php

namespace App\Packages\Services\Nginx;

use App\Packages\Base\Milestones;
use App\Packages\Base\PackageInstaller;
use App\Packages\Credentials\RootCredential;
use App\Packages\Credentials\SshCredential;
use App\Packages\Credentials\UserCredential;
use App\Packages\Enums\ServiceType;

/**
 * Web Server Installation Class
 *
 * Handles installation of NGINX and PHP-FPM with progress tracking
 */
class NginxInstaller extends PackageInstaller
{
    protected function serviceType(): string
    {
        return ServiceType::WEBSERVER;
    }

    protected function milestones(): Milestones
    {
        return new NginxInstallerMilestones;
    }

    protected function sshCredential(): SshCredential
    {
        return new RootCredential;
    }

    /**
     * Execute the web server installation
     */
    public function execute(): void
    {
        $phpService = $this->server->services()->where('service_name', 'php')->latest('id')->first();
        $phpVersion = $phpService->configuration['version'];

        // Compose common PHP packages for the chosen version.
        $phpPackages = implode(' ', [
            "php{$phpVersion}-fpm",
            "php{$phpVersion}-cli",
            "php{$phpVersion}-common",
            "php{$phpVersion}-curl",
            "php{$phpVersion}-mbstring",
            "php{$phpVersion}-xml",
            "php{$phpVersion}-zip",
            "php{$phpVersion}-intl",
            "php{$phpVersion}-mysql",
            "php{$phpVersion}-gd",
        ]);

        $this->install($this->commands($phpVersion, $phpPackages));
    }

    protected function commands(string $phpVersion, $phpPackages): array
    {
        // Get the app user that will own site directories
        $userCredential = new UserCredential;
        $appUser = $userCredential->user();

        return [

            $this->track(NginxInstallerMilestones::PREPARE_SYSTEM),

            // Update package lists and install prerequisites
            'DEBIAN_FRONTEND=noninteractive apt-get update -y',
            'DEBIAN_FRONTEND=noninteractive apt-get install -y ca-certificates curl gnupg lsb-release software-properties-common',

            $this->track(NginxInstallerMilestones::SETUP_REPOSITORY),

            // On Ubuntu, add Ondrej PPAs for PHP and NGINX (ignore errors on non-Ubuntu)
            'if command -v lsb_release >/dev/null 2>&1 && [ "$(lsb_release -is)" = "Ubuntu" ]; then add-apt-repository -y ppa:ondrej/php || true; add-apt-repository -y ppa:ondrej/nginx || true; DEBIAN_FRONTEND=noninteractive apt-get update -y; fi',

            $this->track(NginxInstallerMilestones::REMOVE_CONFLICTS),
            // Ensure Apache is not competing for port 80 (stop, disable, and mask if present)
            'systemctl stop apache2 >/dev/null 2>&1 || true',
            'systemctl disable apache2 >/dev/null 2>&1 || true',
            'systemctl mask apache2 >/dev/null 2>&1 || true',

            $this->track(NginxInstallerMilestones::INSTALL_SOFTWARE),

            // Optionally remove apache packages if installed
            'DEBIAN_FRONTEND=noninteractive apt-get remove -y --purge apache2 apache2-bin apache2-data apache2-utils libapache2-mod-php libapache2-mod-php* >/dev/null 2>&1 || true',

            // Install NGINX and PHP (attempt versioned packages first, then fall back to default php if needed)
            "DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends nginx {$phpPackages} || DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends nginx php-fpm php-cli php-common php-curl php-mbstring php-xml php-zip php-intl php-mysql php-gd",

            $this->track(NginxInstallerMilestones::ENABLE_SERVICES),
            // Enable and start services
            'systemctl enable --now nginx',
            "systemctl enable --now php{$phpVersion}-fpm || systemctl enable --now php-fpm",

            $this->track(NginxInstallerMilestones::CONFIGURE_FIREWALL),
            // Open HTTP/HTTPS ports if ufw exists (safe no-ops otherwise)
            'ufw allow 80/tcp >/dev/null 2>&1 || true',
            'ufw allow 443/tcp >/dev/null 2>&1 || true',

            $this->track(NginxInstallerMilestones::SETUP_DEFAULT_SITE),
            // Create default site structure in app user's home directory
            "mkdir -p /home/{$appUser}/default/public",

            // Create default index.php file with informative content (inline config generation)
            function () use ($appUser) {
                $content = view('provision.default-site')->render();

                return "cat > /home/{$appUser}/default/public/index.php << 'EOF'\n{$content}\nEOF";
            },

            $this->track(NginxInstallerMilestones::SET_PERMISSIONS),
            // Set proper ownership and permissions for app user's site directories
            "chown -R {$appUser}:{$appUser} /home/{$appUser}/",
            "chmod 755 /home/{$appUser}/",
            "chmod 755 /home/{$appUser}/default",
            "chmod 755 /home/{$appUser}/default/public",
            "chmod 644 /home/{$appUser}/default/public/index.php",

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
            fn () => $this->server->sites()->updateOrCreate(
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
            ),

            // Enable the default site
            'ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default',

            $this->track(NginxInstallerMilestones::VERIFY_INSTALL),
            // Test Nginx configuration
            'nginx -t',
            // Reload Nginx to apply configuration
            'systemctl reload nginx',
            // Get the status of nginx
            'systemctl status nginx',

            $this->track(NginxInstallerMilestones::COMPLETE),
        ];
    }
}
