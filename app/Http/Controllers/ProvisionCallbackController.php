<?php

namespace App\Http\Controllers;

use App\Models\Server;
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

        if ($step == 3 && $status == ProvisionStatus::Completed->value) {
            NginxInstallerJob::dispatch($server, PhpVersion::PHP83, isProvisioningServer: true);
        }

        return response()->json(['ok' => true]);
    }
}
