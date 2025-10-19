import { type ReactNode } from 'react';

interface PageHeaderProps {
    /** Page title */
    title: string;
    /** Page description/subtitle */
    description?: string;
    /** Optional action element (e.g., button) to display on the right side */
    action?: ReactNode;
    /** Page content to wrap */
    children: ReactNode;
}

/**
 * PageHeader Component
 *
 * A reusable page header component with title, optional description, and optional action element.
 * Wraps the page content in a container with consistent spacing.
 *
 * @example
 * ```tsx
 * <PageHeader
 *   title="Commands"
 *   description="Execute ad-hoc commands on your server within the site's context."
 * >
 *   <CardContainer>...</CardContainer>
 * </PageHeader>
 * ```
 */
export function PageHeader({ title, description, action, children }: PageHeaderProps) {
    return (
        <div className="mx-auto flex max-w-(--breakpoint-lg) flex-col gap-8 p-0 max-md:mt-6 md:gap-8">
            <div>
                <div className="flex items-center justify-between gap-2">
                    <h1 className="text-2xl font-bold">{title}</h1>
                    {action && <div>{action}</div>}
                </div>
                {/*{description && <p className="text-sm text-neutral-500 dark:text-neutral-400">{description}</p>}*/}
            </div>
            {children}
        </div>
    );
}
