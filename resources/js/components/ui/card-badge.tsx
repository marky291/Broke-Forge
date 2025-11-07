import { Loader2, Pause, X } from 'lucide-react';
import { type ReactNode } from 'react';

type BadgeVariant = 'pending' | 'installing' | 'active' | 'inactive' | 'paused' | 'failed' | 'removing' | 'updating' | 'stopped' | 'uninstalling';

interface CardBadgeProps {
    variant: BadgeVariant;
    label?: string;
    icon?: ReactNode;
}

const variantConfig: Record<
    BadgeVariant,
    {
        label: string;
        icon: ReactNode;
        className: string;
    }
> = {
    pending: {
        label: 'Pending',
        icon: <Loader2 className="h-3 w-3 animate-spin" />,
        className: 'bg-slate-500/10 border-slate-200 text-slate-600 dark:border-slate-800 dark:text-slate-400',
    },
    installing: {
        label: 'Installing',
        icon: <Loader2 className="h-3 w-3 animate-spin" />,
        className: 'bg-blue-500/10 border-blue-200 text-blue-600 dark:border-blue-800 dark:text-blue-400',
    },
    updating: {
        label: 'Updating',
        icon: <Loader2 className="h-3 w-3 animate-spin" />,
        className: 'bg-blue-500/10 border-blue-200 text-blue-600 dark:border-blue-800 dark:text-blue-400',
    },
    active: {
        label: 'Installed',
        icon: (
            <svg className="h-4 w-4" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="8" cy="8" r="7" fill="currentColor" fillOpacity="0.2" />
                <path d="M5 8l2 2 4-4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
        ),
        className: 'bg-emerald-50 border-emerald-200 text-emerald-700 dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-400',
    },
    inactive: {
        label: 'Inactive',
        icon: <Pause className="h-3 w-3" />,
        className: 'bg-amber-500/10 border-amber-200 text-amber-600 dark:border-amber-800 dark:text-amber-400',
    },
    paused: {
        label: 'Paused',
        icon: <Pause className="h-3 w-3" />,
        className: 'bg-amber-500/10 border-amber-200 text-amber-600 dark:border-amber-800 dark:text-amber-400',
    },
    failed: {
        label: 'Failed',
        icon: <X className="h-3 w-3" />,
        className: 'bg-red-500/10 border-red-200 text-red-600 dark:border-red-800 dark:text-red-400',
    },
    removing: {
        label: 'Removing',
        icon: <Loader2 className="h-3 w-3 animate-spin" />,
        className: 'bg-blue-500/10 border-blue-200 text-blue-600 dark:border-blue-800 dark:text-blue-400',
    },
    uninstalling: {
        label: 'Uninstalling',
        icon: <Loader2 className="h-3 w-3 animate-spin" />,
        className: 'bg-blue-500/10 border-blue-200 text-blue-600 dark:border-blue-800 dark:text-blue-400',
    },
    stopped: {
        label: 'Stopped',
        icon: null,
        className: 'bg-gray-500/10 border-gray-200 text-gray-600 dark:border-gray-800 dark:text-gray-400',
    },
};

export function CardBadge({ variant, label, icon }: CardBadgeProps) {
    const config = variantConfig[variant];
    const displayLabel = label || config.label;
    const displayIcon = icon !== undefined ? icon : config.icon;

    return (
        <span className={`inline-flex items-center gap-2 rounded-md px-2 py-1 text-xs font-medium border ${config.className}`}>
            {displayIcon}
            {displayLabel}
        </span>
    );
}
