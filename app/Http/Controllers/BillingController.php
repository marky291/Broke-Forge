<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BillingController extends Controller
{
    /**
     * Display billing dashboard.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $subscription = $user->subscription('default');

        return Inertia::render('billing/index', [
            'subscription' => $subscription,
            'paymentMethods' => $user->paymentMethods,
            'invoices' => $user->invoicesIncludingPending(),
            'upcomingInvoice' => $subscription ? $user->upcomingInvoice() : null,
            'serverUsage' => [
                'current' => $user->activeServers()->count(),
                'limit' => $user->getServerLimit(),
                'remaining' => $user->getRemainingServerSlots(),
            ],
            'plans' => SubscriptionPlan::active()->get(),
        ]);
    }

    /**
     * Download invoice.
     */
    public function downloadInvoice(Request $request, string $invoiceId)
    {
        return $request->user()->downloadInvoice($invoiceId, [
            'vendor' => 'BrokeForge',
            'product' => 'Server Management Subscription',
        ]);
    }

    /**
     * Redirect to Stripe Customer Portal.
     */
    public function portal(Request $request)
    {
        $user = $request->user();

        // Create Stripe customer if not exists
        if (! $user->hasStripeId()) {
            $user->createAsStripeCustomer();
        }

        return $user->redirectToBillingPortal(route('billing.index'));
    }
}
