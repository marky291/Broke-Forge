<?php

namespace App\Packages\Services\Firewall;

use App\Packages\Base\Milestones;
use App\Packages\Base\PackageInstaller;
use App\Packages\Credentials\RootCredential;
use App\Packages\Credentials\SshCredential;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;
use App\Packages\Enums\PackageVersion;

/**
 * Firewall Installation Class
 *
 * Handles UFW firewall installation and basic configuration
 */
class FirewallInstaller extends PackageInstaller
{
    public function packageName(): PackageName
    {
        return PackageName::FirewallUfw;
    }

    public function packageType(): PackageType
    {
        return PackageType::Firewall;
    }

    public function milestones(): Milestones
    {
        return new FirewallInstallerMilestones;
    }

    public function sshCredential(): SshCredential
    {
        return new RootCredential;
    }

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
            $this->track(FirewallInstallerMilestones::PREPARE_SYSTEM),

            // Update package lists
            'DEBIAN_FRONTEND=noninteractive apt-get update -y',

            $this->track(FirewallInstallerMilestones::INSTALL_FIREWALL),

            // Install UFW if not already installed
            'DEBIAN_FRONTEND=noninteractive apt-get install -y ufw',

            $this->track(FirewallInstallerMilestones::CONFIGURE_DEFAULTS),

            // Set default policies (deny incoming, allow outgoing)
            'ufw default deny incoming',
            'ufw default allow outgoing',

            // Allow SSH by default to prevent lockout
            'ufw allow 22/tcp comment "SSH"',

            $this->track(FirewallInstallerMilestones::ENABLE_FIREWALL),

            // Enable UFW
            'ufw --force enable',

            // Show status
            'ufw status verbose',

            // Persist firewall installation to database
            $this->persist(
                PackageType::Firewall,
                PackageName::FirewallUfw,
                PackageVersion::Version1,
                [
                    'default_incoming' => 'deny',
                    'default_outgoing' => 'allow',
                    'enabled' => true,
                    'base_rules' => [
                        ['port' => 22, 'protocol' => 'tcp', 'action' => 'allow', 'comment' => 'SSH']
                    ]
                ]
            ),

            $this->track(FirewallInstallerMilestones::COMPLETE),
        ];
    }
}