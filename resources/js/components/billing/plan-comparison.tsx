import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { Check, TrendingUp } from 'lucide-react';
import { useState } from 'react';

type Subscription = {
    id: number;
    stripe_price: string;
} | null;

type SubscriptionPlan = {
    id: number;
    stripe_product_id: string;
    stripe_price_id: string;
    name: string;
    slug: string;
    amount: number;
    currency: string;
    interval: 'month' | 'year';
    interval_count: number;
    server_limit: number;
    features: string[];
    is_active: boolean;
    formatted_price: string;
};

type PlanComparisonProps = {
    plans: SubscriptionPlan[];
    currentPlan: SubscriptionPlan | undefined;
    subscription: Subscription;
    onManageBilling: () => void;
};

export default function PlanComparison({ plans, currentPlan, subscription, onManageBilling }: PlanComparisonProps) {
    const [billingInterval, setBillingInterval] = useState<'month' | 'year'>('month');

    const filteredPlans = plans.filter((p) => p.interval === billingInterval);

    const handleSelectPlan = (priceId: string, planName: string) => {
        // If user has no subscription, initiate checkout
        if (!subscription) {
            router.post('/billing/checkout', { price_id: priceId });
            return;
        }

        // If user is selecting their current plan, do nothing
        if (isCurrentPlan(priceId)) {
            return;
        }

        // Determine if upgrade or downgrade
        const isUpgrade = currentPlan && currentPlan.amount < filteredPlans.find((p) => p.stripe_price_id === priceId)!.amount;
        const actionType = isUpgrade ? 'upgrade' : 'downgrade';

        // Confirm plan change
        const confirmMessage = isUpgrade
            ? `Upgrade to ${planName}? You'll be charged the prorated difference immediately.`
            : `Downgrade to ${planName}? You'll receive a credit on your next invoice for the unused time.`;

        if (!confirm(confirmMessage)) {
            return;
        }

        // For plan changes (upgrade/downgrade), update subscription
        router.put(
            '/billing/subscriptions',
            { price_id: priceId },
            {
                preserveScroll: true,
            },
        );
    };

    const isCurrentPlan = (priceId: string) => {
        return subscription?.stripe_price === priceId;
    };

    const getButtonText = (plan: SubscriptionPlan) => {
        if (isCurrentPlan(plan.stripe_price_id)) {
            return 'Current Plan';
        }
        if (!subscription) {
            return 'Get Started';
        }
        if (currentPlan && plan.amount > currentPlan.amount) {
            return 'Upgrade';
        }
        if (currentPlan && plan.amount < currentPlan.amount) {
            return 'Downgrade';
        }
        return 'Select Plan';
    };

    return (
        <CardContainer
            title="Available Plans"
            icon={<TrendingUp />}
            action={
                <div className="inline-flex items-center gap-1 rounded-lg border p-1">
                    <button
                        onClick={() => setBillingInterval('month')}
                        className={cn(
                            'rounded-md px-3 py-1 text-sm font-medium transition-colors',
                            billingInterval === 'month' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        Monthly
                    </button>
                    <button
                        onClick={() => setBillingInterval('year')}
                        className={cn(
                            'rounded-md px-3 py-1 text-sm font-medium transition-colors',
                            billingInterval === 'year' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        Yearly
                    </button>
                </div>
            }
        >
            <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                {filteredPlans.map((plan) => {
                    const isCurrent = isCurrentPlan(plan.stripe_price_id);
                    const isPopular = plan.slug === 'pro';

                    return (
                        <div
                            key={plan.id}
                            className={cn(
                                'relative rounded-lg border p-6 transition-all',
                                isCurrent ? 'border-primary ring-2 ring-primary' : 'border-border hover:border-primary/50',
                                isPopular && !isCurrent && 'border-primary/50',
                            )}
                        >
                            {isPopular && (
                                <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                                    <span className="inline-flex items-center rounded-full bg-primary px-3 py-1 text-xs font-medium text-primary-foreground">
                                        Most Popular
                                    </span>
                                </div>
                            )}

                            <div className="space-y-4">
                                <div>
                                    <h3 className="text-lg font-semibold">{plan.name}</h3>
                                    <div className="mt-2 flex items-baseline gap-1">
                                        <span className="text-3xl font-bold">{plan.formatted_price}</span>
                                        <span className="text-sm text-muted-foreground">/ {plan.interval}</span>
                                    </div>
                                </div>

                                <ul className="space-y-3">
                                    {plan.features.map((feature, idx) => (
                                        <li key={idx} className="flex items-start gap-2">
                                            <Check className="mt-0.5 size-4 flex-shrink-0 text-green-600" />
                                            <span className="text-sm text-muted-foreground">{feature}</span>
                                        </li>
                                    ))}
                                </ul>

                                <Button
                                    onClick={() => handleSelectPlan(plan.stripe_price_id, plan.name)}
                                    disabled={isCurrent}
                                    variant={isCurrent ? 'outline' : isPopular ? 'default' : 'outline'}
                                    className="w-full"
                                >
                                    {getButtonText(plan)}
                                </Button>
                            </div>
                        </div>
                    );
                })}
            </div>
        </CardContainer>
    );
}
