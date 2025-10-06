import { Plus } from 'lucide-react';
import { type ButtonHTMLAttributes } from 'react';

interface CardContainerAddButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    /** Optional label to display next to the icon */
    label?: string;
    /** Optional aria-label for accessibility */
    'aria-label'?: string;
}

/**
 * CardContainerAddButton Component
 *
 * Reusable "Add" button designed for CardContainer headers
 * Matches the specific design with neutral colors and blue hover state
 * Can display as icon-only or with a label
 */
export function CardContainerAddButton({ label, 'aria-label': ariaLabel = 'Add', ...props }: CardContainerAddButtonProps) {
    return (
        <button
            aria-label={ariaLabel}
            className={`[&_svg]:stroke-1.5 inline-flex h-8 items-center justify-center gap-2.5 rounded-md border border-neutral-200 bg-white/50 text-sm leading-tight whitespace-nowrap text-neutral-600 ring-offset-neutral-50 hover:border-blue-500 hover:bg-white hover:text-neutral-900 focus-visible:ring-1 focus-visible:ring-blue-500 focus-visible:outline-none active:translate-y-px active:bg-neutral-100 disabled:pointer-events-none disabled:opacity-50 dark:border-white/8 dark:bg-white/3 dark:text-neutral-400 dark:ring-offset-neutral-900 dark:hover:border-white/10 dark:hover:bg-neutral-700 dark:hover:text-white dark:active:bg-neutral-700 [&_svg]:-mx-1 [&_svg]:size-4 [&_svg]:text-neutral-400 hover:[&_svg]:text-blue-600 dark:[&_svg]:stroke-1 dark:hover:[&_svg]:text-white ${label ? 'px-3' : 'aspect-square p-0'}`}
            {...props}
        >
            <Plus className="size-4 text-neutral-700 dark:text-neutral-300" aria-hidden="true" />
            {label && <span>{label}</span>}
        </button>
    );
}
