import { BulkActions } from '@/components/crud/BulkActions';
import { ConfirmDialog } from '@/components/crud/ConfirmDialog';
import { ExportButton } from '@/components/crud/ExportButton';
import { FileImport } from '@/components/crud/FileImport';
import { FilterBar, type FilterConfig } from '@/components/crud/FilterBar';
import { PageHeader } from '@/components/crud/PageHeader';
import { StatusBadge } from '@/components/crud/StatusBadge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTable } from '@/components/crud/DataTable';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    ChevronsUpDown,
    Eye,
    Pencil,
    Trash2,
} from 'lucide-react';
import { useCallback, useState } from 'react';
import { get } from 'lodash';

export interface ColumnConfig {
    key: string;
    label: string;
    sortable?: boolean;
    type?: 'text' | 'datetime' | 'boolean' | 'status' | 'number' | 'image' | 'serial';

    className?: string;
}

interface ResourceMeta {
    columns: ColumnConfig[];
    filters: FilterConfig[];
    actions: string[];
    row_selection: boolean;
    per_page: number;
    resource_name: string;
}

interface ResourceIndexProps {
    data: {
        data: any[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    meta: ResourceMeta;
    filters: Record<string, string>;
    // Customization props
    title?: string;
    breadcrumbs?: BreadcrumbItem[];
    baseRoute?: string;
    exportRoute?: string;
    importRoute?: string;
    importFormatUrl?: string;
    // Custom cell renderers
    cellOverrides?: Record<
        string,
        (item: any, index: number) => React.ReactNode
    >;
    // Extra actions per row
    rowActions?: (item: any) => React.ReactNode;
    // Custom header actions
    headerActions?: React.ReactNode;
}

export default function ResourceIndex({
    data,
    meta,
    filters: currentFilters,
    title,
    breadcrumbs,
    baseRoute,
    exportRoute,
    importRoute,
    importFormatUrl,
    cellOverrides,
    rowActions,
    headerActions,
}: ResourceIndexProps) {
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
    const [isImportModalOpen, setIsImportModalOpen] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    const resourceName = title ?? meta.resource_name ?? 'Records';

    // ── Navigation helpers ────────────────────────────────────

    const navigateWithFilters = useCallback(
        (params: Record<string, string>) => {
            const next = { ...currentFilters, ...params };

            // Clean up empty values and default page
            Object.keys(next).forEach((key) => {
                if (
                    next[key] === '' ||
                    next[key] === null ||
                    next[key] === undefined
                ) {
                    delete next[key];
                }
            });

            if (next.page === '1') {
                delete next.page;
            }

            router.get(window.location.pathname, next, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        },
        [currentFilters],
    );

    const handleSearch = (query: string) => {
        navigateWithFilters({ search: query, page: '1' });
    };

    const handlePerPageChange = (perPage: string) => {
        navigateWithFilters({ per_page: perPage, page: '1' });
    };

    const handleFilterChange = (key: string, value: string) => {
        const val = value === 'all' ? '' : value;
        navigateWithFilters({ [key]: val, page: '1' });
    };

    const handleFilterReset = () => {
        router.get(
            window.location.pathname,
            {},
            {
                preserveState: false,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const handleSort = (key: string) => {
        const currentSort = currentFilters.sort;
        const currentDirection = currentFilters.direction;
        const newDirection =
            currentSort === key && currentDirection === 'asc' ? 'desc' : 'asc';
        navigateWithFilters({ sort: key, direction: newDirection });
    };

    // ── Row selection ─────────────────────────────────────────

    const toggleSelect = (id: number) => {
        setSelectedIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    };

    const toggleSelectAll = () => {
        if (selectedIds.size === data.data.length) {
            setSelectedIds(new Set());
        } else {
            setSelectedIds(new Set(data.data.map((item: any) => item.id)));
        }
    };

    const handleBulkDelete = () => {
        if (baseRoute) {
            router.post(
                `${baseRoute}/bulk-destroy`,
                { ids: Array.from(selectedIds) },
                { onSuccess: () => setSelectedIds(new Set()) },
            );
        }
    };

    // ── Build columns for DataTable ───────────────────────────

    const getSortIcon = (key: string) => {
        if (currentFilters.sort !== key)
            return <ArrowUpDown className="ml-1 h-3 w-3" />;
        return currentFilters.direction === 'asc' ? (
            <ArrowUp className="ml-1 h-3 w-3" />
        ) : (
            <ArrowDown className="ml-1 h-3 w-3" />
        );
    };

    const buildColumns = () => {
        const cols: any[] = [];

        // Row selection checkbox column
        if (meta.row_selection) {
            cols.push({
                header: (
                    <Checkbox
                        checked={
                            data.data.length > 0 &&
                            selectedIds.size === data.data.length
                        }
                        onCheckedChange={toggleSelectAll}
                    />
                ),
                cell: (item: any) => (
                    <Checkbox
                        checked={selectedIds.has(item.id)}
                        onCheckedChange={() => toggleSelect(item.id)}
                    />
                ),
                className: 'w-[40px]',
            });
        }

        // Data columns from config
        for (const col of meta.columns) {
            cols.push({
                header: col.sortable ? (
                    <button
                        className="flex items-center hover:text-foreground"
                        onClick={() => handleSort(col.key)}
                    >
                        {col.label}
                        {getSortIcon(col.key)}
                    </button>
                ) : (
                    col.label
                ),
                accessorKey: col.key,
                className: col.className,
                cell: cellOverrides?.[col.key]
                    ? (item: any, index: number) =>
                          cellOverrides[col.key](item, index)
                    : col.type === 'serial'
                      ? (item: any, index: number) => (
                            <span className="text-muted-foreground">
                                {(data.current_page - 1) * data.per_page +
                                    index +
                                    1}
                            </span>
                        )
                      : col.type === 'boolean'
                      ? (item: any) => (
                            <StatusBadge
                                status={
                                    get(item, col.key) ? 'Active' : 'Inactive'
                                }
                                variant={
                                    get(item, col.key) ? 'success' : 'danger'
                                }
                            />
                        )
                      : col.type === 'status'
                        ? (item: any) => {
                              const sFilter = meta.filters.find(
                                  (f) => f.key === col.key,
                              );
                              if (
                                  sFilter &&
                                  sFilter.options &&
                                  meta.actions.includes('edit') &&
                                  baseRoute
                              ) {
                                  return (
                                      <DropdownMenu>
                                          <DropdownMenuTrigger asChild>
                                              <Button
                                                  size="sm"
                                                  variant="outline"
                                                  className="capitalize"
                                              >
                                                  {get(item, col.key) ?? ''}
                                                  <ChevronsUpDown className="ml-auto size-4" />
                                              </Button>
                                          </DropdownMenuTrigger>
                                          <DropdownMenuContent align="start">
                                              {sFilter.options.map(
                                                  (opt: any) => (
                                                      <DropdownMenuItem
                                                          key={opt.value}
                                                          onClick={() => {
                                                              router.post(
                                                                  `${baseRoute}/${item.id}`,
                                                                  {
                                                                      _method:
                                                                          'patch',
                                                                      [col.key]:
                                                                          opt.value,
                                                                  },
                                                                  {
                                                                      preserveScroll: true,
                                                                  },
                                                              );
                                                          }}
                                                      >
                                                          {opt.label}
                                                      </DropdownMenuItem>
                                                  ),
                                              )}
                                          </DropdownMenuContent>
                                      </DropdownMenu>
                                  );
                              }
                              return (
                                  <StatusBadge
                                      status={get(item, col.key) ?? ''}
                                  />
                              );
                          }
                        : col.type === 'datetime'
                          ? (item: any) => (
                                <span className="text-sm text-muted-foreground">
                                    {get(item, col.key)
                                        ? new Date(
                                              get(item, col.key),
                                          ).toLocaleString()
                                        : '—'}
                                </span>
                            )
                          : col.type === 'image'
                            ? (item: any) => {
                                  const val = get(item, col.key);
                                  return val ? (
                                      <img
                                          src={`/storage/${val}`}
                                          alt=""
                                          className="h-10 w-10 rounded border object-cover"
                                      />
                                  ) : (
                                      <span className="text-xs text-muted-foreground">
                                          No Image
                                      </span>
                                  );
                              }
                            : undefined,
            });
        }

        // Action column
        if (meta.actions.length > 0 || rowActions) {
            cols.push({
                header: <span className="text-right">Actions</span>,
                className: 'text-right',
                cell: (item: any) => (
                    <div className="flex items-center justify-end gap-1">
                        {rowActions && rowActions(item)}
                        {meta.actions.includes('show') && baseRoute && (
                            <Button
                                variant="ghost"
                                size="icon"
                                onClick={() =>
                                    router.get(`${baseRoute}/${item.id}`)
                                }
                            >
                                <Eye className="h-4 w-4" />
                            </Button>
                        )}
                        {meta.actions.includes('edit') && baseRoute && (
                            <Button
                                variant="ghost"
                                size="icon"
                                onClick={() =>
                                    router.get(`${baseRoute}/${item.id}/edit`)
                                }
                            >
                                <Pencil className="h-4 w-4" />
                            </Button>
                        )}
                        {meta.actions.includes('delete') && baseRoute && (
                            <ConfirmDialog
                                trigger={
                                    <Button variant="ghost" size="icon">
                                        <Trash2 className="h-4 w-4 text-destructive" />
                                    </Button>
                                }
                                title={`Delete ${meta.resource_name ?? 'Record'}?`}
                                description="This action cannot be undone."
                                confirmLabel="Delete"
                                onConfirm={() =>
                                    router.delete(`${baseRoute}/${item.id}`)
                                }
                            />
                        )}
                    </div>
                ),
            });
        }

        return cols;
    };

    // ── Page header actions ───────────────────────────────────

    const headerActionsList: any[] = [];
    if (meta.actions.includes('create') && baseRoute) {
        headerActionsList.push({
            label: `Add ${meta.resource_name ?? ''}`,
            icon: 'add',
            href: `${baseRoute}/create`,
        });
    }
    if (importRoute) {
        headerActionsList.push({
            label: `Import`,
            icon: 'import',
            onClick: () => setIsImportModalOpen(true),
            variant: 'outline',
        });
    }

    const handleImportUpload = (file: File) => {
        if (!importRoute) return;
        setIsUploading(true);
        const formData = new FormData();
        formData.append('file', file);
        router.post(importRoute, formData, {
            onSuccess: (page) => {
                setIsImportModalOpen(false);
                setIsUploading(false);
            },
            onError: () => {
                setIsUploading(false);
            },
            onFinish: () => {
                setIsUploading(false);
            },
        });
    };

    // ── Render ────────────────────────────────────────────────

    const defaultBreadcrumbs: BreadcrumbItem[] = breadcrumbs ?? [
        { title: resourceName, href: window.location.pathname },
    ];

    return (
        <>
            <Head title={resourceName} />
            <div className="flex flex-1 flex-col gap-4 p-4 sm:gap-6">
                <PageHeader title={resourceName} actions={headerActionsList}>
                    {exportRoute && (
                        <ExportButton
                            href={exportRoute}
                            params={currentFilters}
                        />
                    )}
                    {headerActions}
                </PageHeader>

                {meta.row_selection && (
                    <BulkActions
                        selectedCount={selectedIds.size}
                        onDelete={
                            meta.actions.includes('delete')
                                ? handleBulkDelete
                                : undefined
                        }
                    />
                )}

                <DataTable
                    data={data.data}
                    columns={buildColumns()}
                    pagination={{
                        current_page: data.current_page,
                        last_page: data.last_page,
                        per_page: data.per_page,
                        total: data.total,
                        from: data.from ?? 0,
                        to: data.to ?? 0,
                        links: data.links,
                    }}
                    onPerPageChange={handlePerPageChange}
                    filters={
                        <FilterBar
                            filters={meta.filters}
                            values={currentFilters}
                            onChange={handleFilterChange}
                            onReset={handleFilterReset}
                            search={currentFilters.search ?? ''}
                            onSearch={handleSearch}
                        />
                    }
                />
            </div>

            {importRoute && (
                <Dialog
                    open={isImportModalOpen}
                    onOpenChange={setIsImportModalOpen}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Import {resourceName}</DialogTitle>
                            <DialogDescription>
                                Upload a CSV or Excel file to batch import{' '}
                                {resourceName.toLowerCase()}.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="py-4">
                            <FileImport
                                onUpload={handleImportUpload}
                                uploading={isUploading}
                                formatDownloadUrl={importFormatUrl}
                            />
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </>
    );
}
