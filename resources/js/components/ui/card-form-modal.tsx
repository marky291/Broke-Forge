import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { XIcon } from 'lucide-react';
import { type ReactNode } from 'react';

interface CardFormModalProps {
    /** Whether the modal is open */
    open: boolean;
    /** Callback when the modal open state changes */
    onOpenChange: (open: boolean) => void;
    /** Modal title */
    title: string;
    /** Optional modal description */
    description?: string;
    /** Form fields content */
    children: ReactNode;
    /** Form submit handler */
    onSubmit: (e: React.FormEvent) => void;
    /** Submit button label */
    submitLabel: string;
    /** Whether the form is currently submitting */
    isSubmitting?: boolean;
    /** Optional additional condition to disable submit button */
    submitDisabled?: boolean;
    /** Optional label for submitting state (defaults to submitLabel with '...' suffix) */
    submittingLabel?: string;
    /** Optional className for DialogContent */
    className?: string;
}

/**
 * CardFormModal Component
 *
 * A reusable modal component for forms in server-related pages.
 * Provides consistent styling and behavior for all server form modals.
 *
 * @example
 * ```tsx
 * <CardFormModal
 *   open={isOpen}
 *   onOpenChange={setIsOpen}
 *   title="Add PHP Version"
 *   description="Install a new PHP version on this server."
 *   onSubmit={handleSubmit}
 *   submitLabel="Install"
 *   isSubmitting={form.processing}
 * >
 *   <div className="space-y-2">
 *     <Label>Version</Label>
 *     <Select>...</Select>
 *   </div>
 * </CardFormModal>
 * ```
 */
export function CardFormModal({
    open,
    onOpenChange,
    title,
    description,
    children,
    onSubmit,
    submitLabel,
    isSubmitting = false,
    submitDisabled = false,
    submittingLabel,
    className,
}: CardFormModalProps) {
    const submittingText = submittingLabel || `${submitLabel}...`;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className={`border-0 bg-transparent p-0 shadow-none [&>button]:hidden ${className}`}>
                {/* Outer border div - glassmorphism effect */}
                <div className="rounded-2xl border border-neutral-200/50 bg-white/50 p-3 dark:border-neutral-700/50 dark:bg-black/50">
                    {/* Inner border div - white content area */}
                    <div className="relative rounded-xl border border-neutral-200 bg-white shadow-lg dark:border-neutral-800 dark:bg-neutral-950">
                        {/* Close button positioned inside */}
                        <button
                            onClick={() => onOpenChange(false)}
                            disabled={isSubmitting}
                            className="ring-offset-background focus:ring-ring absolute right-4 top-4 rounded-sm opacity-70 transition-opacity hover:opacity-100 focus:ring-2 focus:ring-offset-2 focus:outline-hidden disabled:pointer-events-none"
                            type="button"
                        >
                            <XIcon className="h-4 w-4" />
                            <span className="sr-only">Close</span>
                        </button>

                        {/* Form with proper padding */}
                        <form onSubmit={onSubmit} className="flex min-w-0 flex-col gap-4 p-6">
                            <DialogHeader>
                                <DialogTitle>{title}</DialogTitle>
                                {description && <DialogDescription>{description}</DialogDescription>}
                            </DialogHeader>
                            <div className="min-w-0">{children}</div>
                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={isSubmitting}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={isSubmitting || submitDisabled}>
                                    {isSubmitting ? submittingText : submitLabel}
                                </Button>
                            </DialogFooter>
                        </form>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}