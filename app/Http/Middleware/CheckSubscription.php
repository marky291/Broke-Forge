<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Check if subscription is past due
        if ($user->subscription('default')?->pastDue()) {
            return redirect()->route('billing.index')
                ->with('error', 'Your subscription payment is past due. Please update your payment method.');
        }

        return $next($request);
    }
}
