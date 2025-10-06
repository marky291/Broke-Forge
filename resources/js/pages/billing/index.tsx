import InvoicesList from '@/components/billing/invoices-list';
import PlanComparison from '@/components/billing/plan-comparison';
import SubscriptionCard from '@/components/billing/subscription-card';
import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { CreditCard, Server as ServerIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Billing', href: '/billing' }];

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

type PaymentMethod = {
    id: number;
    stripe_payment_method_id: string;
    type: string;
    brand: string | null;
    last_four: string | null;
    exp_month: number | null;
    exp_year: number | null;
    is_default: boolean;
    display_name: string;
    is_expired: boolean;
};

type Invoice = {
    id: string;
    date: Date;
    total: string;
    status: string;
    invoice_pdf: string;
};

type UpcomingInvoice = {
    total: string;
    period_end: Date;
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

type ServerUsage = {
    current: number;
    limit: number;
    remaining: number;
};

type BillingProps = {
    subscription: Subscription;
    paymentMethods: PaymentMethod[];
    invoices: Invoice[];
    upcomingInvoice: UpcomingInvoice;
    serverUsage: ServerUsage;
    plans: SubscriptionPlan[];
};

export default function Billing({ subscription, paymentMethods, invoices, upcomingInvoice, serverUsage, plans }: BillingProps) {
    const { auth } = usePage<SharedData>().props;

    const currentPlan = plans.find((p) => p.stripe_price_id === subscription?.stripe_price);
    const isOnTrial = subscription?.trial_ends_at && new Date(subscription.trial_ends_at) > new Date();
    const isCancelled = subscription?.ends_at !== null;

    const handleManageBilling = () => {
        // Force full browser redirect to Stripe portal
        window.location.href = '/billing/portal';
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Billing" />
            <div className="space-y-6">
                {/* Current Subscription */}
                <SubscriptionCard
                    subscription={subscription}
                    currentPlan={currentPlan}
                    isOnTrial={isOnTrial}
                    isCancelled={isCancelled}
                    upcomingInvoice={upcomingInvoice}
                />

                {/* Server Usage */}
                <CardContainer
                    title="Server Usage"
                    icon={ServerIcon}
                    action={
                        <Button variant="ghost" size="sm" onClick={() => router.visit('/dashboard')}>
                            View Servers
                        </Button>
                    }
                >
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-2xl font-bold">
                                    {serverUsage.current} / {serverUsage.limit}
                                </p>
                                <p className="text-sm text-muted-foreground">Active servers</p>
                            </div>
                            <div className="text-right">
                                <p className="text-2xl font-bold text-green-600">{serverUsage.remaining}</p>
                                <p className="text-sm text-muted-foreground">Remaining</p>
                            </div>
                        </div>
                        <div className="h-2 w-full rounded-full bg-muted">
                            <div
                                className="h-2 rounded-full bg-primary transition-all"
                                style={{
                                    width: `${(serverUsage.current / serverUsage.limit) * 100}%`,
                                }}
                            />
                        </div>
                        {serverUsage.remaining === 0 && (
                            <p className="text-sm text-amber-600">You've reached your server limit. Upgrade your plan to add more servers.</p>
                        )}
                    </div>
                </CardContainer>

                {/* Available Plans */}
                <PlanComparison plans={plans} currentPlan={currentPlan} subscription={subscription} onManageBilling={handleManageBilling} />

                {/* Billing Details */}
                <CardContainer title="Billing Details" icon={CreditCard}>
                    <div className="space-y-6">
                        <div>
                            <h3 className="mb-2 font-medium">Manage your subscription</h3>
                            <p className="mb-4 text-sm text-muted-foreground">
                                Go to Stripe billing portal to manage your subscription, payment methods, and more.
                            </p>
                            <Button onClick={handleManageBilling}>Go to Stripe portal</Button>
                        </div>
                    </div>
                </CardContainer>

                {/* Invoices */}
                <InvoicesList invoices={invoices} />
            </div>
        </AppLayout>
    );
}
