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
 * Handles queued firewall rule configuration on remote servers.
 * Each job instance handles ONE firewall rule only.
 * For multiple rules, dispatch multiple job instances.
 */
class FirewallRuleInstallerJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Server  $server  The server to configure
     * @param  int  $ruleId  The ServerFirewallRule ID to install
     */
    public function __construct(
        public Server $server,
        public int $ruleId
    ) {}

    public function handle(): void
    {
        // Load the firewall rule
        $rule = ServerFirewallRule::findOrFail($this->ruleId);

        Log::info("Starting firewall rule configuration for server #{$this->server->id}", [
            'rule_id' => $rule->id,
            'rule_name' => $rule->name,
            'port' => $rule->port,
        ]);

        try {
            // Update status to 'installing'
            $rule->update(['status' => 'installing']);

            // Create installer instance
            $installer = new FirewallRuleInstaller($this->server);

            // Convert rule data to installer format
            $singleRule = [
                'port' => $rule->port,
                'protocol' => 'tcp', // Default to TCP for MVP
                'action' => $rule->rule_type ?? 'allow',
                'source' => $rule->from_ip_address ?? null,
                'comment' => $rule->name,
            ];

            // Execute rule configuration for this single rule
            // (installer accepts array of rules, so we pass array with one rule)
            $installer->execute([$singleRule]);

            // Update status to 'active' on success
            $rule->update(['status' => 'active']);

            Log::info("Firewall rule '{$rule->name}' configured successfully for server #{$this->server->id}");
        } catch (\Exception $e) {
            // Update status to 'failed' on error
            $rule->update(['status' => 'failed']);

            Log::error("Firewall rule configuration failed for server #{$this->server->id}", [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'port' => $rule->port,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
