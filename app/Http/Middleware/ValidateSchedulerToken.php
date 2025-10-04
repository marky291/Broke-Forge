<?php

namespace App\Http\Middleware;

use App\Models\Server;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateSchedulerToken
{
    /**
     * Handle an incoming request.
     *
     * Validates that the scheduler token in the request header matches
     * the server's stored scheduler token for API authentication.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get the scheduler token from request header
        $token = $request->header('X-Scheduler-Token');

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Scheduler token required',
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
        if (! $server->scheduler_token || $server->scheduler_token !== $token) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid scheduler token',
            ], 401);
        }

        return $next($request);
    }
}
