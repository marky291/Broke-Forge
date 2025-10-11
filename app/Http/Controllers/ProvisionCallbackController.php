<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Packages\Enums\Connection;
use App\Packages\Enums\CredentialType;
use App\Packages\Enums\PhpVersion;
use App\Packages\Enums\ProvisionStatus;
use App\Packages\Services\Nginx\NginxInstallerJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProvisionCallbackController extends Controller
{
    /**
     * Handle provision step callbacks from remote provisioning scripts.
     */
    public function step(Request $request, Server $server): JsonResponse
    {
        // Get from query params (appended to signed URL) or POST body
        $step = (int) ($request->query('step') ?? $request->input('step'));
        $status = $request->query('status') ?? $request->input('status');

        // Validate step and status
        if (! in_array($step, [1, 2, 3], true)) {
            abort(400, 'Invalid step');
        }

        if (! in_array($status, ['pending', 'installing', 'completed', 'failed'], true)) {
            abort(400, 'Invalid status');
        }

        // Save the step to db
        $server->provision->put($step, $status);
        $server->save();

        Log::info("Provision step {$step} updated to {$status} for server #{$server->id}");

        // If any step fails, mark the entire provisioning as failed
        if ($status === 'failed') {
            $server->provision_status = ProvisionStatus::Failed;
            $server->save();

            Log::error("Provision step {$step} failed for server #{$server->id}");

            return response()->json(['ok' => true]);
        }

        if ($step == 1 && $status === ProvisionStatus::Completed->value) {
            // Let's treat this as a brand-new installation for server
            // great when server testing, production should never be able to run
            // this after first installation anyway...
            $server->events()->delete(); // delete all events if new provision.
            $server->databases()->delete(); // delete all databases for this server
            $server->phps()->delete(); // delete all phps for this server
            $server->reverseProxy()->delete(); // delete all proxy (nginx) for this server
            $server->firewall()->delete(); // delete all firewall for this server
            $server->provision = $server->attributesToArray(); // clear out the steps.
            $server->provision->put(1, ProvisionStatus::Completed->value); // complete the connection step.
            $server->connection = Connection::CONNECTED;
            $server->provision_status = ProvisionStatus::Installing;
            $server->save();
        }

        if ($step == 3 && $status == ProvisionStatus::Completed->value) {

            $server->provision->put(4, ProvisionStatus::Installing->value);
            $server->save();

            // Initialize success flags
            $rootUserSuccess = true;
            $brokeforgeUserSuccess = true;

            foreach (CredentialType::cases() as $credentialType) {
                $credential = $server->credential($credentialType);
                $expectedUsername = $credential?->getUsername();

                if (! $credential || ! $expectedUsername) {
                    Log::error("Missing {$credentialType->value} credential for server", ['server' => $server]);

                    continue;
                }

                $result = $server->createSshConnection($credentialType)->execute('whoami');
                $actualUsername = trim($result->getOutput());
                $errorOutput = trim($result->getErrorOutput());
                $exitCode = $result->getExitCode();

                if ($actualUsername !== $expectedUsername) {
                    Log::error("{$credentialType->value} SSH access failed, Found '{$actualUsername}' expected '{$expectedUsername}'", [
                        'server' => $server,
                        'exit_code' => $exitCode,
                        'error_output' => $errorOutput,
                        'stdout' => $actualUsername,
                    ]);
                    ${$credentialType->value.'UserSuccess'} = false;
                }
            }

            // Connection failed if we cannot connect with SSH for either user
            if (! $rootUserSuccess || ! $brokeforgeUserSuccess) {
                $server->provision_status = ProvisionStatus::Failed;
            } else {
                // Connection successful - detect OS information before provisioning
                $server->detectOsInfo();

                Log::info("Detected OS for server #{$server->id}: {$server->os_name} {$server->os_version} ({$server->os_codename})");

                $server->provision->put(4, ProvisionStatus::Completed->value);
                $server->provision->put(5, ProvisionStatus::Installing->value);
                $server->save();

                // Update status and dispatch web service provisioning job
                NginxInstallerJob::dispatch($server, PhpVersion::PHP83, isProvisioningServer: true);

                Log::info("Dispatched web service provisioning job for server #{$server->id}");
            }
        }

        return response()->json(['ok' => true]);
    }
}
