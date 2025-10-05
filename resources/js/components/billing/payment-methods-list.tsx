import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { Check, CreditCard, Trash2 } from 'lucide-react';
import { cn } from '@/lib/utils';

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

type PaymentMethodsListProps = {
    paymentMethods: PaymentMethod[];
};

export default function PaymentMethodsList({ paymentMethods }: PaymentMethodsListProps) {
    const handleSetDefault = (paymentMethodId: number) => {
        router.post(`/billing/payment-methods/${paymentMethodId}/default`, {}, {
            preserveScroll: true,
        });
    };

    const handleDelete = (paymentMethodId: number) => {
        if (confirm('Are you sure you want to remove this payment method?')) {
            router.delete(`/billing/payment-methods/${paymentMethodId}`, {
                preserveScroll: true,
            });
        }
    };

    const getBrandIcon = (brand: string | null) => {
        const brandLower = brand?.toLowerCase();

        // You can expand this with actual brand icons
        const brandColors: Record<string, string> = {
            visa: 'text-blue-600',
            mastercard: 'text-orange-600',
            amex: 'text-blue-500',
            discover: 'text-orange-500',
        };

        return brandColors[brandLower || ''] || 'text-gray-600';
    };

    if (!paymentMethods || paymentMethods.length === 0) {
        return (
            <div className="text-center py-8">
                <CreditCard className="mx-auto size-12 text-muted-foreground/30" />
                <h3 className="mt-4 text-sm font-medium">No payment methods</h3>
                <p className="mt-1 text-sm text-muted-foreground">
                    Add a payment method to subscribe to a plan
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-3">
            {paymentMethods.map((method) => (
                <div
                    key={method.id}
                    className={cn(
                        'flex items-center justify-between p-4 rounded-lg border transition-colors',
                        method.is_default
                            ? 'border-primary bg-primary/5'
                            : 'border-border hover:border-primary/50',
                        method.is_expired && 'opacity-60'
                    )}
                >
                    <div className="flex items-center gap-3">
                        <div className={cn(
                            'size-10 rounded-full flex items-center justify-center',
                            method.is_default ? 'bg-primary/10' : 'bg-muted'
                        )}>
                            <CreditCard className={cn(
                                'size-5',
                                method.is_default ? 'text-primary' : getBrandIcon(method.brand)
                            )} />
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <p className="font-medium">{method.display_name}</p>
                                {method.is_default && (
                                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-primary/10 text-primary">
                                        <Check className="size-3" />
                                        Default
                                    </span>
                                )}
                                {method.is_expired && (
                                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                        Expired
                                    </span>
                                )}
                            </div>
                            {method.exp_month && method.exp_year && (
                                <p className="text-sm text-muted-foreground">
                                    Expires {method.exp_month.toString().padStart(2, '0')}/{method.exp_year}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {!method.is_default && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => handleSetDefault(method.id)}
                            >
                                Set as Default
                            </Button>
                        )}
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => handleDelete(method.id)}
                            className="text-destructive hover:text-destructive hover:bg-destructive/10"
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    </div>
                </div>
            ))}
        </div>
    );
}
