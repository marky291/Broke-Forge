import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';

interface CardContainerProps {
    /** Section title */
    title: string;
    /** Section description/subtitle */
    description?: string;
    /** Optional icon to display on the left of the title */
    icon?: ReactNode;
    /** Optional action button to display on the right side of the title */
    action?: ReactNode;
    /** Section content */
    children: ReactNode;
    /** Additional CSS classes for the container */
    className?: string;
    /** Whether to wrap children with border/shadow styling (default: true) */
    parentBorder?: boolean;
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
 *
 * @example
 * ```tsx
 * // Without parent border - useful when children have their own card styling
 * <CardContainer
 *   title="Versions"
 *   parentBorder={false}
 * >
 *   {items.map(item => (
 *     <div className="divide-y divide-neutral-200 rounded-lg border...">
 *       {item.content}
 *     </div>
 *   ))}
 * </CardContainer>
 * ```
 */
export function CardContainer({ title, description, icon, action, children, className, parentBorder = true }: CardContainerProps) {
    return (
        <div className={cn('rounded-xl border border-neutral-200/70 bg-neutral-50 p-1 dark:border-white/5 dark:bg-white/3 grid gap-1.5 md:p-1.5 md:gap-2', className)}>
            <div className="grid gap-1.5 md:gap-2">
                <div className="flex items-center justify-between p-1.5 pb-1 md:p-2 md:pb-1.5">
                    <div>
                        <div className="flex items-center gap-2">
                            {icon && (
                                <span className="flex size-6 items-center justify-center rounded-md border border-neutral-300 bg-white dark:border-white/10 dark:bg-neutral-700/75 [&_svg]:size-3 [&_svg]:text-neutral-400">
                                    {icon}
                                </span>
                            )}
                            <h2 className="text-sm font-medium text-foreground md:text-base">{title}</h2>
                        </div>
                        {description && <p className="mt-1 text-sm text-muted-foreground">{description}</p>}
                    </div>
                    {action && <div>{action}</div>}
                </div>
                {parentBorder ? (
                    <div className="divide-y divide-neutral-200 rounded-lg border border-neutral-200 bg-white shadow-md shadow-black/5 dark:divide-white/8 dark:border-white/8 dark:bg-white/3">
                        <div className="px-6 py-6">{children}</div>
                    </div>
                ) : (
                    <div>{children}</div>
                )}
            </div>
        </div>
    );
}
