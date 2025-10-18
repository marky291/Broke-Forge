<?php

namespace App\Http\Controllers;

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
            FirewallRuleInstallerJob::dispatch($server, $rule->id);

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

            // Update status to 'removing' for UI feedback
            $rule->update(['status' => 'removing']);

            // Dispatch job to remove the rule from the server
            FirewallRuleUninstallerJob::dispatch($server, $rule->id);

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
}
