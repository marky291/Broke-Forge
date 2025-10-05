<?php

namespace App\Http\Controllers;

use App\Models\BillingEvent;
use App\Models\User;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;

class StripeWebhookController extends CashierController
{
    /**
     * Handle invoice payment succeeded.
     */
    public function handleInvoicePaymentSucceeded(array $payload)
    {
        $user = $this->getUserByStripeId($payload['data']['object']['customer']);

        if ($user) {
            BillingEvent::create([
                'user_id' => $user->id,
                'type' => 'invoice.payment_succeeded',
                'stripe_event_id' => $payload['id'],
                'description' => 'Invoice payment succeeded',
                'metadata' => $payload,
            ]);
        }

        return $this->successMethod();
    }

    /**
     * Handle invoice payment failed.
     */
    public function handleInvoicePaymentFailed(array $payload)
    {
        $user = $this->getUserByStripeId($payload['data']['object']['customer']);

        if ($user) {
            BillingEvent::create([
                'user_id' => $user->id,
                'type' => 'invoice.payment_failed',
                'stripe_event_id' => $payload['id'],
                'description' => 'Invoice payment failed',
                'metadata' => $payload,
            ]);

            // Send notification to user
            // $user->notify(new PaymentFailedNotification());
        }

        return $this->successMethod();
    }

    /**
     * Handle customer subscription updated.
     */
    public function handleCustomerSubscriptionUpdated(array $payload)
    {
        $user = $this->getUserByStripeId($payload['data']['object']['customer']);

        if ($user) {
            BillingEvent::create([
                'user_id' => $user->id,
                'type' => 'customer.subscription.updated',
                'stripe_event_id' => $payload['id'],
                'description' => 'Subscription updated',
                'metadata' => $payload,
            ]);
        }

        return parent::handleCustomerSubscriptionUpdated($payload);
    }

    /**
     * Handle customer subscription deleted.
     */
    public function handleCustomerSubscriptionDeleted(array $payload)
    {
        $user = $this->getUserByStripeId($payload['data']['object']['customer']);

        if ($user) {
            BillingEvent::create([
                'user_id' => $user->id,
                'type' => 'customer.subscription.deleted',
                'stripe_event_id' => $payload['id'],
                'description' => 'Subscription deleted',
                'metadata' => $payload,
            ]);
        }

        return parent::handleCustomerSubscriptionDeleted($payload);
    }

    /**
     * Get user by Stripe customer ID.
     */
    protected function getUserByStripeId($stripeId)
    {
        return User::where('stripe_id', $stripeId)->first();
    }
}
