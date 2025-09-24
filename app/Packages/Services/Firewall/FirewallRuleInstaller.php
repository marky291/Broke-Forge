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
 * Firewall Rule Installation Class
 *
 * Handles adding specific firewall rules to an existing UFW installation
 */
class FirewallRuleInstaller extends PackageInstaller
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
        return new FirewallRuleInstallerMilestones;
    }

    public function sshCredential(): SshCredential
    {
        return new RootCredential;
    }

    /**
     * Execute firewall rule configuration
     *
     * @param array $rules Array of firewall rules to apply
     * @param string $context Context for these rules (e.g., 'nginx', 'mysql', 'custom')
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

            // Persist the new rules to database (append to existing rules)
            function() use ($rules, $context) {
                // Get existing firewall configuration
                $existingPackage = $this->server->packages()
                    ->where('package_type', PackageType::Firewall)
                    ->where('package_name', PackageName::FirewallUfw)
                    ->first();

                if ($existingPackage) {
                    $config = $existingPackage->configuration ?? [];
                    $existingRules = $config['rules'] ?? [];

                    // Add context to new rules
                    $contextualRules = array_map(function($rule) use ($context) {
                        $rule['context'] = $context;
                        $rule['added_at'] = now()->toIso8601String();
                        return $rule;
                    }, $rules);

                    // Merge with existing rules
                    $config['rules'] = array_merge($existingRules, $contextualRules);

                    // Update the package configuration
                    $existingPackage->update(['configuration' => $config]);
                } else {
                    // If no existing firewall package, create one with just these rules
                    $this->persist(
                        PackageType::Firewall,
                        PackageName::FirewallUfw,
                        PackageVersion::Version1,
                        [
                            'rules' => array_map(function($rule) use ($context) {
                                $rule['context'] = $context;
                                $rule['added_at'] = now()->toIso8601String();
                                return $rule;
                            }, $rules)
                        ]
                    );
                }
            },

            $this->track(FirewallRuleInstallerMilestones::COMPLETE),
        ]);

        return $commands;
    }

    /**
     * Build UFW command from rule configuration
     */
    private function buildUfwCommand(array $rule): string
    {
        $action = $rule['action'] ?? 'allow';
        $port = $rule['port'];
        $protocol = $rule['protocol'] ?? 'tcp';

        // Start building the command
        $command = "ufw {$action}";

        // Add source restriction if specified
        if (!empty($rule['source'])) {
            $command .= " from {$rule['source']}";
        }

        // Add destination restriction if specified
        if (!empty($rule['destination'])) {
            $command .= " to {$rule['destination']}";
        }

        // Add port and protocol
        $command .= " {$port}/{$protocol}";

        // Add comment if provided (helps identify rules later)
        if (!empty($rule['comment'])) {
            $comment = escapeshellarg($rule['comment']);
            $command .= " comment {$comment}";
        }

        return $command;
    }
}