<?php

namespace App\Services\Subscription;

use App\Models\BillingEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Create subscription for user.
     */
    public function createSubscription(User $user, string $priceId, ?string $paymentMethodId = null): void
    {
        DB::transaction(function () use ($user, $priceId, $paymentMethodId) {
            $subscription = $user->newSubscription('default', $priceId);

            // Add trial if configured
            if (config('subscription.trial_days')) {
                $subscription->trialDays(config('subscription.trial_days'));
            }

            // Create subscription
            if ($paymentMethodId) {
                $subscription->create($paymentMethodId);
            } else {
                $subscription->create();
            }

            // Log event
            BillingEvent::create([
                'user_id' => $user->id,
                'type' => 'subscription_created',
                'description' => 'Subscription created for plan: '.$priceId,
            ]);
        });
    }

    /**
     * Change subscription plan.
     */
    public function changePlan(User $user, string $newPriceId): void
    {
        DB::transaction(function () use ($user, $newPriceId) {
            $subscription = $user->subscription('default');
            $oldPriceId = $subscription->stripe_price;

            // Swap plans (prorate by default)
            $subscription->swap($newPriceId);

            // Log event
            BillingEvent::create([
                'user_id' => $user->id,
                'type' => 'subscription_updated',
                'description' => "Plan changed from {$oldPriceId} to {$newPriceId}",
            ]);
        });
    }

    /**
     * Cancel subscription.
     */
    public function cancelSubscription(User $user, bool $immediate = false): void
    {
        DB::transaction(function () use ($user, $immediate) {
            $subscription = $user->subscription('default');

            if ($immediate) {
                $subscription->cancelNow();
            } else {
                $subscription->cancel();
            }

            // Log event
            BillingEvent::create([
                'user_id' => $user->id,
                'type' => 'subscription_cancelled',
                'description' => $immediate ? 'Subscription cancelled immediately' : 'Subscription cancelled at period end',
            ]);
        });
    }

    /**
     * Resume cancelled subscription.
     */
    public function resumeSubscription(User $user): void
    {
        DB::transaction(function () use ($user) {
            $subscription = $user->subscription('default');
            $subscription->resume();

            // Log event
            BillingEvent::create([
                'user_id' => $user->id,
                'type' => 'subscription_resumed',
                'description' => 'Subscription resumed',
            ]);
        });
    }

    /**
     * Check if user can create server.
     */
    public function canCreateServer(User $user): array
    {
        $limit = $user->getServerLimit();
        $current = $user->activeServers()->count();
        $canCreate = $current < $limit;

        return [
            'can_create' => $canCreate,
            'current' => $current,
            'limit' => $limit,
            'remaining' => max(0, $limit - $current),
        ];
    }

    /**
     * Record server usage for billing.
     */
    public function recordServerUsage(User $user): void
    {
        if (! config('subscription.overage.enabled')) {
            return;
        }

        $limit = $user->getServerLimit();
        $current = $user->activeServers()->count();
        $overage = max(0, $current - $limit);

        if ($overage > 0 && $user->subscribed('default')) {
            $user->subscription('default')->recordUsage($overage);
        }
    }
}
