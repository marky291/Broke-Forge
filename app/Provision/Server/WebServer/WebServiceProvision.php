<?php

namespace App\Provision\Server\WebServer;

use App\Provision\Enums\ServiceType;
use App\Provision\InstallableService;
use App\Provision\Milestones;
use App\Provision\Server\Access\RootCredential;
use App\Provision\Server\Access\SshCredential;

class WebServiceProvision extends InstallableService
{
    protected function serviceType(): string
    {
        return ServiceType::WEBSERVER;
    }

    protected function milestones(): Milestones
    {
        return new WebServiceProvisionMilestones;
    }

    protected function sshCredential(): SshCredential
    {
        return new RootCredential;
    }

    public function provision(): void
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
        $userCredential = new \App\Provision\Server\Access\UserCredential;
        $appUser = $userCredential->user();

        return [

            $this->track(WebServiceProvisionMilestones::PREPARE_SYSTEM),

            // Update package lists and install prerequisites
            'DEBIAN_FRONTEND=noninteractive apt-get update -y',
            'DEBIAN_FRONTEND=noninteractive apt-get install -y ca-certificates curl gnupg lsb-release software-properties-common',

            $this->track(WebServiceProvisionMilestones::SETUP_REPOSITORY),

            // On Ubuntu, add Ondrej PPAs for PHP and NGINX (ignore errors on non-Ubuntu)
            'if command -v lsb_release >/dev/null 2>&1 && [ "$(lsb_release -is)" = "Ubuntu" ]; then add-apt-repository -y ppa:ondrej/php || true; add-apt-repository -y ppa:ondrej/nginx || true; DEBIAN_FRONTEND=noninteractive apt-get update -y; fi',

            $this->track(WebServiceProvisionMilestones::REMOVE_CONFLICTS),
            // Ensure Apache is not competing for port 80 (stop, disable, and mask if present)
            'systemctl stop apache2 >/dev/null 2>&1 || true',
            'systemctl disable apache2 >/dev/null 2>&1 || true',
            'systemctl mask apache2 >/dev/null 2>&1 || true',

            $this->track(WebServiceProvisionMilestones::INSTALL_SOFTWARE),

            // Optionally remove apache packages if installed
            'DEBIAN_FRONTEND=noninteractive apt-get remove -y --purge apache2 apache2-bin apache2-data apache2-utils libapache2-mod-php libapache2-mod-php* >/dev/null 2>&1 || true',

            // Install NGINX and PHP (attempt versioned packages first, then fall back to default php if needed)
            "DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends nginx {$phpPackages} || DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends nginx php-fpm php-cli php-common php-curl php-mbstring php-xml php-zip php-intl php-mysql php-gd",

            $this->track(WebServiceProvisionMilestones::ENABLE_SERVICES),
            // Enable and start services
            'systemctl enable --now nginx',
            "systemctl enable --now php{$phpVersion}-fpm || systemctl enable --now php-fpm",

            $this->track(WebServiceProvisionMilestones::CONFIGURE_FIREWALL),
            // Open HTTP/HTTPS ports if ufw exists (safe no-ops otherwise)
            'ufw allow 80/tcp >/dev/null 2>&1 || true',
            'ufw allow 443/tcp >/dev/null 2>&1 || true',

            $this->track(WebServiceProvisionMilestones::SETUP_DEFAULT_SITE),
            // Create default site structure in app user's home directory
            "mkdir -p /home/{$appUser}/default/public",

            // Create default index.php file with informative content
            $this->createDefaultSiteFile($appUser),

            $this->track(WebServiceProvisionMilestones::SET_PERMISSIONS),
            // Set proper ownership and permissions for app user's site directories
            "chown -R {$appUser}:{$appUser} /home/{$appUser}/",
            "chmod 755 /home/{$appUser}/",
            "chmod 755 /home/{$appUser}/default",
            "chmod 755 /home/{$appUser}/default/public",
            "chmod 644 /home/{$appUser}/default/public/index.php",

            // Add app user to www-data group for PHP-FPM compatibility
            "usermod -a -G www-data {$appUser}",

            $this->track(WebServiceProvisionMilestones::CONFIGURE_NGINX),
            // Create default Nginx configuration for the default site
            $this->createDefaultNginxConfig($appUser, $phpVersion),

            // Enable the default site
            'ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default',

            $this->track(WebServiceProvisionMilestones::VERIFY_INSTALL),
            // Test Nginx configuration
            'nginx -t',
            // Reload Nginx to apply configuration
            'systemctl reload nginx',
            // Get the status of nginx
            'systemctl status nginx',

            $this->track(WebServiceProvisionMilestones::COMPLETE),
        ];
    }

    /**
     * Create the command to write the default site file
     */
    protected function createDefaultSiteFile(string $appUser): string
    {
        $content = view('provision.default-site')->render();

        return "cat > /home/{$appUser}/default/public/index.php << 'EOF'\n{$content}\nEOF";
    }

    /**
     * Create the command to write the default Nginx configuration
     */
    protected function createDefaultNginxConfig(string $appUser, string $phpVersion): string
    {
        $nginxConfig = view('nginx.default', [
            'appUser' => $appUser,
            'phpVersion' => $phpVersion,
        ])->render();

        return "cat > /etc/nginx/sites-available/default << 'EOF'\n{$nginxConfig}\nEOF";
    }
}
