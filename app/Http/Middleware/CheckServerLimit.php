<?php

namespace App\Http\Middleware;

use App\Services\Subscription\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckServerLimit
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

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

        $check = $this->subscriptionService->canCreateServer($user);

        if (! $check['can_create']) {
            return back()->with('error',
                "You've reached your server limit ({$check['limit']}). Please upgrade your subscription to add more servers."
            );
        }

        return $next($request);
    }
}
