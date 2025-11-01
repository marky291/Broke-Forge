<?php

namespace App\Packages\Services\Firewall;

use App\Packages\Core\Base\PackageRemover;
use App\Packages\Core\Base\ServerPackage;

/**
 * Firewall Rule Uninstaller
 *
 * Removes individual firewall rules from the remote server
 */
class FirewallRuleUninstaller extends PackageRemover implements ServerPackage
{
    /**
     * Execute the removal process
     *
     * @param  array  $ruleConfig  Configuration array with port, from_ip_address, rule_type
     */
    public function execute(array $ruleConfig): void
    {
        $this->remove($this->commands($ruleConfig));
    }

    /**
     * Generate SSH commands for removal
     *
     * @param  array  $ruleConfig  Configuration array with port, from_ip_address, rule_type
     */
    protected function commands(array $ruleConfig): array
    {
        // Build the UFW delete command based on the rule configuration
        $ufwCommand = 'ufw --force delete ';

        // Build the rule specification to delete
        $ruleSpec = $ruleConfig['rule_type']; // 'allow' or 'deny'

        // Add source IP if specified
        if (! empty($ruleConfig['from_ip_address'])) {
            $ruleSpec .= " from {$ruleConfig['from_ip_address']}";
        }

        // Add port if specified
        if (! empty($ruleConfig['port'])) {
            $ruleSpec .= " to any port {$ruleConfig['port']}";
        }

        $deleteCommand = $ufwCommand.$ruleSpec;

        return [

            // Delete the firewall rule
            $deleteCommand,

            // Reload UFW to apply changes
            'ufw --force reload',

        ];
    }
}
