import { cn } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

interface NavigationCardProps {
    /** Navigation items to display */
    items: NavItem[];
    /** Optional title for the navigation group */
    title?: string;
    /** Optional CSS classes */
    className?: string;
}

interface NavigationCardItemProps {
    /** Navigation item data */
    item: NavItem;
}

interface NavigationSidebarProps extends PropsWithChildren {
    /** Optional CSS classes */
    className?: string;
}

/**
 * NavigationCardItem Component
 *
 * Individual navigation item with icon and label
 */
export function NavigationCardItem({ item }: NavigationCardItemProps) {
    const Icon = item.icon;
    return (
        <Link
            href={item.href}
            className={cn(
                'navigation-card-item flex w-full cursor-pointer items-center gap-3 rounded-lg p-3 px-2.5 text-sm transition-all hover:text-neutral-800 dark:hover:text-white',
                item.isActive ? 'border-white/8 bg-white/5 text-neutral-900 opacity-100 dark:text-neutral-50' : 'text-muted-foreground',
            )}
        >
            {Icon && <Icon className="size-4 flex-shrink-0" strokeWidth={2} />}
            <span>{item.title}</span>
        </Link>
    );
}

/**
 * NavigationCard Component
 *
 * Card-styled navigation menu matching CardContainer design
 * Used in both server and site layouts
 */
export function NavigationCard({ items, title, className }: NavigationCardProps) {
    return (
        <div className={cn('', className)}>
            <nav className="">
                {title && (
                    <div className="mb-2 px-3">
                        <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">{title}</span>
                    </div>
                )}
                {items.map((item) => (
                    <NavigationCardItem key={item.href} item={item} />
                ))}
            </nav>
        </div>
    );
}

/**
 * NavigationSidebar Component
 *
 * Sidebar container for NavigationCard components
 */
export function NavigationSidebar({ children, className }: NavigationSidebarProps) {
    return (
        <div className={''}>
            <aside className={cn('w-64', className)}>
                <div className="flex h-full flex-col">
                    <div className="">{children}</div>
                </div>
            </aside>
        </div>
    );
}
