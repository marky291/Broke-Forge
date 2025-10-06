<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BillingController extends Controller
{
    /**
     * Format amount from cents to currency string.
     */
    private function formatAmount(int $amountInCents, string $currency = 'eur'): string
    {
        $amount = $amountInCents / 100;
        $symbol = $currency === 'usd' ? '$' : '€';

        // Handle negative amounts (credits)
        if ($amount < 0) {
            return '-' . $symbol . number_format(abs($amount), 2);
        }

        return $symbol . number_format($amount, 2);
    }

    /**
     * Display billing dashboard.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Check if returning from successful checkout or plan change
        $justSubscribed = false;
        $planChanged = $request->boolean('plan_changed', false);

        if ($request->has('session_id')) {
            // Manually sync subscription from Stripe (webhooks may fail in dev)
            try {
                $stripe = new \Stripe\StripeClient(config('cashier.secret'));
                $session = $stripe->checkout->sessions->retrieve($request->get('session_id'));

                if ($session->status === 'complete' && $session->subscription) {
                    // Sync subscription from Stripe
                    $stripeSubscription = $stripe->subscriptions->retrieve($session->subscription);

                    // Create or update subscription in database
                    $user->subscriptions()->updateOrCreate(
                        ['stripe_id' => $stripeSubscription->id],
                        [
                            'type' => 'default',
                            'stripe_status' => $stripeSubscription->status,
                            'stripe_price' => $stripeSubscription->items->data[0]->price->id,
                            'quantity' => $stripeSubscription->items->data[0]->quantity,
                            'trial_ends_at' => $stripeSubscription->trial_end ? date('Y-m-d H:i:s', $stripeSubscription->trial_end) : null,
                            'ends_at' => null,
                        ]
                    );

                    $justSubscribed = true;
                }
            } catch (\Exception $e) {
                // Log error but continue
                logger()->error('Failed to sync subscription from checkout session', [
                    'error' => $e->getMessage(),
                    'session_id' => $request->get('session_id'),
                ]);
            }
        }

        $subscription = $user->subscription('default');

        // Get upcoming invoice only if user has active subscription
        $upcomingInvoice = null;
        if ($subscription && $subscription->stripe_status === 'active') {
            try {
                $invoice = $user->upcomingInvoice();
                if ($invoice) {
                    $currency = $invoice->currency ?? 'eur';
                    $total = $invoice->rawTotal();

                    $upcomingInvoice = [
                        'total' => $this->formatAmount($total, $currency),
                        'period_end' => $invoice->date()->toIso8601String(),
                    ];
                }
            } catch (\Throwable $e) {
                // No upcoming invoice (e.g., cancelled subscription or no payment method)
                logger()->debug('Could not fetch upcoming invoice', ['error' => $e->getMessage()]);
            }
        }

        // Format invoices for frontend
        $invoices = collect($user->invoicesIncludingPending())->map(function ($invoice) {
            // Get currency and amount
            $currency = $invoice->currency ?? 'eur';
            $total = $invoice->rawTotal();

            return [
                'id' => $invoice->id,
                'date' => $invoice->date()->toIso8601String(),
                'total' => $this->formatAmount($total, $currency),
                'status' => $invoice->status,
                'invoice_pdf' => $invoice->invoice_pdf ?? null,
                'is_credit' => $total < 0,
            ];
        })->toArray();

        return Inertia::render('billing/index', [
            'subscription' => $subscription,
            'paymentMethods' => $user->paymentMethods,
            'invoices' => $invoices,
            'upcomingInvoice' => $upcomingInvoice,
            'serverUsage' => [
                'current' => $user->activeServers()->count(),
                'limit' => $user->getServerLimit(),
                'remaining' => $user->getRemainingServerSlots(),
            ],
            'plans' => SubscriptionPlan::active()->get(),
            'justSubscribed' => $justSubscribed,
            'planChanged' => $planChanged,
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

    /**
     * Create Stripe Checkout session for new subscription.
     */
    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'price_id' => 'required|string',
        ]);

        $user = $request->user();

        // Create Stripe customer if not exists
        if (! $user->hasStripeId()) {
            $user->createAsStripeCustomer();
        }

        $checkout = $user->newSubscription('default', $validated['price_id'])
            ->checkout([
                'success_url' => route('billing.index').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('billing.index'),
            ]);

        return Inertia::location($checkout->url);
    }
}
