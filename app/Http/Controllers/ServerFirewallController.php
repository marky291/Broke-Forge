<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PreparesSiteData;
use App\Http\Requests\Servers\FirewallRuleRequest;
use App\Models\Server;
use App\Models\ServerFirewallRule;
use App\Packages\Services\Firewall\FirewallRuleInstallerJob;
use App\Packages\Services\Firewall\FirewallRuleUninstallerJob;
use App\Services\FirewallConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ServerFirewallController extends Controller
{
    use PreparesSiteData;

    public function __construct(
        private readonly FirewallConfigurationService $firewallConfig
    ) {}

    public function index(Server $server): Response
    {
        $firewall = $server->firewall;

        // Check if firewall is actually installed on the server
        $isFirewallInstalled = $firewall !== null;

        if (! $isFirewallInstalled) {
            return Inertia::render('servers/firewall', [
                'server' => $server->only([
                    'id',
                    'vanity_name',
                    'public_ip',
                    'private_ip',
                    'ssh_port',
                    'connection', 'monitoring_status',
                    'provision_status',
                    'created_at',
                    'updated_at',
                ]),
                'rules' => [],
                'isFirewallInstalled' => false,
                'firewallStatus' => 'not_installed',
                'recentEvents' => [],
                'latestMetrics' => $this->getLatestMetrics($server),
            ]);
        }

        return Inertia::render('servers/firewall', [
            'server' => $server->only([
                'id',
                'vanity_name',
                'public_ip',
                'private_ip',
                'ssh_port',
                'connection', 'monitoring_status',
                'provision_status',
                'created_at',
                'updated_at',
            ]),
            'rules' => $firewall->rules()->latest()->get()->map(fn ($rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'port' => $rule->port,
                'from_ip_address' => $rule->from_ip_address,
                'rule_type' => $rule->rule_type,
                'status' => $rule->status,
                'created_at' => $rule->created_at->toISOString(),
            ]),
            'isFirewallInstalled' => true,
            'firewallStatus' => $firewall->is_enabled ? 'enabled' : 'disabled',
            'recentEvents' => $server->events()
                ->where('service_type', 'firewall')
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($event) => [
                    'id' => $event->id,
                    'milestone' => $event->milestone,
                    'status' => $event->status,
                    'current_step' => $event->current_step,
                    'total_steps' => $event->total_steps,
                    'progress' => $event->progressPercentage ?? null,
                    'details' => $event->details,
                    'created_at' => $event->created_at->toISOString(),
                ]),
            'latestMetrics' => $this->getLatestMetrics($server),
        ]);
    }

    public function store(FirewallRuleRequest $request, Server $server): RedirectResponse
    {
        try {
            // Ensure firewall exists for this server
            $firewall = $server->firewall()->firstOrCreate(
                ['server_id' => $server->id],
                ['is_enabled' => true]
            );

            // Create the firewall rule
            $rule = $firewall->rules()->create([
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

    public function status(Server $server): JsonResponse
    {
        $firewall = $server->firewall;

        if (! $firewall) {
            return response()->json([
                'rules' => [],
                'firewallStatus' => 'not_installed',
                'latestEvent' => null,
            ]);
        }

        $latestEvent = $server->events()
            ->where('service_type', 'firewall')
            ->latest()
            ->first();

        return response()->json([
            'rules' => $firewall->rules()->latest()->get()->map(fn ($rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'port' => $rule->port,
                'from_ip_address' => $rule->from_ip_address,
                'rule_type' => $rule->rule_type,
                'status' => $rule->status,
                'created_at' => $rule->created_at->toISOString(),
            ]),
            'firewallStatus' => $firewall->is_enabled ? 'enabled' : 'disabled',
            'latestEvent' => $latestEvent ? [
                'id' => $latestEvent->id,
                'milestone' => $latestEvent->milestone,
                'status' => $latestEvent->status,
                'current_step' => $latestEvent->current_step,
                'total_steps' => $latestEvent->total_steps,
                'progress' => $latestEvent->progressPercentage ?? null,
                'details' => $latestEvent->details,
                'created_at' => $latestEvent->created_at->toISOString(),
            ] : null,
        ]);
    }
}
