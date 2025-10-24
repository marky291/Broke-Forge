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

        // Determine if we need to use extended syntax (when source or destination is specified)
        $hasSourceOrDestination = ! empty($rule['source']) || ! empty($rule['destination']);

        // Add source restriction if specified
        if (! empty($rule['source'])) {
            $command .= " from {$rule['source']}";
        }

        // Add destination restriction if specified
        // If no destination but source is specified, use "to any"
        if (! empty($rule['destination'])) {
            $command .= " to {$rule['destination']}";
        } elseif (! empty($rule['source'])) {
            $command .= ' to any';
        }

        // Add port and protocol
        // UFW has two syntax formats:
        // 1. Simple: "ufw allow 80/tcp" (no source/destination)
        // 2. Extended: "ufw allow from X to any port 80 proto tcp" (with source/destination)
        if ($hasSourceOrDestination) {
            $command .= " port {$port} proto {$protocol}";
        } else {
            $command .= " {$port}/{$protocol}";
        }

        // Add comment if provided (helps identify rules later)
        if (! empty($rule['comment'])) {
            $comment = escapeshellarg($rule['comment']);
            $command .= " comment {$comment}";
        }

        return $command;
    }
}
