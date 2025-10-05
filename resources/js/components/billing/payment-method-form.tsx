import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { CardElement, useElements, useStripe } from '@stripe/react-stripe-js';
import { useState } from 'react';
import { Loader2 } from 'lucide-react';

type PaymentMethodFormProps = {
    onSuccess: () => void;
    subscribeToPlan?: string | null;
};

export default function PaymentMethodForm({ onSuccess, subscribeToPlan }: PaymentMethodFormProps) {
    const stripe = useStripe();
    const elements = useElements();
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!stripe || !elements) {
            return;
        }

        setProcessing(true);
        setError(null);

        try {
            const cardElement = elements.getElement(CardElement);

            if (!cardElement) {
                throw new Error('Card element not found');
            }

            // Create payment method
            const { error: stripeError, paymentMethod } = await stripe.createPaymentMethod({
                type: 'card',
                card: cardElement,
            });

            if (stripeError) {
                throw new Error(stripeError.message);
            }

            // Submit to backend
            const data: any = {
                payment_method_id: paymentMethod.id,
            };

            // If subscribing to a plan, include it
            if (subscribeToPlan) {
                data.subscribe_to_plan = subscribeToPlan;
            }

            router.post('/billing/payment-methods', data, {
                preserveState: false,
                preserveScroll: false,
                onSuccess: () => {
                    // Force a full browser redirect to refresh everything
                    window.location.href = '/billing';
                },
                onError: (errors) => {
                    console.error('Payment method error:', errors);
                    setError(Object.values(errors)[0] as string);
                    setProcessing(false);
                },
            });
        } catch (err) {
            setError(err instanceof Error ? err.message : 'An error occurred');
            setProcessing(false);
        }
    };

    // Detect dark mode
    const isDarkMode = document.documentElement.classList.contains('dark');

    const cardElementOptions = {
        style: {
            base: {
                fontSize: '16px',
                color: isDarkMode ? '#ffffff' : '#09090b',
                fontFamily: 'system-ui, sans-serif',
                iconColor: isDarkMode ? '#a1a1aa' : '#71717a',
                '::placeholder': {
                    color: isDarkMode ? '#71717a' : '#a1a1aa',
                },
            },
            invalid: {
                color: isDarkMode ? '#fca5a5' : '#dc2626',
                iconColor: isDarkMode ? '#fca5a5' : '#dc2626',
            },
        },
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-2">
                <label className="text-sm font-medium">Card Information</label>
                <div className="p-3 border rounded-lg bg-white dark:bg-zinc-800 border-input">
                    <CardElement options={cardElementOptions} />
                </div>
            </div>

            {error && (
                <div className="p-3 rounded-lg bg-destructive/10 border border-destructive/20">
                    <p className="text-sm text-destructive">{error}</p>
                </div>
            )}

            <div className="flex gap-2">
                <Button
                    type="submit"
                    disabled={!stripe || processing}
                    className="flex-1"
                >
                    {processing ? (
                        <>
                            <Loader2 className="mr-2 size-4 animate-spin" />
                            Processing...
                        </>
                    ) : (
                        'Add Payment Method'
                    )}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    onClick={onSuccess}
                    disabled={processing}
                >
                    Cancel
                </Button>
            </div>
        </form>
    );
}
