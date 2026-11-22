import { Button } from '@/components/ui/button';
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
import { get } from 'lodash';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/**
 * Safely get a value from an object, checking direct property first
 * (for server-formatted dot-notated keys) then dot-notated path.
 */
function getNestedValue(obj: any, path: string): any {
    if (obj && Object.prototype.hasOwnProperty.call(obj, path)) {
        return obj[path];
    }
    return get(obj, path);
}

interface DataTableProps<T> {
    data: T[];
    columns: {
        header: React.ReactNode;
        accessorKey?: keyof T | string;
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
        first_page_url?: string | null;
        last_page_url?: string | null;
        prev_page_url?: string | null;
        next_page_url?: string | null;
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
    };
    onSearch?: (query: string) => void;
    onPerPageChange?: (perPage: string) => void;
    onPageChange?: (page: number) => void;
    filters?: React.ReactNode;
    initialSearch?: string;
    defaultPerPage?: number;
}

export function DataTable<T>({
    data,
    columns,
    pagination,
    onSearch,
    onPerPageChange,
    filters,
    initialSearch = '',
}: DataTableProps<T>) {
    const [search, setSearch] = useState(initialSearch);
    const isFirstRender = useRef(true);

    useEffect(() => {
        setSearch(initialSearch);
    }, [initialSearch]);

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

    const handlePageChange = (url: string | null | undefined) => {
        if (!url) return;
        router.visit(url, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    // Resolve first, prev, next, and last URLs cleanly
    const firstPageUrl =
        pagination.first_page_url ??
        pagination.links.find((l) => l.label.trim() === '1')?.url ??
        pagination.links[1]?.url ??
        null;

    const prevPageUrl =
        pagination.prev_page_url ??
        pagination.links.find((l) => l.label.includes('Previous'))?.url ??
        null;

    const nextPageUrl =
        pagination.next_page_url ??
        pagination.links.find((l) => l.label.includes('Next'))?.url ??
        null;

    const lastPageUrl =
        pagination.last_page_url ??
        pagination.links.find(
            (l) => l.label.trim() === String(pagination.last_page),
        )?.url ??
        (pagination.links.length >= 2
            ? pagination.links[pagination.links.length - 2]?.url
            : null) ??
        null;

    return (
        <div className="flex flex-1 flex-col gap-4 max-sm:has-[div[role='toolbar']]:mb-16">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex flex-1 items-center gap-2">{filters}</div>
                <div className="flex items-center gap-2">
                    <span className="text-muted-foreground text-sm">
                        Rows per page
                    </span>
                    <Select
                        value={String(pagination.per_page)}
                        onValueChange={(value) => {
                            if (onPerPageChange) {
                                onPerPageChange(value);
                            } else {
                                router.get(
                                    window.location.pathname,
                                    { per_page: value },
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                    },
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
                                <TableHead
                                    key={index}
                                    className={column.className}
                                >
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
                                        <TableCell
                                            key={colIndex}
                                            className={column.className}
                                        >
                                            {column.cell
                                                ? column.cell(item, rowIndex)
                                                : column.accessorKey
                                                  ? (getNestedValue(
                                                        item,
                                                        column.accessorKey as string,
                                                    ) as React.ReactNode)
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

            <div className="mt-auto flex flex-col items-center justify-between gap-2 overflow-clip px-2 sm:flex-row @max-2xl/content:flex-col-reverse @max-2xl/content:gap-4">
                <div className="text-muted-foreground text-sm">
                    Showing {pagination.from} to {pagination.to} of{' '}
                    {pagination.total} entries
                </div>
                <div className="flex flex-col items-center gap-2 sm:flex-row sm:gap-6 lg:gap-8">
                    <div className="text-muted-foreground flex w-max items-center justify-center text-sm font-medium @max-3xl/content:hidden">
                        Page {pagination.current_page} of {pagination.last_page}
                    </div>
                    <div className="flex items-center space-x-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="h-8 w-8 p-0"
                            onClick={() => handlePageChange(firstPageUrl)}
                            disabled={
                                !firstPageUrl || pagination.current_page <= 1
                            }
                        >
                            <span className="sr-only">Go to first page</span>
                            <ChevronsLeft className="h-4 w-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            className="h-8 w-8 p-0"
                            onClick={() => handlePageChange(prevPageUrl)}
                            disabled={
                                !prevPageUrl || pagination.current_page <= 1
                            }
                        >
                            <span className="sr-only">Go to previous page</span>
                            <ChevronLeft className="h-4 w-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            className="h-8 w-8 p-0"
                            onClick={() => handlePageChange(nextPageUrl)}
                            disabled={
                                !nextPageUrl ||
                                pagination.current_page >= pagination.last_page
                            }
                        >
                            <span className="sr-only">Go to next page</span>
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            className="h-8 w-8 p-0"
                            onClick={() => handlePageChange(lastPageUrl)}
                            disabled={
                                !lastPageUrl ||
                                pagination.current_page >= pagination.last_page
                            }
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
