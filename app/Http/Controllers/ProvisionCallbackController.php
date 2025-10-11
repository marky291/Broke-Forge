<?php

namespace App\Http\Controllers;

use App\Models\Server;
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

        Log::info('Provision step callback received', [
            'server_id' => $server->id,
            'step' => $step,
            'status' => $status,
            'all_input' => $request->all(),
            'query' => $request->query(),
        ]);

        // Validate step and status
        if (! in_array($step, [1, 2, 3], true)) {
            Log::error("Invalid step: {$step}");
            abort(400, 'Invalid step');
        }

        if (! in_array($status, ['pending', 'installing', 'completed', 'failed'], true)) {
            Log::error("Invalid status: {$status}");
            abort(400, 'Invalid status');
        }

        // Get current provision array or initialize empty
        $provision = $server->provision ?? [];

        // Update or add the step status
        $stepFound = false;
        foreach ($provision as $key => $item) {
            if ($item['step'] === $step) {
                $provision[$key]['status'] = $status;
                $stepFound = true;
                break;
            }
        }

        if (! $stepFound) {
            $provision[] = ['step' => $step, 'status' => $status];
        }

        // Sort by step number
        usort($provision, fn ($a, $b) => $a['step'] <=> $b['step']);

        // Save to database
        $server->provision = $provision;
        $server->save();

        Log::info("Provision step {$step} updated to {$status} for server #{$server->id}");

        return response()->json(['ok' => true]);
    }
}
