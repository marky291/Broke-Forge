<?php

namespace App\Packages\Services\Firewall;

use App\Packages\Base\Milestones;
use App\Packages\Base\PackageRemover;
use App\Packages\Base\ServerPackage;
use App\Packages\Credentials\RootCredential;
use App\Packages\Credentials\SshCredential;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

/**
 * Firewall Rule Uninstaller
 *
 * Removes individual firewall rules from the remote server
 */
class FirewallRuleUninstaller extends PackageRemover implements ServerPackage
{
    /**
     * Generic name of the current package
     */
    public function packageName(): PackageName
    {
        return PackageName::FirewallUfw;
    }

    /**
     * Package categorization type
     */
    public function packageType(): PackageType
    {
        return PackageType::Firewall;
    }

    /**
     * Service type identifier for milestone tracking
     * @deprecated Use packageName() instead
     */
    protected function serviceType(): string
    {
        return $this->packageName()->value;
    }

    /**
     * Milestone implementation for progress tracking
     */
    public function milestones(): Milestones
    {
        return new FirewallRuleUninstallerMilestones;
    }

    /**
     * SSH credential type for remote execution
     */
    public function sshCredential(): SshCredential
    {
        return new RootCredential;
    }

    /**
     * Execute the removal process
     *
     * @param array $ruleConfig Configuration array with port, from_ip_address, rule_type
     */
    public function execute(array $ruleConfig): void
    {
        $this->remove($this->commands($ruleConfig));
    }

    /**
     * Generate SSH commands for removal
     *
     * @param array $ruleConfig Configuration array with port, from_ip_address, rule_type
     */
    protected function commands(array $ruleConfig): array
    {
        // Build the UFW delete command based on the rule configuration
        $ufwCommand = 'ufw --force delete ';

        // Build the rule specification to delete
        $ruleSpec = $ruleConfig['rule_type']; // 'allow' or 'deny'

        // Add source IP if specified
        if (!empty($ruleConfig['from_ip_address'])) {
            $ruleSpec .= " from {$ruleConfig['from_ip_address']}";
        }

        // Add port if specified
        if (!empty($ruleConfig['port'])) {
            $ruleSpec .= " to any port {$ruleConfig['port']}";
        }

        $deleteCommand = $ufwCommand . $ruleSpec;

        return [
            $this->track(FirewallRuleUninstallerMilestones::PREPARE_REMOVAL),

            // Delete the firewall rule
            $deleteCommand,

            // Reload UFW to apply changes
            'ufw --force reload',

            $this->track(FirewallRuleUninstallerMilestones::REMOVAL_COMPLETE),
        ];
    }
}