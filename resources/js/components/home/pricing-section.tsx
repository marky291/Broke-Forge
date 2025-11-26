import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { register } from '@/routes';
import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';

type SubscriptionPlan = {
    id: number;
    name: string;
    slug: string;
    amount: number;
    interval: 'month' | 'year';
    features: string[];
    formatted_price: string;
};

type FreePlan = {
    name: string;
    server_limit: number;
    features: string[];
};

type PricingSectionProps = {
    plans: SubscriptionPlan[];
    freePlan: FreePlan;
};

export default function PricingSection({ plans, freePlan }: PricingSectionProps) {
    const [billingInterval, setBillingInterval] = useState<'month' | 'year'>('month');

    const filteredPlans = plans.filter((p) => p.interval === billingInterval);

    // Group plans by slug to get unique plan types
    const proPlans = filteredPlans.filter((p) => p.slug === 'pro');
    const enterprisePlans = filteredPlans.filter((p) => p.slug === 'enterprise');

    const proPlan = proPlans[0];
    const enterprisePlan = enterprisePlans[0];

    return (
        <section id="pricing" className="border-t border-white/5 bg-background py-24">
            <div className="mx-auto max-w-6xl px-6">
                {/* Section Header */}
                <div className="mx-auto max-w-2xl text-center">
                    <h2 className="text-3xl font-bold tracking-tight sm:text-4xl">Simple, transparent pricing</h2>
                    <p className="mt-4 text-lg text-muted-foreground">
                        Start free with one server. Upgrade as you grow. No hidden fees.
                    </p>
                </div>

                {/* Billing Toggle */}
                <div className="mt-10 flex justify-center">
                    <div className="inline-flex items-center gap-1 rounded-lg border border-white/10 bg-muted/50 p-1">
                        <button
                            onClick={() => setBillingInterval('month')}
                            className={cn(
                                'rounded-md px-4 py-2 text-sm font-medium transition-colors',
                                billingInterval === 'month'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            Monthly
                        </button>
                        <button
                            onClick={() => setBillingInterval('year')}
                            className={cn(
                                'rounded-md px-4 py-2 text-sm font-medium transition-colors',
                                billingInterval === 'year'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            Yearly
                            <span className="ml-1.5 rounded-full bg-green-500/20 px-2 py-0.5 text-xs text-green-400">Save 20%</span>
                        </button>
                    </div>
                </div>

                {/* Pricing Cards */}
                <div className="mt-12 grid gap-8 lg:grid-cols-3">
                    {/* Free Plan */}
                    <div className="relative rounded-xl border border-white/10 bg-card/50 p-8">
                        <div className="space-y-4">
                            <div>
                                <h3 className="text-lg font-semibold">{freePlan.name}</h3>
                                <div className="mt-4 flex items-baseline gap-1">
                                    <span className="text-4xl font-bold">Free</span>
                                </div>
                                <p className="mt-2 text-sm text-muted-foreground">Perfect for getting started</p>
                            </div>

                            <ul className="space-y-3 pt-4">
                                {freePlan.features.map((feature, idx) => (
                                    <li key={idx} className="flex items-start gap-3">
                                        <Check className="mt-0.5 size-5 flex-shrink-0 text-green-500" />
                                        <span className="text-sm text-muted-foreground">{feature}</span>
                                    </li>
                                ))}
                            </ul>

                            <Button variant="outline" className="mt-6 w-full" asChild>
                                <Link href={register()}>Get Started Free</Link>
                            </Button>
                        </div>
                    </div>

                    {/* Pro Plan */}
                    {proPlan && (
                        <div className="relative rounded-xl border-2 border-primary bg-card p-8">
                            {/* Most Popular Badge */}
                            <div className="absolute -top-4 left-1/2 -translate-x-1/2">
                                <span className="inline-flex items-center rounded-full bg-primary px-4 py-1 text-sm font-medium text-primary-foreground">
                                    Most Popular
                                </span>
                            </div>

                            <div className="space-y-4">
                                <div>
                                    <h3 className="text-lg font-semibold">{proPlan.name}</h3>
                                    <div className="mt-4 flex items-baseline gap-1">
                                        <span className="text-4xl font-bold">{proPlan.formatted_price}</span>
                                        <span className="text-muted-foreground">/ {proPlan.interval}</span>
                                    </div>
                                    <p className="mt-2 text-sm text-muted-foreground">For growing teams and projects</p>
                                </div>

                                <ul className="space-y-3 pt-4">
                                    {proPlan.features.map((feature, idx) => (
                                        <li key={idx} className="flex items-start gap-3">
                                            <Check className="mt-0.5 size-5 flex-shrink-0 text-green-500" />
                                            <span className="text-sm text-muted-foreground">{feature}</span>
                                        </li>
                                    ))}
                                </ul>

                                <Button className="mt-6 w-full" asChild>
                                    <Link href={register()}>Get Started</Link>
                                </Button>
                            </div>
                        </div>
                    )}

                    {/* Enterprise Plan */}
                    {enterprisePlan && (
                        <div className="relative rounded-xl border border-white/10 bg-card/50 p-8">
                            <div className="space-y-4">
                                <div>
                                    <h3 className="text-lg font-semibold">{enterprisePlan.name}</h3>
                                    <div className="mt-4 flex items-baseline gap-1">
                                        <span className="text-4xl font-bold">{enterprisePlan.formatted_price}</span>
                                        <span className="text-muted-foreground">/ {enterprisePlan.interval}</span>
                                    </div>
                                    <p className="mt-2 text-sm text-muted-foreground">For large-scale deployments</p>
                                </div>

                                <ul className="space-y-3 pt-4">
                                    {enterprisePlan.features.map((feature, idx) => (
                                        <li key={idx} className="flex items-start gap-3">
                                            <Check className="mt-0.5 size-5 flex-shrink-0 text-green-500" />
                                            <span className="text-sm text-muted-foreground">{feature}</span>
                                        </li>
                                    ))}
                                </ul>

                                <Button variant="outline" className="mt-6 w-full" asChild>
                                    <Link href={register()}>Get Started</Link>
                                </Button>
                            </div>
                        </div>
                    )}
                </div>

                {/* Additional Info */}
                <p className="mt-12 text-center text-sm text-muted-foreground">
                    All plans include 14-day trial. No credit card required to start.
                </p>
            </div>
        </section>
    );
}
