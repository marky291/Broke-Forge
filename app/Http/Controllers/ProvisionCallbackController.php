<?php

namespace App\Http\Controllers;

use App\Jobs\ProvisionWebServiceJob;
use App\Models\Server;
use App\Provision\Enums\Connection;
use App\Provision\Enums\ProvisionStatus;
use App\Support\ServerCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Ssh\Ssh;

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
            $appUserSuccess = true;
            $server->connection = Connection::CONNECTED;

            // check the user of the ssh commands.
            $rootUser = trim(Ssh::create($server->ssh_root_user, $server->public_ip)
                ->disableStrictHostKeyChecking()
                ->execute('whoami')->getOutput());
            $appUser = trim(Ssh::create($server->ssh_app_user, $server->public_ip)
                ->disableStrictHostKeyChecking()
                ->execute('whoami')->getOutput());

            if ($rootUser != $server->ssh_root_user) {
                Log::error("Root SSH access failed, Found '{$rootUser}' expected '{$server->ssh_root_user}'", ['server' => $server]);
                $rootUserSuccess = false;
            }

            if ($appUser != $server->ssh_app_user) {
                Log::error("App SSH access failed, Found '{$appUser}' expected '{$server->ssh_app_user}'", ['server' => $server]);
                $appUserSuccess = false;
            }

            // connection failed if we cannot connect with SSH :(
            if (! $rootUserSuccess || ! $appUserSuccess) {
                $server->connection = Connection::FAILED;
                $server->provision_status = ProvisionStatus::Failed;
            } else {
                // Connection successful, update status and dispatch web service provisioning job
                $server->provision_status = ProvisionStatus::Installing;
                ProvisionWebServiceJob::dispatch($server);
                Log::info("Dispatched web service provisioning job for server #{$server->id}");
            }

            ServerCredentials::forgetRootPassword($server);
            $server->save();
        }

        return response()->json(['ok' => true]);
    }
}
