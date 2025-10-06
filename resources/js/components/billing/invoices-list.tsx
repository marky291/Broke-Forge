import { Button } from '@/components/ui/button';
import { CardContainer } from '@/components/ui/card-container';
import { cn } from '@/lib/utils';
import { Download, Receipt } from 'lucide-react';

type Invoice = {
    id: string;
    date: Date;
    total: string;
    status: string;
    invoice_pdf: string;
};

type InvoicesListProps = {
    invoices: Invoice[];
};

export default function InvoicesList({ invoices }: InvoicesListProps) {
    const getStatusBadge = (status: string) => {
        const statusConfig: Record<string, { color: string; label: string }> = {
            paid: { color: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400', label: 'Paid' },
            open: { color: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400', label: 'Open' },
            void: { color: 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400', label: 'Void' },
            uncollectible: { color: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400', label: 'Uncollectible' },
        };

        const config = statusConfig[status.toLowerCase()] || statusConfig.open;

        return <span className={cn('inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium', config.color)}>{config.label}</span>;
    };

    if (!invoices || invoices.length === 0) {
        return (
            <CardContainer title="Invoices" icon={<Receipt />}>
                <div className="py-8 text-center">
                    <Receipt className="mx-auto size-12 text-muted-foreground/30" />
                    <h3 className="mt-4 text-sm font-medium">No invoices</h3>
                    <p className="mt-1 text-sm text-muted-foreground">Your invoices will appear here once you have a subscription</p>
                </div>
            </CardContainer>
        );
    }

    return (
        <CardContainer title="Invoices" icon={<Receipt />}>
            <div className="space-y-3">
                {invoices.map((invoice) => (
                    <div
                        key={invoice.id}
                        className="flex items-center justify-between rounded-lg border border-border p-4 transition-colors hover:border-primary/50"
                    >
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-full bg-muted">
                                <Receipt className="size-5 text-muted-foreground" />
                            </div>
                            <div>
                                <p className="font-medium">
                                    {new Date(invoice.date).toLocaleDateString('en-US', {
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric',
                                    })}
                                </p>
                                <p className="text-sm text-muted-foreground">{invoice.total}</p>
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            {getStatusBadge(invoice.status)}
                            <Button variant="outline" size="sm" onClick={() => window.open(invoice.invoice_pdf, '_blank')}>
                                <Download className="mr-2 size-4" />
                                Download
                            </Button>
                        </div>
                    </div>
                ))}
            </div>
        </CardContainer>
    );
}
