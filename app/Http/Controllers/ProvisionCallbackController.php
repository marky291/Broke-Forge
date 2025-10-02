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
     * Handle signed callbacks from remote provisioning scripts.
     */
    public function __invoke(Request $request, Server $server, string $status): JsonResponse
    {
        if (! in_array($status, ['started', 'completed'], true)) {
            abort(404);
        }

        // Update connection and provision status based on callback
        if ($status === 'started') {
            $server->connection = Connection::CONNECTING;
            $server->provision_status = ProvisionStatus::Connecting;
            $server->save();
        }

        if ($status === 'completed') {

            $rootUserSuccess = true;
            $brokeforgeUserSuccess = true;
            $server->connection = Connection::CONNECTED;

            // Verify SSH access for both credential types (root and brokeforge)
            foreach (CredentialType::cases() as $credentialType) {
                try {
                    $credential = $server->credential($credentialType);
                    $expectedUsername = $credential?->getUsername();

                    if (! $credential || ! $expectedUsername) {
                        Log::error("Missing {$credentialType->value} credential for server", ['server' => $server]);
                        ${$credentialType->value.'UserSuccess'} = false;

                        continue;
                    }

                    $actualUsername = trim($server->createSshConnection($credentialType)
                        ->execute('whoami')->getOutput());

                    if ($actualUsername !== $expectedUsername) {
                        Log::error("{$credentialType->value} SSH access failed, Found '{$actualUsername}' expected '{$expectedUsername}'", ['server' => $server]);
                        ${$credentialType->value.'UserSuccess'} = false;
                    }
                } catch (\Exception $e) {
                    Log::error("{$credentialType->value} SSH connection failed: {$e->getMessage()}", ['server' => $server]);
                    ${$credentialType->value.'UserSuccess'} = false;
                }
            }

            // Connection failed if we cannot connect with SSH for either user
            if (! $rootUserSuccess || ! $brokeforgeUserSuccess) {
                $server->connection = Connection::FAILED;
                $server->provision_status = ProvisionStatus::Failed;
            } else {
                // Connection successful, update status and dispatch web service provisioning job
                $server->provision_status = ProvisionStatus::Installing;
                NginxInstallerJob::dispatch($server, PhpVersion::PHP83, isProvisioningServer: true);
                Log::info("Dispatched web service provisioning job for server #{$server->id}");
            }

            $server->save();
        }

        return response()->json(['ok' => true]);
    }
}
