import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import { router } from '@inertiajs/react';
import { Calendar, CreditCard, TrendingUp } from 'lucide-react';

type Subscription = {
    id: number;
    stripe_id: string;
    stripe_status: string;
    stripe_price: string;
    quantity: number;
    trial_ends_at: string | null;
    ends_at: string | null;
    created_at: string;
} | null;

type SubscriptionPlan =
    | {
          id: number;
          name: string;
          slug: string;
          amount: number;
          currency: string;
          interval: 'month' | 'year';
          formatted_price: string;
      }
    | undefined;

type UpcomingInvoice = {
    total: string;
    period_end: Date;
} | null;

type SubscriptionCardProps = {
    subscription: Subscription;
    currentPlan: SubscriptionPlan;
    isOnTrial: boolean;
    isCancelled: boolean;
    upcomingInvoice: UpcomingInvoice;
};

export default function SubscriptionCard({ subscription, currentPlan, isOnTrial, isCancelled, upcomingInvoice }: SubscriptionCardProps) {
    const handleCancelSubscription = () => {
        if (confirm('Are you sure you want to cancel your subscription? You will lose access at the end of your billing period.')) {
            router.delete('/billing/subscriptions', {
                preserveScroll: true,
            });
        }
    };

    const handleResumeSubscription = () => {
        router.post(
            '/billing/subscriptions/resume',
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const getStatusBadge = () => {
        if (isOnTrial) {
            return (
                <span className="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                    Trial
                </span>
            );
        }
        if (isCancelled) {
            return (
                <span className="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                    Cancelled
                </span>
            );
        }
        if (subscription?.stripe_status === 'active') {
            return (
                <span className="inline-flex items-center rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                    Active
                </span>
            );
        }
        if (subscription?.stripe_status === 'past_due') {
            return (
                <span className="inline-flex items-center rounded-full bg-red-100 px-2.5 py-1 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400">
                    Past Due
                </span>
            );
        }
        return null;
    };

    if (!subscription) {
        return (
            <CardContainer title="Current Plan" icon={TrendingUp}>
                <div className="space-y-4">
                    <div className="flex items-start justify-between">
                        <div>
                            <h3 className="text-2xl font-bold">Free Plan</h3>
                            <p className="mt-1 text-sm text-muted-foreground">Limited to 1 server</p>
                        </div>
                        <span className="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-900/30 dark:text-gray-400">
                            Free
                        </span>
                    </div>
                    <p className="text-sm text-muted-foreground">Upgrade to a paid plan to unlock more servers and advanced features.</p>
                </div>
            </CardContainer>
        );
    }

    return (
        <CardContainer title="Current Subscription" icon={TrendingUp}>
            <div className="space-y-6">
                {/* Plan Info */}
                <div className="flex items-start justify-between">
                    <div>
                        <h3 className="text-2xl font-bold">{currentPlan?.name || 'Unknown Plan'}</h3>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {currentPlan?.formatted_price} / {currentPlan?.interval}
                        </p>
                    </div>
                    {getStatusBadge()}
                </div>

                {/* Trial Info */}
                {isOnTrial && subscription.trial_ends_at && (
                    <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/30">
                        <div className="flex items-start gap-3">
                            <Calendar className="mt-0.5 size-5 text-blue-600 dark:text-blue-400" />
                            <div>
                                <p className="text-sm font-medium text-blue-900 dark:text-blue-100">Trial Period</p>
                                <p className="mt-1 text-sm text-blue-700 dark:text-blue-300">
                                    Your trial ends on {new Date(subscription.trial_ends_at).toLocaleDateString()}
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {/* Cancelled Info */}
                {isCancelled && subscription.ends_at && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/30">
                        <div className="flex items-start gap-3">
                            <Calendar className="mt-0.5 size-5 text-amber-600 dark:text-amber-400" />
                            <div className="flex-1">
                                <p className="text-sm font-medium text-amber-900 dark:text-amber-100">Subscription Cancelled</p>
                                <p className="mt-1 text-sm text-amber-700 dark:text-amber-300">
                                    Your subscription will end on {new Date(subscription.ends_at).toLocaleDateString()}
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {/* Upcoming Invoice */}
                {upcomingInvoice && !isCancelled && (
                    <div className="border-t pt-4">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <CreditCard className="size-4 text-muted-foreground" />
                                <span className="text-sm text-muted-foreground">Next payment</span>
                            </div>
                            <div className="text-right">
                                <p className="font-semibold">{upcomingInvoice.total}</p>
                                <p className="text-xs text-muted-foreground">Due {new Date(upcomingInvoice.period_end).toLocaleDateString()}</p>
                            </div>
                        </div>
                    </div>
                )}

                {/* Actions */}
                <div className="flex gap-2">
                    {isCancelled ? (
                        <Button onClick={handleResumeSubscription} className="w-full">
                            Resume Subscription
                        </Button>
                    ) : (
                        <Button variant="destructive" onClick={handleCancelSubscription} className="w-full">
                            Cancel Subscription
                        </Button>
                    )}
                </div>
            </div>
        </CardContainer>
    );
}
