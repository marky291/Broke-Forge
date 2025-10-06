<?php

namespace App\Http\Controllers;

use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

    /**
     * Create new subscription.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'price_id' => 'required|string',
            'payment_method_id' => 'required|string',
        ]);

        try {
            $this->subscriptionService->createSubscription(
                $request->user(),
                $validated['price_id'],
                $validated['payment_method_id']
            );

            return redirect()->route('billing.index')
                ->with('success', 'Subscription created successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create subscription: '.$e->getMessage());
        }
    }

    /**
     * Update subscription plan.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'price_id' => 'required|string',
        ]);

        try {
            $this->subscriptionService->changePlan(
                $request->user(),
                $validated['price_id']
            );

            return redirect()->route('billing.index', ['plan_changed' => 'true'])
                ->with('success', 'Subscription updated successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update subscription: '.$e->getMessage());
        }
    }

    /**
     * Cancel subscription.
     */
    public function destroy(Request $request)
    {
        $immediate = $request->boolean('immediate', false);

        try {
            $this->subscriptionService->cancelSubscription(
                $request->user(),
                $immediate
            );

            $message = $immediate
                ? 'Subscription cancelled immediately.'
                : 'Subscription will be cancelled at the end of the current billing period.';

            return redirect()->route('billing.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to cancel subscription: '.$e->getMessage());
        }
    }

    /**
     * Resume cancelled subscription.
     */
    public function resume(Request $request)
    {
        try {
            $this->subscriptionService->resumeSubscription($request->user());

            return redirect()->route('billing.index')
                ->with('success', 'Subscription resumed successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to resume subscription: '.$e->getMessage());
        }
    }
}
