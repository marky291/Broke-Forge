<?php

namespace App\Packages\Services\Firewall\Concerns;

use App\Models\ServerFirewall;

trait ManagesFirewallRules
{
    /**
     * Create firewall rules in the database
     *
     * @param  array  $rules  Array of firewall rules
     * @param  string  $context  Context for these rules (e.g., 'nginx', 'mysql', 'ssh', 'custom')
     */
    protected function createFirewallRules(array $rules, string $context = 'custom'): \Closure
    {
        return function () use ($rules, $context) {
            // Get or create firewall for this server
            $firewall = ServerFirewall::firstOrCreate(
                ['server_id' => $this->server->id],
                ['is_enabled' => true]
            );

            // Add each rule to the firewall (updateOrCreate prevents duplicates)
            foreach ($rules as $rule) {
                // Generate a descriptive name if not provided
                $name = $rule['comment'] ?? $rule['name'] ?? "Port {$rule['port']} ({$context})";

                $firewall->rules()->updateOrCreate(
                    [
                        'name' => $name,
                        'port' => $rule['port'],
                    ],
                    [
                        'from_ip_address' => $rule['source'] ?? $rule['from_ip_address'] ?? null,
                        'rule_type' => $rule['action'] ?? $rule['rule_type'] ?? 'allow',
                        'status' => 'active',
                    ]
                );
            }
        };
    }

    /**
     * Build UFW command from rule configuration
     *
     * @param  array  $rule  Rule configuration
     * @return string UFW command
     */
    protected function buildUfwCommand(array $rule): string
    {
        $action = $rule['action'] ?? 'allow';
        $port = $rule['port'];
        $protocol = $rule['protocol'] ?? 'tcp';

        // Start building the command
        $command = "ufw {$action}";

        // Add source restriction if specified
        if (! empty($rule['source'])) {
            $command .= " from {$rule['source']}";
        }

        // Add destination restriction if specified
        if (! empty($rule['destination'])) {
            $command .= " to {$rule['destination']}";
        }

        // Add port and protocol
        $command .= " {$port}/{$protocol}";

        // Add comment if provided (helps identify rules later)
        if (! empty($rule['comment'])) {
            $comment = escapeshellarg($rule['comment']);
            $command .= " comment {$comment}";
        }

        return $command;
    }
}
