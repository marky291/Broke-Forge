import * as React from 'react';
import { cn } from '@/lib/utils';
import { TablePagination } from '@/components/ui/table-pagination';

export interface CardTableColumn<T> {
    /** Column header label */
    header: string;
    /** Accessor function to get cell value from data */
    accessor?: (row: T) => React.ReactNode;
    /** Custom cell renderer */
    cell?: (row: T, index: number) => React.ReactNode;
    /** Text alignment */
    align?: 'left' | 'center' | 'right';
    /** Custom header className */
    headerClassName?: string;
    /** Custom cell className */
    cellClassName?: string;
}

export interface CardTablePaginationProps {
    currentPage: number;
    totalPages: number;
    totalItems: number;
    perPage: number;
    onPageChange: (page: number) => void;
}

export interface CardTableProps<T> {
    /** Table columns configuration */
    columns: CardTableColumn<T>[];
    /** Table data */
    data: T[];
    /** Empty state content */
    emptyState?: React.ReactNode;
    /** Custom row className function based on row data */
    rowClassName?: (row: T, index: number) => string;
    /** Custom row key accessor */
    getRowKey?: (row: T, index: number) => string | number;
    /** Optional pagination configuration */
    pagination?: CardTablePaginationProps;
}

/**
 * CardTable Component
 *
 * A reusable table component designed to work within CardContainer components.
 * Uses flexbox to evenly space columns across full width.
 *
 * @example
 * ```tsx
 * <CardContainer title="Firewall Rules">
 *   <CardTable
 *     columns={[
 *       { header: 'Name', accessor: (row) => row.name },
 *       { header: 'Port', accessor: (row) => row.port },
 *       { header: 'Status', cell: (row) => <Badge>{row.status}</Badge> },
 *     ]}
 *     data={rules}
 *     emptyState={<div>No rules found</div>}
 *   />
 * </CardContainer>
 * ```
 */
export function CardTable<T>({
    columns,
    data,
    emptyState,
    rowClassName,
    getRowKey,
    pagination,
}: CardTableProps<T>) {
    const getAlignClass = (align?: 'left' | 'center' | 'right') => {
        switch (align) {
            case 'center':
                return 'text-center';
            case 'right':
                return 'text-right';
            default:
                return 'text-left';
        }
    };

    if (data.length === 0 && emptyState) {
        return <>{emptyState}</>;
    }

    return (
        <>
            <div className="space-y-2">
                {/* Table Header */}
                <div className="flex w-full gap-2 px-4 py-2 text-sm font-medium text-muted-foreground border-b">
                    {columns.map((column, index) => (
                        <div
                            key={index}
                            className={cn(
                                'flex-1',
                                getAlignClass(column.align),
                                column.headerClassName
                            )}
                        >
                            {column.header}
                        </div>
                    ))}
                </div>

                {/* Table Rows */}
                <div className="divide-y">
                    {data.map((row, rowIndex) => {
                        const key = getRowKey ? getRowKey(row, rowIndex) : rowIndex;

                        return (
                            <div
                                key={key}
                                className={cn(
                                    'flex w-full gap-2 px-4 py-3 items-center transition-all',
                                    rowClassName ? rowClassName(row, rowIndex) : 'hover:bg-muted/50'
                                )}
                            >
                                {columns.map((column, colIndex) => {
                                    const cellContent = column.cell
                                        ? column.cell(row, rowIndex)
                                        : column.accessor
                                        ? column.accessor(row)
                                        : null;

                                    return (
                                        <div
                                            key={colIndex}
                                            className={cn(
                                                'flex-1',
                                                getAlignClass(column.align),
                                                column.cellClassName
                                            )}
                                        >
                                            {cellContent}
                                        </div>
                                    );
                                })}
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* Pagination */}
            {pagination && <TablePagination {...pagination} />}
        </>
    );
}
