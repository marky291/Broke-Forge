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
            className={`[&_svg]:stroke-1.5 inline-flex items-center justify-center rounded-md whitespace-nowrap ring-offset-neutral-50 focus-visible:ring-1 focus-visible:ring-blue-500 focus-visible:outline-none active:translate-y-px disabled:pointer-events-none disabled:opacity-50 dark:ring-offset-neutral-900 dark:[&_svg]:stroke-1 border [&_svg]:text-neutral-400 hover:text-neutral-900 dark:hover:text-white bg-white/50 dark:bg-white/3 border-neutral-200 dark:border-white/8 text-neutral-600 dark:text-neutral-400 active:bg-neutral-100 hover:bg-white hover:border-blue-500 hover:[&_svg]:text-blue-600 dark:hover:[&_svg]:text-white dark:hover:bg-neutral-700 dark:hover:border-white/10 dark:active:bg-neutral-700 h-8 gap-2.5 text-sm leading-tight [&_svg]:size-4 [&_svg]:-mx-1 ${label ? 'px-3' : 'aspect-square p-0'}`}
            {...props}
        >
            <Plus className="size-4 text-neutral-700 dark:text-neutral-300" aria-hidden="true" />
            {label && <span>{label}</span>}
        </button>
    );
}