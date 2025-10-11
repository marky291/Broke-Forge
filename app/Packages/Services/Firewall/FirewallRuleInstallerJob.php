<?php

namespace App\Packages\Services\Firewall;

use App\Models\Server;
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
     * @param  array  $ruleData  Single firewall rule data (name, port, rule_type, from_ip_address)
     */
    public function __construct(
        public Server $server,
        public array $ruleData
    ) {}

    public function handle(): void
    {
        Log::info("Starting firewall rule configuration for server #{$this->server->id}", [
            'rule_name' => $this->ruleData['name'],
            'port' => $this->ruleData['port'],
        ]);

        try {
            // Create installer instance
            $installer = new FirewallRuleInstaller($this->server);

            // Convert single rule data to installer format
            $singleRule = [
                'port' => $this->ruleData['port'],
                'protocol' => 'tcp', // Default to TCP for MVP
                'action' => $this->ruleData['rule_type'] ?? 'allow',
                'source' => $this->ruleData['from_ip_address'] ?? null,
                'comment' => $this->ruleData['name'],
            ];

            // Execute rule configuration for this single rule
            // (installer accepts array of rules, so we pass array with one rule)
            $installer->execute([$singleRule], 'custom');

            Log::info("Firewall rule '{$this->ruleData['name']}' configured successfully for server #{$this->server->id}");
        } catch (\Exception $e) {
            Log::error("Firewall rule configuration failed for server #{$this->server->id}", [
                'rule_name' => $this->ruleData['name'],
                'port' => $this->ruleData['port'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
