<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('welcome', [
            'plans' => SubscriptionPlan::active()->get(),
            'freePlan' => config('subscription.plans.free'),
        ]);
    }
}
