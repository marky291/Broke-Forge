<?php

namespace App\Packages\Services\Firewall;

use App\Models\Server;
use App\Models\ServerFirewallRule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Firewall Rule Installation Job
 *
 * Handles queued firewall rule configuration on remote servers
 */
class FirewallRuleInstallerJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Server $server,
        public int $ruleId
    ) {}

    public function handle(): void
    {
        // Get the rule that needs to be installed
        $rule = ServerFirewallRule::find($this->ruleId);

        if (!$rule) {
            Log::warning('No firewall rule found for ID', ['rule_id' => $this->ruleId]);

            return;
        }

        Log::info("Starting firewall rule configuration for server #{$this->server->id}", [
            'rule_id' => $this->ruleId,
        ]);

        // Update rule status to 'installing'
        $rule->update(['status' => 'installing']);

        try {
            // Create installer instance
            $installer = new FirewallRuleInstaller($this->server);

            // Convert rule to array format expected by installer
            $ruleArray = [
                'port' => $rule->port,
                'protocol' => 'tcp', // Default to TCP for MVP
                'action' => $rule->rule_type, // 'allow' or 'deny'
                'source' => $rule->from_ip_address,
                'comment' => $rule->name,
            ];

            // Execute rule configuration
            $installer->execute([$ruleArray], 'custom');

            // Update rule status to 'active'
            $rule->update(['status' => 'active']);

            Log::info("Firewall rule configuration completed for server #{$this->server->id}", [
                'rule_id' => $this->ruleId,
            ]);
        } catch (\Exception $e) {
            Log::error("Firewall rule configuration failed for server #{$this->server->id}", [
                'rule_id' => $this->ruleId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update rule status to 'failed'
            $rule->update(['status' => 'failed']);

            throw $e;
        }
    }
}
