<?php

namespace App\Packages\Services\Firewall;

use App\Enums\FirewallRuleStatus;
use App\Models\Server;
use App\Models\ServerFirewallRule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Firewall Rule Uninstallation Job
 *
 * Handles queued firewall rule removal on remote servers
 */
class FirewallRuleUninstallerJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     */
    public $timeout = 600;

    public function __construct(
        public Server $server,
        public int $ruleId
    ) {}

    public function handle(): void
    {
        set_time_limit(0);

        // Get the rule that needs to be removed
        $rule = ServerFirewallRule::find($this->ruleId);

        if (! $rule) {
            Log::warning('No firewall rule found for removal', ['rule_id' => $this->ruleId]);

            return;
        }

        Log::info("Starting firewall rule removal for server #{$this->server->id}", [
            'rule_id' => $this->ruleId,
        ]);

        // Store original status for rollback
        $originalStatus = $rule->status;

        try {
            // ✅ UPDATE: active → removing
            // Model event broadcasts automatically via Reverb
            $rule->update(['status' => FirewallRuleStatus::Removing]);

            // Create uninstaller instance
            $uninstaller = new FirewallRuleUninstaller($this->server);

            // Prepare rule configuration for the uninstaller
            $ruleConfig = [
                'port' => $rule->port,
                'from_ip_address' => $rule->from_ip_address,
                'rule_type' => $rule->rule_type,
                'name' => $rule->name,
            ];

            // Execute rule removal
            $uninstaller->execute($ruleConfig);

            // ✅ DELETE: Remove from database after successful removal
            // Model deleted event broadcasts automatically via Reverb
            $rule->delete();

            Log::info("Firewall rule removal completed for server #{$this->server->id}", [
                'rule_id' => $this->ruleId,
            ]);
        } catch (\Exception $e) {
            Log::error("Firewall rule removal failed for server #{$this->server->id}", [
                'rule_id' => $this->ruleId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // ✅ ROLLBACK: Restore original status on failure
            // Model event broadcasts automatically via Reverb
            $rule->update(['status' => $originalStatus]);

            // Mark job as failed without retrying
            $this->fail($e);
        }
    }
}
