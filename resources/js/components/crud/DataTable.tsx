import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Search,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { get } from 'lodash';

/**
 * Safely get a nested value from an object using a dot-notated path.
 */
function getNestedValue(obj: any, path: string): any {
    return get(obj, path);
}


interface DataTableProps<T> {
    data: T[];
    columns: {
        header: React.ReactNode;
        accessorKey?: keyof T;
        cell?: (item: T, index: number) => React.ReactNode;
        className?: string;
    }[];
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
    };
    onSearch?: (query: string) => void;
    onPerPageChange?: (perPage: string) => void;
    onPageChange?: (page: number) => void; // Optional if handling via links directly
    filters?: React.ReactNode; // Extra filters
    initialSearch?: string; // Initial search value
    defaultPerPage?: number; // Default per page count
}

export function DataTable<T>({
    data,
    columns,
    pagination,
    onSearch,
    onPerPageChange,
    onPageChange,
    filters,
    defaultPerPage = 10,
    initialSearch = '',
}: DataTableProps<T>) {
    const [search, setSearch] = useState(initialSearch);
    const isFirstRender = useRef(true);

    useEffect(() => {
        setSearch(initialSearch);
    }, [initialSearch]);
    
    // Simple debounce implementation if hook not found
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        const timer = setTimeout(() => {
            if (onSearch && search !== initialSearch) {
                onSearch(search);
            }
        }, 300);
        return () => clearTimeout(timer);
    }, [search, onSearch, initialSearch]);

    const handlePageChange = (url: string | null) => {
        if (!url) return;
        // Extract page number or just visit the URL
        router.visit(url, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const isFiltered = search.length > 0 || pagination.per_page !== defaultPerPage;

    return (
        <div className="max-sm:has-[div[role='toolbar']]:mb-16 flex flex-1 flex-col gap-4">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex flex-1 items-center gap-2">
                    {filters}
                </div>
                <div className="flex items-center gap-2">
                    <span className="text-sm text-muted-foreground">Rows per page</span>
                    <Select
                        value={String(pagination.per_page)}
                        onValueChange={(value) => {
                            if (onPerPageChange) {
                                onPerPageChange(value);
                            } else {
                                router.get(
                                    window.location.pathname,
                                    { per_page: value },
                                    { preserveState: true, preserveScroll: true }
                                );
                            }
                        }}
                    >
                        <SelectTrigger className="w-[70px]">
                            <SelectValue placeholder={pagination.per_page} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="10">10</SelectItem>
                            <SelectItem value="20">20</SelectItem>
                            <SelectItem value="50">50</SelectItem>
                            <SelectItem value="100">100</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div className="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {columns.map((column, index) => (
                                <TableHead key={index} className={column.className}>
                                    {column.header}
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {data.length > 0 ? (
                            data.map((item, rowIndex) => (
                                <TableRow key={rowIndex}>
                                    {columns.map((column, colIndex) => (
                                        <TableCell key={colIndex} className={column.className}>
                                            {column.cell
                                                ? column.cell(item, rowIndex)
                                                : column.accessorKey
                                                ? (getNestedValue(item, column.accessorKey as string) as React.ReactNode)
                                                : null}

                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length}
                                    className="h-24 text-center"
                                >
                                    No results.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            <div className="flex flex-col sm:flex-row items-center justify-between overflow-clip px-2 @max-2xl/content:flex-col-reverse @max-2xl/content:gap-4 gap-2 mt-auto">
                <div className="text-sm text-muted-foreground">
                    Showing {pagination.from} to {pagination.to} of {pagination.total} entries
                </div>
                <div className="flex flex-col sm:flex-row items-center gap-2 sm:gap-6 lg:gap-8">
                     {/* We can use the links provided by Laravel directly or build custom buttons */}
                    <div className="flex w-max items-center justify-center text-sm font-medium @max-3xl/content:hidden text-muted-foreground">
                        Page {pagination.current_page} of {pagination.last_page}
                    </div>
                    <div className="flex items-center space-x-2">
                        {pagination.links.map((link, i) => {
                            // Render simplified controls: First, Prev, Next, Last
                            // Or just render all links? rendering all links can be messy if too many.
                            // Let's stick to simple Prev/Next for now using the links array to find prev/next urls
                             return null;
                        })}
                        
                        <Button
                            variant="outline"
                            className="h-8 w-8 p-0"
                            onClick={() => handlePageChange(pagination.links[0]?.url)}
                            disabled={!pagination.links[0]?.url || pagination.current_page === 1}
                        >
                            <span className="sr-only">Go to first page</span>
                            <ChevronsLeft className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="outline"
                            className="h-8 w-8 p-0"
                            onClick={() => handlePageChange(pagination.links.find(l => l.label.includes('Previous'))?.url ?? null)}
                             disabled={pagination.current_page === 1}

                        >
                            <span className="sr-only">Go to previous page</span>
                            <ChevronLeft className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="outline"
                            className="h-8 w-8 p-0"
                            onClick={() => handlePageChange(pagination.links.find(l => l.label.includes('Next'))?.url ?? null)}
                            disabled={pagination.current_page === pagination.last_page}
                        >
                            <span className="sr-only">Go to next page</span>
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="outline"
                            className="h-8 w-8 p-0"
                            onClick={() => handlePageChange(pagination.links[pagination.links.length - 1]?.url)}
                            disabled={pagination.current_page === pagination.last_page}
                        >
                            <span className="sr-only">Go to last page</span>
                            <ChevronsRight className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
}
