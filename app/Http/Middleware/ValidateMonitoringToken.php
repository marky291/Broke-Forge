<?php

namespace App\Http\Middleware;

use App\Models\Server;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateMonitoringToken
{
    /**
     * Handle an incoming request.
     *
     * Validates that the monitoring token in the request header matches
     * the server's stored monitoring token for API authentication.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get the monitoring token from request header
        $token = $request->header('X-Monitoring-Token');

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Monitoring token required',
            ], 401);
        }

        // Get the server from route binding
        $server = $request->route('server');

        if (! $server instanceof Server) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid server',
            ], 404);
        }

        // Validate token matches
        if (! $server->monitoring_token || $server->monitoring_token !== $token) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid monitoring token',
            ], 401);
        }

        return $next($request);
    }
}
