<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Packages\Enums\ConnectionStatus;
use App\Packages\Enums\PhpVersion;
use App\Packages\Enums\ProvisionStatus;
use App\Packages\Services\Nginx\NginxInstallerJob;
use App\Packages\Services\SourceProvider\ServerSshKeyManager;
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
            $server->firewall?->rules()->delete();
            $server->firewall()->delete(); // delete all firewall for this server
            $server->provision = collect(); // clear out the steps.
            $server->provision->put(1, ProvisionStatus::Completed->value); // complete the connection step.
            $server->connection_status = ConnectionStatus::CONNECTED;
            $server->provision_status = ProvisionStatus::Installing;
            $server->save();
        }

        if ($step == 3 && $status == ProvisionStatus::Completed->value) {

            $server->provision->put(4, ProvisionStatus::Installing->value);
            $server->save();

            // Initialize success flags
            $rootUserSuccess = true;
            $brokeforgeUserSuccess = true;

            foreach (['root', 'brokeforge'] as $user) {
                $credential = $server->credentials()
                    ->where('user', $user)
                    ->first();

                $expectedUsername = $credential?->getUsername();

                if (! $credential || ! $expectedUsername) {
                    Log::error("Missing {$user} credential for server", ['server' => $server]);

                    continue;
                }

                $result = $server->ssh($user)->execute('whoami');
                $actualUsername = trim($result->getOutput());
                $errorOutput = trim($result->getErrorOutput());
                $exitCode = $result->getExitCode();

                if ($actualUsername !== $expectedUsername) {
                    Log::error("{$user} SSH access failed, Found '{$actualUsername}' expected '{$expectedUsername}'", [
                        'server' => $server,
                        'exit_code' => $exitCode,
                        'error_output' => $errorOutput,
                        'stdout' => $actualUsername,
                    ]);
                    ${$user.'UserSuccess'} = false;
                }
            }

            // Connection failed if we cannot connect with SSH for either user
            if (! $rootUserSuccess || ! $brokeforgeUserSuccess) {
                $server->provision_status = ProvisionStatus::Failed;
                $server->save();
            } else {
                // Connection successful - detect OS information before provisioning
                $server->detectOsInfo();

                Log::info("Detected OS for server #{$server->id}: {$server->os_name} {$server->os_version} ({$server->os_codename})");

                // Add server SSH key to GitHub if user opted in and has GitHub connected
                $user = $server->user;
                $githubProvider = $user->githubProvider();

                if ($server->add_ssh_key_to_github && $githubProvider) {
                    try {
                        $keyManager = new ServerSshKeyManager($server, $githubProvider);
                        $success = $keyManager->addServerKeyToGitHub();

                        if ($success) {
                            Log::info("Successfully added server SSH key to GitHub for server #{$server->id}");
                        } else {
                            Log::warning("Failed to add server SSH key to GitHub for server #{$server->id} - provisioning continues");
                        }
                    } catch (\Exception $e) {
                        Log::warning("Error adding server SSH key to GitHub for server #{$server->id}: {$e->getMessage()} - provisioning continues");
                    }
                } else {
                    if (! $server->add_ssh_key_to_github) {
                        Log::debug("User opted out of adding server SSH key to GitHub for server #{$server->id}");
                    } elseif (! $githubProvider) {
                        Log::debug("User does not have GitHub connected, skipping SSH key addition for server #{$server->id}");
                    }
                }

                $server->provision->put(4, ProvisionStatus::Completed->value);
                $server->provision->put(5, ProvisionStatus::Installing->value);
                $server->provision_status = ProvisionStatus::Installing;
                $server->save();

                // Update status and dispatch web service provisioning job
                NginxInstallerJob::dispatch($server, PhpVersion::PHP83, isProvisioningServer: true);

                Log::info("Dispatched web service provisioning job for server #{$server->id}");
            }
        }

        return response()->json(['ok' => true]);
    }
}
