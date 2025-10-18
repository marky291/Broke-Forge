<?php

namespace App\Packages\Services\Firewall;

use App\Packages\Base\PackageInstaller;
use App\Packages\Base\ServerPackage;
use App\Packages\Services\Firewall\Concerns\ManagesFirewallRules;

/**
 * Firewall Installation Class
 *
 * Handles UFW firewall installation and basic configuration
 */
class FirewallInstaller extends PackageInstaller implements ServerPackage
{
    use ManagesFirewallRules;

    /**
     * Execute firewall installation and basic configuration
     */
    public function execute(): void
    {
        $this->install($this->commands());
    }

    protected function commands(): array
    {
        return [

            // Update package lists
            'DEBIAN_FRONTEND=noninteractive apt-get update -y',

            // Install UFW if not already installed
            'DEBIAN_FRONTEND=noninteractive apt-get install -y ufw',

            // Set default policies (deny incoming, allow outgoing)
            'ufw default deny incoming',
            'ufw default allow outgoing',

            // Allow SSH by default to prevent lockout
            $this->buildUfwCommand(['port' => 22, 'protocol' => 'tcp', 'action' => 'allow', 'comment' => 'SSH']),

            // Enable UFW
            'ufw --force enable',

            // Show status
            'ufw status verbose',

            // Save firewall and SSH rule to database
            $this->createFirewallRules([
                ['port' => 22, 'protocol' => 'tcp', 'action' => 'allow', 'comment' => 'SSH'],
            ], 'ssh'),

        ];
    }
}
