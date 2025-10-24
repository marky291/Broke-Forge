<?php

namespace App\Http\Controllers;

use App\Enums\FirewallRuleStatus;
use App\Http\Requests\Servers\FirewallRuleRequest;
use App\Http\Resources\ServerResource;
use App\Models\Server;
use App\Models\ServerFirewallRule;
use App\Packages\Services\Firewall\FirewallRuleInstallerJob;
use App\Packages\Services\Firewall\FirewallRuleUninstallerJob;
use App\Services\FirewallConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ServerFirewallController extends Controller
{
    public function __construct(
        private readonly FirewallConfigurationService $firewallConfig
    ) {}

    public function index(Server $server): Response
    {
        $this->authorize('view', $server);

        // Load necessary relationships for the resource
        $server->load(['firewall.rules', 'metrics']);

        return Inertia::render('servers/firewall', [
            'server' => new ServerResource($server),
        ]);
    }

    public function store(FirewallRuleRequest $request, Server $server): RedirectResponse
    {
        $this->authorize('update', $server);

        try {
            // Ensure server has a firewall
            if (! $server->firewall) {
                return back()->with('error', 'Firewall is not installed on this server.');
            }

            // Create the firewall rule with 'pending' status
            $rule = ServerFirewallRule::create([
                'server_firewall_id' => $server->firewall->id,
                'name' => $request->validated('name'),
                'port' => $request->validated('port'),
                'from_ip_address' => $request->validated('from_ip_address'),
                'rule_type' => $request->validated('rule_type', 'allow'),
                'status' => 'pending',
            ]);

            // Dispatch job to configure the rule on the server
            FirewallRuleInstallerJob::dispatch($server, $rule);

            return back()->with('success', 'Firewall rule is being applied.');

        } catch (\Exception $e) {
            Log::error('Failed to create firewall rule', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to create firewall rules.');
        }
    }

    public function destroy(Server $server, $ruleId): RedirectResponse
    {
        $this->authorize('update', $server);

        try {
            $rule = ServerFirewallRule::findOrFail($ruleId);

            if ($rule->firewall->server_id !== $server->id) {
                return back()->with('error', 'Invalid firewall rule.');
            }

            // Update status to 'pending' for UI feedback
            $rule->update(['status' => FirewallRuleStatus::Pending]);

            // Dispatch job to remove the rule from the server
            FirewallRuleUninstallerJob::dispatch($server, $rule);

            return back()->with('success', 'Firewall rule is being removed.');

        } catch (\Exception $e) {
            Log::error('Failed to remove firewall rule', [
                'server_id' => $server->id,
                'rule_id' => $ruleId,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to remove firewall rule.');
        }
    }

    /**
     * Retry a failed firewall rule installation
     */
    public function retry(Server $server, ServerFirewallRule $firewallRule): RedirectResponse
    {
        $this->authorize('update', $server);

        // Verify the firewall rule belongs to this server
        if ($firewallRule->firewall->server_id !== $server->id) {
            return back()->with('error', 'Invalid firewall rule.');
        }

        // Only allow retry for failed firewall rules
        if ($firewallRule->status !== \App\Enums\FirewallRuleStatus::Failed) {
            return back()->with('error', 'Only failed firewall rules can be retried');
        }

        // Audit log
        Log::info('Firewall rule installation retry initiated', [
            'user_id' => auth()->id(),
            'server_id' => $server->id,
            'rule_id' => $firewallRule->id,
            'rule_name' => $firewallRule->name,
            'port' => $firewallRule->port,
            'ip_address' => request()->ip(),
        ]);

        // Reset status to 'pending' and clear error log
        // Model events will broadcast automatically via Reverb
        $firewallRule->update([
            'status' => \App\Enums\FirewallRuleStatus::Pending,
            'error_log' => null,
        ]);

        // Re-dispatch installer job
        FirewallRuleInstallerJob::dispatch($server, $firewallRule);

        // No redirect needed - frontend will update via Reverb
        return back();
    }
}
