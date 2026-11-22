import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
    RotateCcw,
    SlidersHorizontal,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

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

export interface DataTableColumn<T> {
    header: React.ReactNode;
    accessorKey?: keyof T | string;
    cell?: (item: T, index: number) => React.ReactNode;
    className?: string;
    columnKey?: string;
    columnLabel?: string;
    hideable?: boolean;
    defaultHidden?: boolean;
}

interface DataTableProps<T> {
    data: T[];
    columns: DataTableColumn<T>[];
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
    storageKey?: string;
    rowClassName?: (item: T) => string | undefined;
}

export function DataTable<T>({
    data,
    columns,
    pagination,
    onSearch,
    onPerPageChange,
    filters,
    initialSearch = '',
    storageKey,
    rowClassName,
}: DataTableProps<T>) {
    const [search, setSearch] = useState(initialSearch);
    const isFirstRender = useRef(true);

    const hideableColumns = useMemo(
        () => columns.filter((c) => c.columnKey && c.hideable !== false),
        [columns],
    );

    const defaultHiddenKeys = useMemo(
        () =>
            hideableColumns
                .filter((c) => c.defaultHidden)
                .map((c) => c.columnKey!),
        [hideableColumns],
    );

    const [hiddenColumns, setHiddenColumns] = useState<Set<string>>(() => {
        if (storageKey && typeof window !== 'undefined') {
            try {
                const saved = window.localStorage.getItem(
                    `crud:cols:${storageKey}`,
                );
                if (saved) {
                    const parsed = JSON.parse(saved);
                    if (Array.isArray(parsed)) {
                        return new Set(parsed.map(String));
                    }
                }
            } catch {
                // Ignore storage errors
            }
        }
        return new Set(defaultHiddenKeys);
    });

    const toggleColumn = (key: string) => {
        setHiddenColumns((prev) => {
            const next = new Set(prev);
            if (next.has(key)) next.delete(key);
            else next.add(key);

            if (storageKey && typeof window !== 'undefined') {
                try {
                    window.localStorage.setItem(
                        `crud:cols:${storageKey}`,
                        JSON.stringify(Array.from(next)),
                    );
                } catch {
                    // Ignore storage errors
                }
            }
            return next;
        });
    };

    const resetColumns = () => {
        const next = new Set(defaultHiddenKeys);
        setHiddenColumns(next);
        if (storageKey && typeof window !== 'undefined') {
            try {
                window.localStorage.removeItem(`crud:cols:${storageKey}`);
            } catch {
                // Ignore storage errors
            }
        }
    };

    const visibleColumns = useMemo(
        () =>
            columns.filter(
                (col) => !col.columnKey || !hiddenColumns.has(col.columnKey),
            ),
        [columns, hiddenColumns],
    );

    const perPageOptions = useMemo(() => {
        const defaults = [10, 15, 25, 50, 100];
        const current = Number(pagination.per_page);
        if (Number.isFinite(current) && current > 0 && !defaults.includes(current)) {
            defaults.push(current);
        }
        return defaults.sort((a, b) => a - b);
    }, [pagination.per_page]);

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
                    {hideableColumns.length > 0 && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="h-8 gap-1.5"
                                >
                                    <SlidersHorizontal className="h-3.5 w-3.5" />
                                    <span>Columns</span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-48">
                                <DropdownMenuLabel className="text-muted-foreground text-xs font-semibold">
                                    Toggle columns
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <div className="max-h-[260px] overflow-y-auto">
                                    {hideableColumns.map((col) => {
                                        const key = col.columnKey!;
                                        const isVisible =
                                            !hiddenColumns.has(key);
                                        return (
                                            <DropdownMenuCheckboxItem
                                                key={key}
                                                checked={isVisible}
                                                onSelect={(e) => {
                                                    e.preventDefault();
                                                    toggleColumn(key);
                                                }}
                                                className="cursor-pointer"
                                            >
                                                <span className="truncate">
                                                    {col.columnLabel ?? key}
                                                </span>
                                            </DropdownMenuCheckboxItem>
                                        );
                                    })}
                                </div>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onSelect={(e) => {
                                        e.preventDefault();
                                        resetColumns();
                                    }}
                                    className="text-muted-foreground focus:text-foreground cursor-pointer justify-center gap-1.5 text-xs font-medium"
                                >
                                    <RotateCcw className="h-3.5 w-3.5" />
                                    <span>Reset columns</span>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}

                    <span className="text-muted-foreground text-sm whitespace-nowrap">
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
                        <SelectTrigger className="h-8 w-[72px]">
                            <SelectValue placeholder={String(pagination.per_page)} />
                        </SelectTrigger>
                        <SelectContent>
                            {perPageOptions.map((size) => (
                                <SelectItem key={size} value={String(size)}>
                                    {size}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div className="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {visibleColumns.map((column, index) => (
                                <TableHead
                                    key={column.columnKey ?? index}
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
                                <TableRow
                                    key={rowIndex}
                                    className={rowClassName?.(item)}
                                >
                                    {visibleColumns.map((column, colIndex) => (
                                        <TableCell
                                            key={column.columnKey ?? colIndex}
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
                                    colSpan={visibleColumns.length}
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
                    Showing {pagination.from ?? 0} to {pagination.to ?? 0} of{' '}
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
