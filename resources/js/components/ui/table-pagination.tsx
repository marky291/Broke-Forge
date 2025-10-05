import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';

interface TablePaginationProps {
    currentPage: number;
    totalPages: number;
    totalItems: number;
    perPage: number;
    onPageChange: (page: number) => void;
}

/**
 * TablePagination Component
 *
 * A reusable pagination component for tables and lists.
 *
 * @example
 * ```tsx
 * <TablePagination
 *   currentPage={currentPage}
 *   totalPages={Math.ceil(data.length / perPage)}
 *   totalItems={data.length}
 *   perPage={5}
 *   onPageChange={setCurrentPage}
 * />
 * ```
 */
export function TablePagination({ currentPage, totalPages, totalItems, perPage, onPageChange }: TablePaginationProps) {
    const startItem = totalItems === 0 ? 0 : (currentPage - 1) * perPage + 1;
    const endItem = Math.min(currentPage * perPage, totalItems);

    return (
        <div className="flex items-center justify-between px-4 py-3 border-t border-border/50">
            <div className="text-sm text-muted-foreground">
                Showing {startItem} to {endItem} of {totalItems} results
            </div>
            <div className="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onPageChange(currentPage - 1)}
                    disabled={currentPage === 1}
                >
                    <ChevronLeft className="h-4 w-4" />
                    Previous
                </Button>
                <div className="text-sm text-muted-foreground">
                    Page {currentPage} of {totalPages}
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onPageChange(currentPage + 1)}
                    disabled={currentPage === totalPages}
                >
                    Next
                    <ChevronRight className="h-4 w-4" />
                </Button>
            </div>
        </div>
    );
}

/**
 * usePagination Hook
 *
 * A custom hook to manage pagination state and get paginated data.
 *
 * @example
 * ```tsx
 * const { currentPage, setCurrentPage, paginatedData, paginationProps } = usePagination(data, 5);
 *
 * return (
 *   <>
 *     {paginatedData.map(item => <div key={item.id}>{item.name}</div>)}
 *     <TablePagination {...paginationProps} />
 *   </>
 * );
 * ```
 */
export function usePagination<T>(data: T[], perPage: number = 10) {
    const [currentPage, setCurrentPage] = useState(1);
    const totalPages = Math.ceil(data.length / perPage);

    // Reset to page 1 if data changes and current page is out of bounds
    if (currentPage > totalPages && totalPages > 0) {
        setCurrentPage(1);
    }

    const startIndex = (currentPage - 1) * perPage;
    const endIndex = startIndex + perPage;
    const paginatedData = data.slice(startIndex, endIndex);

    const paginationProps: TablePaginationProps = {
        currentPage,
        totalPages: totalPages || 1,
        totalItems: data.length,
        perPage,
        onPageChange: setCurrentPage,
    };

    return {
        currentPage,
        setCurrentPage,
        paginatedData,
        paginationProps,
    };
}
