<?php

namespace App\Packages\Services\Firewall;

use App\Packages\Base\Milestones;
use App\Packages\Base\PackageInstaller;
use App\Packages\Base\ServerPackage;
use App\Packages\Enums\CredentialType;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;
use App\Packages\Services\Firewall\Concerns\ManagesFirewallRules;

/**
 * Firewall Rule Installation Class
 *
 * Handles adding specific firewall rules to an existing UFW installation
 */
class FirewallRuleInstaller extends PackageInstaller implements ServerPackage
{
    use ManagesFirewallRules;

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
        return new FirewallRuleInstallerMilestones;
    }

    public function credentialType(): CredentialType
    {
        return CredentialType::Root;
    }

    /**
     * Execute firewall rule configuration
     *
     * @param  array  $rules  Array of firewall rules to apply
     * @param  string  $context  Context for these rules (e.g., 'nginx', 'mysql', 'custom')
     *
     * Example rules:
     * [
     *   ['port' => 80, 'protocol' => 'tcp', 'action' => 'allow', 'comment' => 'HTTP'],
     *   ['port' => 443, 'protocol' => 'tcp', 'action' => 'allow', 'comment' => 'HTTPS'],
     *   ['port' => 3306, 'protocol' => 'tcp', 'action' => 'allow', 'source' => '10.0.0.0/8', 'comment' => 'MySQL'],
     * ]
     */
    public function execute(array $rules, string $context = 'custom'): void
    {
        if (empty($rules)) {
            throw new \InvalidArgumentException('At least one firewall rule must be provided');
        }

        $this->install($this->commands($rules, $context));
    }

    protected function commands(array $rules, string $context): array
    {
        $commands = [
            $this->track(FirewallRuleInstallerMilestones::VERIFY_FIREWALL),

            // Verify UFW is installed and enabled
            'which ufw >/dev/null 2>&1 || (echo "UFW is not installed" && exit 1)',
            'ufw status | grep -q "Status: active" || (echo "UFW is not enabled" && exit 1)',

            $this->track(FirewallRuleInstallerMilestones::APPLY_RULES),
        ];

        // Add each rule with proper error handling
        foreach ($rules as $rule) {
            $commands[] = $this->buildUfwCommand($rule);
        }

        // Continue with remaining commands
        $commands = array_merge($commands, [
            $this->track(FirewallRuleInstallerMilestones::RELOAD_FIREWALL),

            // Reload UFW to ensure all rules are applied
            'ufw reload',

            // Show current status
            'ufw status numbered',

            // Save firewall rules to database
            $this->createFirewallRules($rules, $context),

            $this->track(FirewallRuleInstallerMilestones::COMPLETE),
        ]);

        return $commands;
    }
}
