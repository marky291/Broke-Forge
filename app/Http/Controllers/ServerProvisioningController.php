<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Resources\ServerProvisioningResource;
use App\Models\Server;
use App\Packages\Enums\PhpVersion;
use App\Packages\ProvisionAccess;
use App\Packages\Services\Nginx\NginxInstallerJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ServerProvisioningController extends Controller
{
    public function show(Server $server): Response|\Illuminate\Http\RedirectResponse
    {
        // Authorize user can view this server
        $this->authorize('view', $server);

        // Redirect to server page if fully provisioned
        if ($server->provision_status === TaskStatus::Success) {
            return redirect()->route('servers.show', $server);
        }

        $server->load(['databases', 'defaultPhp']);

        return Inertia::render('servers/provisioning', [
            'server' => new ServerProvisioningResource($server),
            'provision' => $this->getProvisionData($server),
        ]);
    }

    public function provision(Server $server): HttpResponse
    {
        $script = (new ProvisionAccess)->makeScriptFor($server, $server->ssh_root_password);

        return response($script, 200, [
            'Content-Type' => 'text/x-shellscript; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function services(Server $server): Response
    {
        // Authorize user can view this server
        $this->authorize('view', $server);

        return Inertia::render('servers/provision/services', [
            'server' => $server->only(['id', 'vanity_name', 'provider', 'public_ip', 'ssh_port', 'private_ip', 'connection', 'created_at', 'updated_at']),
        ]);
    }

    public function storeServices(Server $server): RedirectResponse
    {
        // Authorize user can update this server
        $this->authorize('update', $server);

        // TODO: Implement service provisioning logic
        return redirect()
            ->route('servers.provision.services', $server)
            ->with('success', 'Services configured successfully');
    }

    /**
     * Retry provisioning for a failed server.
     *
     * If SSH connection is already established (step 3 complete), identifies the failed step
     * and resumes from there. Otherwise, reset to pending for manual re-run.
     */
    public function retry(Server $server): RedirectResponse
    {
        $this->authorize('update', $server);

        if ($server->provision_status !== TaskStatus::Failed) {
            return redirect()
                ->route('servers.provisioning', $server)
                ->with('error', 'Provisioning is not in a failed state.');
        }

        // Check if SSH connection was already established (step 3 = success)
        $sshEstablished = $server->provision_state->get(3) === TaskStatus::Success->value;

        if ($sshEstablished && $server->provision_config?->has('php_version')) {
            // Find the failed step (5-8) from provision_state
            $failedStep = $server->provision_state
                ->search(fn ($status) => $status === TaskStatus::Failed->value);

            // Default to step 5 if no specific failed step found
            if ($failedStep === false || $failedStep < 5) {
                $failedStep = 5;
            }

            // Clean up only resources from the failed step onward
            $this->cleanupFromStep($server, $failedStep);

            // Build new provision state: keep successful steps, mark failed step as installing
            $newState = collect([
                1 => TaskStatus::Success->value,
                2 => TaskStatus::Success->value,
                3 => TaskStatus::Success->value,
                4 => TaskStatus::Success->value,
            ]);

            // Preserve steps completed before the failed step
            for ($step = 5; $step < $failedStep; $step++) {
                $newState->put($step, TaskStatus::Success->value);
            }

            // Mark the failed step as installing
            $newState->put($failedStep, TaskStatus::Installing->value);

            $server->provision_state = $newState;
            $server->provision_status = TaskStatus::Installing;
            $server->save();

            // Dispatch the provisioning job, resuming from the failed step
            $phpVersion = PhpVersion::from($server->provision_config->get('php_version'));
            NginxInstallerJob::dispatch($server, $phpVersion, isProvisioningServer: true, resumeFromStep: $failedStep);

            return redirect()
                ->route('servers.provisioning', $server)
                ->with('success', 'Provisioning resumed from failed step.');
        }

        // SSH not established - reset everything for manual re-run
        $server->ssh_root_password = Server::generatePassword();
        $server->connection_status = TaskStatus::Pending;
        $server->provision_status = TaskStatus::Pending;
        $server->provision_state = collect();
        $server->save();

        return redirect()
            ->route('servers.provisioning', $server)
            ->with('success', 'Provisioning reset. Run the provisioning command again.');
    }

    /**
     * Clean up server resources from a specific step onward.
     *
     * This preserves resources from steps that completed successfully before the failed step.
     */
    protected function cleanupFromStep(Server $server, int $step): void
    {
        // Step 5: Firewall
        if ($step <= 5) {
            $server->firewall?->rules()->delete();
            $server->firewall()->delete();
        }

        // Step 6: PHP
        if ($step <= 6) {
            $server->phps()->delete();
        }

        // Step 7: Nginx / Reverse Proxy and default site
        if ($step <= 7) {
            $server->reverseProxy()->delete();
            $server->sites()->where('is_default', true)->delete();
        }

        // Step 8: Scheduler, Supervisor, Node
        if ($step <= 8) {
            $server->scheduledTasks()->delete();
            $server->supervisorTasks()->delete();
            $server->nodes()->delete();
        }
    }

    protected function getProvisionData(Server $server): ?array
    {
        return [
            'command' => $this->buildProvisionCommand($server),
            'root_password' => $server->ssh_root_password,
        ];
    }

    protected function buildProvisionCommand(Server $server): string
    {
        $provisionUrl = route('servers.provision', ['server' => $server->id]);
        $filename = Str::slug(config('app.name')).'.sh';

        return sprintf('wget -O %1$s "%2$s"; bash %1$s', $filename, $provisionUrl);
    }
}
