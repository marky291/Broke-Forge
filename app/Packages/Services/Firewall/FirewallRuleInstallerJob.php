<?php

namespace App\Packages\Services\Firewall;

use App\Models\Server;
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
        public array $rules,
        public string $context = 'custom'
    ) {}

    public function handle(): void
    {
        Log::info("Starting firewall rule configuration for server #{$this->server->id}", [
            'context' => $this->context,
            'rule_count' => count($this->rules)
        ]);

        try {
            // Create installer instance
            $installer = new FirewallRuleInstaller($this->server);

            // Execute rule configuration - the installer handles database tracking
            $installer->execute($this->rules, $this->context);

            Log::info("Firewall rule configuration completed for server #{$this->server->id}", [
                'context' => $this->context,
                'rules_applied' => count($this->rules)
            ]);
        } catch (\Exception $e) {
            Log::error("Firewall rule configuration failed for server #{$this->server->id}", [
                'context' => $this->context,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}