import { BulkActions } from '@/components/crud/BulkActions';
import { ConfirmDialog } from '@/components/crud/ConfirmDialog';
import { DataTable } from '@/components/crud/DataTable';
import { ExportButton } from '@/components/crud/ExportButton';
import { FileImport } from '@/components/crud/FileImport';
import { FilterBar } from '@/components/crud/FilterBar';
import { PageHeader } from '@/components/crud/PageHeader';
import { StatusBadge } from '@/components/crud/StatusBadge';
import type {
    ColumnConfig,
    ResourceIndexProps,
    ResourceMeta,
} from '@/components/crud/types';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Head, router } from '@inertiajs/react';
import { get } from 'lodash';
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

export type { ColumnConfig, ResourceIndexProps, ResourceMeta };

/**
 * Prefer direct property lookup (supports server-formatted dot-notated keys
 * from `display_format`) before falling back to nested dot traversal.
 */
function resolveCellValue(item: any, key: string): any {
    if (item && Object.prototype.hasOwnProperty.call(item, key)) {
        return item[key];
    }
    return get(item, key);
}

const ROW_ACTIONS = ['show', 'edit', 'delete'] as const;

export default function ResourceIndex<T extends Record<string, any> = any>({
    data,
    meta,
    filters: currentFilters,
    title,
    description,
    baseRoute,
    exportRoute,
    importRoute,
    importFormatUrl,
    importAccept,
    importMaxSizeMB,
    cellOverrides,
    rowActions,
    headerActions,
}: ResourceIndexProps<T>) {
    const [selectedIds, setSelectedIds] = useState<Set<string | number>>(
        new Set(),
    );
    const [isImportModalOpen, setIsImportModalOpen] = useState(false);
    const [isUploading, setIsUploading] = useState(false);

    // ── Normalized defaults from meta (overridable via props) ─
    const actions = meta.actions ?? [];
    const resourceName =
        title ??
        meta.title ??
        meta.resource_plural ??
        meta.resource_name ??
        'Records';
    const pageDescription = description ?? meta.description ?? undefined;
    const effectiveBaseRoute =
        baseRoute ?? meta.routes?.index ?? meta.base_route;

    const canCreate = actions.includes('create');
    const canShow = actions.includes('show');
    const canEdit = actions.includes('edit');
    const canDelete = actions.includes('delete');
    const canExport = actions.includes('export');
    const canImport = actions.includes('import');

    const effectiveCreateRoute =
        meta.routes?.create ??
        (effectiveBaseRoute ? `${effectiveBaseRoute}/create` : undefined);

    const effectiveExportRoute = canExport
        ? (exportRoute ??
          meta.routes?.export ??
          (effectiveBaseRoute ? `${effectiveBaseRoute}/export` : undefined))
        : undefined;

    const effectiveImportRoute = canImport
        ? (importRoute ??
          meta.routes?.import ??
          meta.import_route ??
          (effectiveBaseRoute ? `${effectiveBaseRoute}/import` : undefined))
        : undefined;

    const effectiveBulkDestroyRoute =
        meta.routes?.bulk_destroy ??
        (effectiveBaseRoute ? `${effectiveBaseRoute}/bulk-destroy` : undefined);

    const effectiveImportFormatUrl =
        importFormatUrl ?? meta.import_format_url ?? undefined;
    const effectiveImportAccept =
        importAccept ?? meta.import_accept ?? '.csv,.xlsx,.xls';
    const effectiveImportMaxSizeMB =
        importMaxSizeMB ?? meta.import_max_size_mb ?? 100;

    // ── Navigation helpers ────────────────────────────────────

    const navigateWithFilters = useCallback(
        (params: Record<string, string>) => {
            const next = { ...currentFilters, ...params };

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

    const handleSearch = useCallback(
        (query: string) => {
            navigateWithFilters({ search: query, page: '1' });
        },
        [navigateWithFilters],
    );

    const handlePerPageChange = useCallback(
        (perPage: string) => {
            navigateWithFilters({ per_page: perPage, page: '1' });
        },
        [navigateWithFilters],
    );

    const handleFilterChange = useCallback(
        (key: string, value: string) => {
            const val = value === 'all' ? '' : value;
            navigateWithFilters({ [key]: val, page: '1' });
        },
        [navigateWithFilters],
    );

    const handleFiltersChange = useCallback(
        (updates: Record<string, string>) => {
            navigateWithFilters({ ...updates, page: '1' });
        },
        [navigateWithFilters],
    );

    const handleFilterReset = useCallback(() => {
        const preserved: Record<string, string> = {};
        if (currentFilters.per_page) preserved.per_page = currentFilters.per_page;
        if (currentFilters.sort) preserved.sort = currentFilters.sort;
        if (currentFilters.direction) preserved.direction = currentFilters.direction;

        router.get(window.location.pathname, preserved, {
            preserveState: false,
            preserveScroll: true,
            replace: true,
        });
    }, [currentFilters]);

    const handleSort = (key: string) => {
        const currentSort = currentFilters.sort;
        const currentDirection = currentFilters.direction;
        const newDirection =
            currentSort === key && currentDirection === 'asc' ? 'desc' : 'asc';
        navigateWithFilters({ sort: key, direction: newDirection });
    };

    // ── Row selection ─────────────────────────────────────────

    const toggleSelect = (id: string | number) => {
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
        if (effectiveBulkDestroyRoute && selectedIds.size > 0) {
            router.post(
                effectiveBulkDestroyRoute,
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

    const hasRowActions =
        Boolean(rowActions) ||
        (Boolean(effectiveBaseRoute) &&
            ROW_ACTIONS.some((a) => actions.includes(a)));

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
                        type="button"
                        className="hover:text-foreground flex items-center"
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
                    ? (item: T, index: number) =>
                          cellOverrides[col.key](item, index)
                    : col.type === 'serial'
                      ? (_item: any, index: number) => (
                            <span className="text-muted-foreground">
                                {(data.current_page - 1) * data.per_page +
                                    index +
                                    1}
                            </span>
                        )
                      : col.type === 'boolean'
                        ? (item: any) => {
                              const val = resolveCellValue(item, col.key);
                              return (
                                  <StatusBadge
                                      status={val ? 'Active' : 'Inactive'}
                                      variant={val ? 'success' : 'danger'}
                                  />
                              );
                          }
                        : col.type === 'badge'
                          ? (item: any) => (
                                <StatusBadge
                                    status={String(
                                        resolveCellValue(item, col.key) ?? '',
                                    )}
                                />
                            )
                          : col.type === 'status'
                            ? (item: any) => {
                                  const sFilter = meta.filters.find(
                                      (f) => f.key === col.key,
                                  );
                                  const val =
                                      resolveCellValue(item, col.key) ?? '';
                                  if (
                                      sFilter &&
                                      sFilter.options &&
                                      canEdit &&
                                      effectiveBaseRoute
                                  ) {
                                      return (
                                          <DropdownMenu>
                                              <DropdownMenuTrigger asChild>
                                                  <Button
                                                      type="button"
                                                      size="sm"
                                                      variant="outline"
                                                      className="capitalize"
                                                  >
                                                      {val}
                                                      <ChevronsUpDown className="ml-auto size-4" />
                                                  </Button>
                                              </DropdownMenuTrigger>
                                              <DropdownMenuContent align="start">
                                                  {sFilter.options.map(
                                                      (opt) => (
                                                          <DropdownMenuItem
                                                              key={opt.value}
                                                              onClick={() => {
                                                                  router.post(
                                                                      `${effectiveBaseRoute}/${item.id}`,
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
                                  return <StatusBadge status={String(val)} />;
                              }
                            : col.type === 'date'
                              ? (item: any) => {
                                    const val = resolveCellValue(item, col.key);
                                    return (
                                        <span className="text-muted-foreground text-sm">
                                            {val
                                                ? new Date(
                                                      val,
                                                  ).toLocaleDateString()
                                                : '—'}
                                        </span>
                                    );
                                }
                              : col.type === 'datetime'
                                ? (item: any) => {
                                      const val = resolveCellValue(
                                          item,
                                          col.key,
                                      );
                                      return (
                                          <span className="text-muted-foreground text-sm">
                                              {val
                                                  ? new Date(
                                                        val,
                                                    ).toLocaleString()
                                                  : '—'}
                                          </span>
                                      );
                                  }
                                : col.type === 'image'
                                  ? (item: any) => {
                                        const val = resolveCellValue(
                                            item,
                                            col.key,
                                        );
                                        if (!val) {
                                            return (
                                                <span className="text-muted-foreground text-xs">
                                                    No Image
                                                </span>
                                            );
                                        }
                                        const src =
                                            typeof val === 'string' &&
                                            (val.startsWith('http://') ||
                                                val.startsWith('https://') ||
                                                val.startsWith('/'))
                                                ? val
                                                : `/storage/${val}`;
                                        return (
                                            <img
                                                src={src}
                                                alt=""
                                                className="h-10 w-10 rounded border object-cover"
                                            />
                                        );
                                    }
                                  : undefined,
            });
        }

        // Action column (only when row actions exist)
        if (hasRowActions) {
            cols.push({
                header: <span className="text-right">Actions</span>,
                className: 'text-right',
                cell: (item: T) => (
                    <div className="flex items-center justify-end gap-1">
                        {rowActions && rowActions(item)}
                        {canShow && effectiveBaseRoute && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() =>
                                    router.get(
                                        `${effectiveBaseRoute}/${item.id}`,
                                    )
                                }
                            >
                                <Eye className="h-4 w-4" />
                            </Button>
                        )}
                        {canEdit && effectiveBaseRoute && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() =>
                                    router.get(
                                        `${effectiveBaseRoute}/${item.id}/edit`,
                                    )
                                }
                            >
                                <Pencil className="h-4 w-4" />
                            </Button>
                        )}
                        {canDelete && effectiveBaseRoute && (
                            <ConfirmDialog
                                trigger={
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                    >
                                        <Trash2 className="text-destructive h-4 w-4" />
                                    </Button>
                                }
                                title={`Delete ${meta.resource_name ?? 'Record'}?`}
                                description="This action cannot be undone."
                                confirmLabel="Delete"
                                onConfirm={() =>
                                    router.delete(
                                        `${effectiveBaseRoute}/${item.id}`,
                                    )
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
    if (canCreate && effectiveCreateRoute) {
        headerActionsList.push({
            label: `Add ${meta.resource_name ?? ''}`.trim(),
            icon: 'add',
            href: effectiveCreateRoute,
        });
    }
    if (canImport && effectiveImportRoute) {
        headerActionsList.push({
            label: 'Import',
            icon: 'import',
            onClick: () => setIsImportModalOpen(true),
            variant: 'outline',
        });
    }

    const handleImportUpload = (file: File) => {
        if (!effectiveImportRoute) return;
        setIsUploading(true);
        const formData = new FormData();
        formData.append('file', file);
        router.post(effectiveImportRoute, formData, {
            onSuccess: () => {
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

    return (
        <>
            <Head title={resourceName} />
            <div className="flex flex-1 flex-col gap-4 p-4 sm:gap-6">
                <PageHeader
                    title={resourceName}
                    description={pageDescription}
                    actions={headerActionsList}
                >
                    {effectiveExportRoute && (
                        <ExportButton
                            href={effectiveExportRoute}
                            params={currentFilters}
                        />
                    )}
                    {headerActions}
                </PageHeader>

                {meta.row_selection && (
                    <BulkActions
                        selectedCount={selectedIds.size}
                        onDelete={
                            canDelete && effectiveBulkDestroyRoute
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
                        first_page_url: data.first_page_url,
                        last_page_url: data.last_page_url,
                        prev_page_url: data.prev_page_url,
                        next_page_url: data.next_page_url,
                        links: data.links,
                    }}
                    onPerPageChange={handlePerPageChange}
                    filters={
                        <FilterBar
                            filters={meta.filters}
                            values={currentFilters}
                            onChange={handleFilterChange}
                            onChangeMany={handleFiltersChange}
                            onReset={handleFilterReset}
                            search={currentFilters.search ?? ''}
                            onSearch={handleSearch}
                            defaultSeparator={meta.default_separator}
                        />
                    }
                />
            </div>

            {effectiveImportRoute && (
                <Dialog
                    open={isImportModalOpen}
                    onOpenChange={setIsImportModalOpen}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Import {resourceName}</DialogTitle>
                            <DialogDescription>
                                Upload a file to batch import{' '}
                                {resourceName.toLowerCase()}.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="py-4">
                            <FileImport
                                onUpload={handleImportUpload}
                                uploading={isUploading}
                                accept={effectiveImportAccept}
                                maxSizeMB={effectiveImportMaxSizeMB}
                                formatDownloadUrl={effectiveImportFormatUrl}
                            />
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </>
    );
}
