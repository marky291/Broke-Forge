import { cn } from '@/lib/utils';
import { MoreVertical } from 'lucide-react';
import { type ReactNode } from 'react';
import { CardContainerAddButton } from './card-container-add-button';
import { Button } from './ui/button';
import { CardContainer } from './ui/card-container';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from './ui/dropdown-menu';

export interface CardListAction {
    /** Action label displayed in the dropdown */
    label: string;
    /** Action click handler, receives the item */
    onClick: (item: any) => void;
    /** Action variant - 'destructive' for delete/remove actions */
    variant?: 'default' | 'destructive';
    /** Optional icon to display before the label */
    icon?: ReactNode;
    /** Whether the action is disabled */
    disabled?: boolean;
}

export interface CardListProps<T> {
    /** Section title */
    title: string;
    /** Section description/subtitle */
    description?: string;
    /** Optional icon to display on the left of the title */
    icon?: ReactNode;

    /** Add button click handler - shows + button if provided */
    onAddClick?: () => void;
    /** Optional label for the add button (icon-only if not provided) */
    addButtonLabel?: string;

    /** Array of items to display in the list */
    items: T[];
    /** Render function for each item - receives item and index */
    renderItem: (item: T, index: number) => ReactNode;
    /** Key extractor for React keys - defaults to using index */
    keyExtractor?: (item: T, index: number) => string | number;

    /** Optional click handler for items - makes items clickable */
    onItemClick?: (item: T) => void;

    /** Actions to display in the dropdown menu - can be static array or dynamic function */
    actions?: CardListAction[] | ((item: T) => CardListAction[]);

    /** Custom empty state message */
    emptyStateMessage?: string;
    /** Custom empty state icon */
    emptyStateIcon?: ReactNode;

    /** Additional CSS classes for the container */
    className?: string;
}

/**
 * CardList Component
 *
 * A reusable list component that builds on CardContainer, providing a consistent
 * pattern for rendering lists with optional actions and add buttons.
 *
 * @example
 * ```tsx
 * <CardList
 *   title="Background processes"
 *   items={processes}
 *   onAddClick={() => setShowAddModal(true)}
 *   renderItem={(process) => (
 *     <div className="flex items-center gap-3">
 *       <div className="rounded-md bg-muted p-2">
 *         <Icon className="h-5 w-5" />
 *       </div>
 *       <div>
 *         <div className="font-medium">{process.name}</div>
 *         <div className="text-sm text-muted-foreground">{process.command}</div>
 *       </div>
 *     </div>
 *   )}
 *   actions={[
 *     { label: 'Edit', onClick: (item) => handleEdit(item) },
 *     { label: 'Delete', onClick: (item) => handleDelete(item), variant: 'destructive' }
 *   ]}
 * />
 * ```
 */
export function CardList<T>({
    title,
    description,
    icon,
    onAddClick,
    addButtonLabel,
    items,
    renderItem,
    keyExtractor = (_, index) => index,
    onItemClick,
    actions,
    emptyStateMessage = 'No items yet.',
    emptyStateIcon,
    className,
}: CardListProps<T>) {
    const getActions = (item: T): CardListAction[] | undefined => {
        if (!actions) {
            return undefined;
        }
        return typeof actions === 'function' ? actions(item) : actions;
    };

    return (
        <CardContainer
            title={title}
            description={description}
            icon={icon}
            action={onAddClick ? <CardContainerAddButton label={addButtonLabel} onClick={onAddClick} /> : undefined}
            className={className}
            parentBorder={false}
        >
            <div className="divide-y divide-neutral-200 rounded-xl border border-neutral-200 bg-white dark:divide-white/8 dark:border-white/8 dark:bg-[#141514]">
                {items.length > 0 ? (
                    <div className="divide-y divide-sidebar-border/70">
                        {items.map((item, index) => {
                            const itemActions = getActions(item);
                            const key = keyExtractor(item, index);

                            return (
                                <div
                                    key={key}
                                    onClick={() => onItemClick?.(item)}
                                    className={cn('flex items-center justify-between px-6 py-5', {
                                        'cursor-pointer transition-colors hover:bg-muted/30': onItemClick,
                                    })}
                                >
                                    <div className="flex-1">{renderItem(item, index)}</div>

                                    <div className="ml-3 flex-shrink-0" onClick={(e) => e.stopPropagation()}>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                                    <MoreVertical className="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                {itemActions && itemActions.length > 0 ? (
                                                    itemActions.map((action, actionIndex) => (
                                                        <DropdownMenuItem
                                                            key={actionIndex}
                                                            onClick={() => action.onClick(item)}
                                                            disabled={action.disabled}
                                                            className={cn({
                                                                'text-red-600': action.variant === 'destructive' && !action.disabled,
                                                            })}
                                                        >
                                                            {action.icon && <span className="mr-2">{action.icon}</span>}
                                                            {action.label}
                                                        </DropdownMenuItem>
                                                    ))
                                                ) : (
                                                    <DropdownMenuItem disabled>No actions available</DropdownMenuItem>
                                                )}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                ) : (
                    <div className="flex flex-col items-center justify-center px-6 py-8 text-center">
                        {emptyStateIcon && <div className="mb-3 rounded-full bg-muted p-3">{emptyStateIcon}</div>}
                        <p className="text-sm text-muted-foreground">{emptyStateMessage}</p>
                    </div>
                )}
            </div>
        </CardContainer>
    );
}
