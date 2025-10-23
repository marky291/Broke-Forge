<?php

namespace App\Packages\Services\Firewall;

use App\Enums\FirewallRuleStatus;
use App\Models\Server;
use App\Models\ServerFirewallRule;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Firewall Rule Installation Job
 *
 * Handles queued firewall rule configuration on remote servers with real-time status updates
 * Each job instance handles ONE firewall rule only.
 * For multiple rules, dispatch multiple job instances.
 */
class FirewallRuleInstallerJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

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
        // Set no time limit for long-running installation process
        set_time_limit(0);

        // Load the firewall rule from database
        $rule = ServerFirewallRule::findOrFail($this->ruleId);

        Log::info('Starting firewall rule configuration', [
            'rule_id' => $rule->id,
            'server_id' => $this->server->id,
            'rule_name' => $rule->name,
            'port' => $rule->port,
        ]);

        try {
            // ✅ UPDATE: pending → installing
            $rule->update(['status' => FirewallRuleStatus::Installing]);
            // Model event broadcasts automatically via Reverb

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

            // ✅ UPDATE: installing → active
            $rule->update(['status' => FirewallRuleStatus::Active]);
            // Model event broadcasts automatically via Reverb

            Log::info("Firewall rule '{$rule->name}' configured successfully", [
                'rule_id' => $rule->id,
                'server_id' => $this->server->id,
            ]);

        } catch (Exception $e) {
            // ✅ UPDATE: any → failed
            $rule->update([
                'status' => FirewallRuleStatus::Failed,
                'error_log' => $e->getMessage(),
            ]);
            // Model event broadcasts automatically via Reverb

            Log::error('Firewall rule configuration failed', [
                'rule_id' => $rule->id,
                'server_id' => $this->server->id,
                'rule_name' => $rule->name,
                'port' => $rule->port,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;  // Re-throw for Laravel's retry mechanism
        }
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("package:action:{$this->server->id}"))->shared()
                ->releaseAfter(15)
                ->expireAfter(900),
        ];
    }

    public function failed(\Throwable $exception): void
    {
        $rule = ServerFirewallRule::find($this->ruleId);

        if ($rule) {
            $rule->update([
                'status' => FirewallRuleStatus::Failed,
                'error_log' => $exception->getMessage(),
            ]);
        }

        Log::error('FirewallRuleInstallerJob job failed', [
            'rule_id' => $this->ruleId,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
