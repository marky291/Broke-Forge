import { cn } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';

interface NavigationMenuProps {
    /** Navigation items to display */
    items: NavItem[];
    /** Optional title for the navigation group */
    title?: string;
    /** Optional CSS classes */
    className?: string;
}

interface NavigationSectionProps {
    /** Section title */
    title?: string;
    /** Navigation items to display */
    items: NavItem[];
    /** Optional CSS classes */
    className?: string;
}

/**
 * NavigationMenu Component
 *
 * Reusable navigation menu component with CardContainer-style design
 * Used in both server and site layouts
 */
export function NavigationMenu({ items, title, className }: NavigationMenuProps) {
    return (
        <div className={cn('rounded-xl border border-neutral-200/70 bg-neutral-50 p-1.5 dark:border-white/5 dark:bg-white/3', className)}>
            <nav className="divide-y divide-neutral-200 rounded-lg border border-neutral-200 bg-white shadow-md shadow-black/5 dark:divide-white/8 dark:border-white/8 dark:bg-white/3">
                {title && (
                    <div className="px-4 py-3">
                        <div className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground/70 select-none">
                            {title}
                        </div>
                    </div>
                )}
                <div className="p-2">
                    {items.map((item) => {
                        const Icon = item.icon;
                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={cn(
                                    'group flex items-center gap-3 px-3 py-2 text-sm font-medium transition-all duration-150 rounded-lg',
                                    item.isActive
                                        ? 'bg-accent text-accent-foreground'
                                        : 'text-muted-foreground hover:text-foreground hover:bg-accent/50'
                                )}
                            >
                                {Icon && <Icon className="size-4 flex-shrink-0" strokeWidth={2} />}
                                <span>{item.title}</span>
                            </Link>
                        );
                    })}
                </div>
            </nav>
        </div>
    );
}

/**
 * NavigationSection Component
 *
 * Navigation menu with optional section title (legacy support)
 */
export function NavigationSection({ title, items, className }: NavigationSectionProps) {
    return <NavigationMenu items={items} title={title} className={className} />;
}