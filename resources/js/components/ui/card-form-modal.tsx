import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
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
}: CardFormModalProps) {
    const submittingText = submittingLabel || `${submitLabel}...`;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={onSubmit}>
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        {description && <DialogDescription>{description}</DialogDescription>}
                    </DialogHeader>
                    <div className="py-4">{children}</div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={isSubmitting}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={isSubmitting || submitDisabled}>
                            {isSubmitting ? submittingText : submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}