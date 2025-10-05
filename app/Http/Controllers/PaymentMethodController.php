<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    /**
     * Store new payment method.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'payment_method_id' => 'required|string',
            'subscribe_to_plan' => 'nullable|string', // Optional: price_id to subscribe to
        ]);

        try {
            $user = $request->user();

            // Check if this is the first payment method BEFORE adding it
            $isFirstPaymentMethod = $user->paymentMethods()->count() === 0;

            // Add payment method to Stripe
            $user->addPaymentMethod($validated['payment_method_id']);

            // Set as default if it's the first payment method
            if ($isFirstPaymentMethod) {
                $user->updateDefaultPaymentMethod($validated['payment_method_id']);
            }

            // Get payment method details from Stripe
            $stripePaymentMethod = $user->findPaymentMethod($validated['payment_method_id']);

            // Store in database
            PaymentMethod::create([
                'user_id' => $user->id,
                'stripe_payment_method_id' => $validated['payment_method_id'],
                'type' => $stripePaymentMethod->type,
                'brand' => $stripePaymentMethod->card->brand ?? null,
                'last_four' => $stripePaymentMethod->card->last4 ?? null,
                'exp_month' => $stripePaymentMethod->card->exp_month ?? null,
                'exp_year' => $stripePaymentMethod->card->exp_year ?? null,
                'is_default' => $isFirstPaymentMethod,
            ]);

            // If user wants to subscribe to a plan after adding payment method
            if (isset($validated['subscribe_to_plan'])) {
                $user->newSubscription('default', $validated['subscribe_to_plan'])
                    ->trialDays(config('subscription.trial_days', 14))
                    ->create($validated['payment_method_id']);

                return to_route('billing.index')->with('success', 'Payment method added and subscription started successfully!');
            }

            // Force a full page reload to refresh all data
            return redirect()->route('billing.index')
                ->with('success', 'Payment method added successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to add payment method: '.$e->getMessage());
        }
    }

    /**
     * Set default payment method.
     */
    public function setDefault(Request $request, PaymentMethod $paymentMethod)
    {
        $this->authorize('update', $paymentMethod);

        try {
            $user = $request->user();

            // Update default in Stripe
            $user->updateDefaultPaymentMethod($paymentMethod->stripe_payment_method_id);

            // Update database
            $user->paymentMethods()->update(['is_default' => false]);
            $paymentMethod->update(['is_default' => true]);

            return redirect()->route('billing.index')
                ->with('success', 'Default payment method updated!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update default payment method: '.$e->getMessage());
        }
    }

    /**
     * Remove payment method.
     */
    public function destroy(PaymentMethod $paymentMethod)
    {
        $this->authorize('delete', $paymentMethod);

        try {
            // Remove from Stripe
            $paymentMethod->user->removePaymentMethod($paymentMethod->stripe_payment_method_id);

            // Remove from database
            $paymentMethod->delete();

            return redirect()->route('billing.index')
                ->with('success', 'Payment method removed successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to remove payment method: '.$e->getMessage());
        }
    }
}
