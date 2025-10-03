import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';

interface CardContainerProps {
    /** Section title */
    title: string;
    /** Section description/subtitle */
    description?: string;
    /** Optional action button to display on the right side of the title */
    action?: ReactNode;
    /** Section content */
    children: ReactNode;
    /** Additional CSS classes for the container */
    className?: string;
}

/**
 * CardContainer Component
 *
 * A reusable container component with title, optional description, optional action button, and consistent styling.
 *
 * @example
 * ```tsx
 * <CardContainer
 *   title="Application"
 *   description="Repository information and deployment settings"
 *   action={<CardContainerAddButton label="Add App" />}
 * >
 *   <div>Your content here</div>
 * </CardContainer>
 * ```
 */
export function CardContainer({ title, description, action, children, className }: CardContainerProps) {
    return (
        <div className={cn('rounded-xl border border-neutral-200/70 bg-neutral-50 p-1.5 dark:border-white/5 dark:bg-white/3', className)}>
            <div className="space-y-4">
                <div className="px-4 pt-4 flex items-start justify-between">
                    <div>
                        <h2 className="text-xl font-medium text-foreground">{title}</h2>
                        {description && <p className="mt-1 text-sm text-muted-foreground">{description}</p>}
                    </div>
                    {action && <div>{action}</div>}
                </div>
                <div className="divide-y divide-neutral-200 rounded-lg border border-neutral-200 bg-white shadow-md shadow-black/5 dark:divide-white/8 dark:border-white/8 dark:bg-white/3">
                    <div className="px-6 py-6">{children}</div>
                </div>
            </div>
        </div>
    );
}