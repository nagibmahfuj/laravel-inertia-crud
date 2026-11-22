import { BulkActions } from '@/components/crud/BulkActions';
import { ConfirmDialog } from '@/components/crud/ConfirmDialog';
import { DataTable, type DataTableColumn } from '@/components/crud/DataTable';
import { ExportButton } from '@/components/crud/ExportButton';
import { FileImport } from '@/components/crud/FileImport';
import { FilterBar } from '@/components/crud/FilterBar';
import { PageHeader } from '@/components/crud/PageHeader';
import ResourceForm from '@/components/crud/ResourceForm';
import { StatCard } from '@/components/crud/StatCard';
import { StatusBadge } from '@/components/crud/StatusBadge';
import { resolveLucideIcon } from '@/components/crud/icon-map';
import type {
    ColumnConfig,
    CustomActionConfig,
    FieldConfig,
    ImportProgressState,
    ResourceIndexProps,
    ResourceMeta,
} from '@/components/crud/types';
import { Badge } from '@/components/ui/badge';
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
import { Progress } from '@/components/ui/progress';
import { Head, router } from '@inertiajs/react';
import { get } from 'lodash';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Check,
    ChevronsUpDown,
    Copy,
    Download,
    ExternalLink,
    Eye,
    Loader2,
    Pencil,
    RotateCcw,
    Trash2,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

export type { ColumnConfig, ResourceIndexProps, ResourceMeta };

function resolveCellValue(item: any, key: string): any {
    if (item && Object.prototype.hasOwnProperty.call(item, key)) {
        return item[key];
    }
    return get(item, key);
}

function getCsrfToken(): string {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    if (metaTag) {
        const token = metaTag.getAttribute('content');
        if (token) return token;
    }
    const cookies = document.cookie.split(';');
    for (const cookie of cookies) {
        const [name, ...rest] = cookie.trim().split('=');
        if (name === 'XSRF-TOKEN') {
            return decodeURIComponent(rest.join('='));
        }
    }
    return '';
}

function CopyableCell({ text }: { text: string }) {
    const [copied, setCopied] = useState(false);

    return (
        <span className="inline-flex items-center gap-1 font-mono text-xs">
            <span>{text}</span>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="h-6 w-6"
                title="Copy"
                onClick={() => {
                    if (navigator?.clipboard) {
                        navigator.clipboard.writeText(text).then(() => {
                            setCopied(true);
                            setTimeout(() => setCopied(false), 1500);
                        });
                    }
                }}
            >
                {copied ? (
                    <Check className="h-3 w-3 text-emerald-500" />
                ) : (
                    <Copy className="text-muted-foreground h-3 w-3" />
                )}
            </Button>
        </span>
    );
}

const ROW_ACTIONS = ['show', 'edit', 'delete'] as const;

interface EmbeddedFormState {
    open: boolean;
    loading: boolean;
    mode: 'create' | 'edit';
    fields: FieldConfig[];
    record?: Record<string, any> | null;
    meta?: ResourceMeta;
}

export default function ResourceIndex<T extends Record<string, any> = any>({
    data,
    meta,
    filters: currentFilters,
    stats,
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
    const [importProgress, setImportProgress] =
        useState<ImportProgressState | null>(null);

    const [embeddedForm, setEmbeddedForm] = useState<EmbeddedFormState>({
        open: false,
        loading: false,
        mode: 'create',
        fields: [],
        record: null,
    });

    // ── Normalized defaults from meta (overridable via props) ─
    const actions = meta.actions ?? [];
    const customActions = meta.custom_actions ?? [];
    const rowCustomActions = customActions.filter((a) => a.scope !== 'bulk');
    const bulkCustomActions = customActions.filter((a) => a.scope === 'bulk');

    const resourceName =
        title ??
        meta.title ??
        meta.resource_plural ??
        meta.resource_name ??
        'Records';
    const pageDescription = description ?? meta.description ?? undefined;
    const effectiveBaseRoute =
        baseRoute ?? meta.routes?.index ?? meta.base_route;
    const formMode = meta.form_mode ?? 'page';
    const isEmbeddedFormMode = formMode === 'modal' || formMode === 'drawer';
    const softDeletes = Boolean(meta.soft_deletes);

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
    const effectiveBulkRestoreRoute =
        meta.routes?.bulk_restore ??
        (effectiveBaseRoute ? `${effectiveBaseRoute}/bulk-restore` : undefined);
    const effectiveBulkForceDestroyRoute =
        meta.routes?.bulk_force_destroy ??
        (effectiveBaseRoute
            ? `${effectiveBaseRoute}/bulk-force-destroy`
            : undefined);

    const effectiveImportFormatUrl =
        importFormatUrl ??
        meta.import_format_url ??
        meta.routes?.import_sample ??
        undefined;
    const effectiveImportAccept =
        importAccept ?? meta.import_accept ?? '.csv,.txt';
    const effectiveImportMaxSizeMB =
        importMaxSizeMB ?? meta.import_max_size_mb ?? 100;

    // ── Background import polling ─────────────────────────────
    useEffect(() => {
        if (
            !importProgress ||
            !importProgress.status_url ||
            (importProgress.status !== 'queued' &&
                importProgress.status !== 'processing')
        ) {
            return;
        }

        const timer = setInterval(async () => {
            try {
                const res = await fetch(importProgress.status_url!, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (!res.ok) return;
                const next: ImportProgressState = await res.json();
                setImportProgress(next);

                if (next.status === 'completed') {
                    router.reload({ only: ['data', 'stats'] });
                }
            } catch {
                // Retry on next tick
            }
        }, 2000);

        return () => clearInterval(timer);
    }, [importProgress?.id, importProgress?.status, importProgress?.status_url]);

    // ── Embedded Modal / Drawer form loader ───────────────────
    const openEmbeddedForm = async (
        mode: 'create' | 'edit',
        itemId?: string | number,
    ) => {
        const url =
            mode === 'create'
                ? effectiveCreateRoute
                : effectiveBaseRoute && itemId !== undefined
                  ? `${effectiveBaseRoute}/${itemId}/edit`
                  : undefined;

        if (!url) return;

        setEmbeddedForm({
            open: true,
            loading: true,
            mode,
            fields: [],
            record: null,
        });

        try {
            const res = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Crud-Form': '1',
                },
                credentials: 'same-origin',
            });
            if (!res.ok) {
                setEmbeddedForm((prev) => ({ ...prev, open: false, loading: false }));
                router.get(url);
                return;
            }
            const payload = await res.json();
            setEmbeddedForm({
                open: true,
                loading: false,
                mode: payload.mode ?? mode,
                fields: payload.fields ?? [],
                record: payload.record ?? null,
                meta: payload.meta ?? meta,
            });
        } catch {
            setEmbeddedForm((prev) => ({ ...prev, open: false, loading: false }));
            router.get(url);
        }
    };

    const closeEmbeddedForm = () => {
        setEmbeddedForm((prev) => ({ ...prev, open: false }));
    };

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
        if (currentFilters.per_page)
            preserved.per_page = currentFilters.per_page;
        if (currentFilters.sort) preserved.sort = currentFilters.sort;
        if (currentFilters.direction)
            preserved.direction = currentFilters.direction;

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

    // ── Row selection & bulk actions ──────────────────────────

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

    const handleBulkRestore = () => {
        if (effectiveBulkRestoreRoute && selectedIds.size > 0) {
            router.post(
                effectiveBulkRestoreRoute,
                { ids: Array.from(selectedIds) },
                { onSuccess: () => setSelectedIds(new Set()) },
            );
        }
    };

    const handleBulkForceDelete = () => {
        if (effectiveBulkForceDestroyRoute && selectedIds.size > 0) {
            router.post(
                effectiveBulkForceDestroyRoute,
                { ids: Array.from(selectedIds) },
                { onSuccess: () => setSelectedIds(new Set()) },
            );
        }
    };

    const executeCustomAction = (
        action: CustomActionConfig,
        payload: Record<string, any>,
        itemId?: string | number,
    ) => {
        const resolvedUrl =
            itemId !== undefined
                ? action.url.replace('{id}', String(itemId))
                : action.url;
        const method = action.method ?? 'post';

        if (method === 'get') {
            router.get(resolvedUrl, payload, { preserveScroll: true });
        } else {
            router.visit(resolvedUrl, {
                method: method as any,
                data: payload,
                preserveScroll: true,
                onSuccess: () => {
                    if (action.scope === 'bulk') {
                        setSelectedIds(new Set());
                    }
                },
            });
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
        rowCustomActions.length > 0 ||
        (Boolean(effectiveBaseRoute) &&
            ROW_ACTIONS.some((a) => actions.includes(a)));

    const buildColumns = (): DataTableColumn<T>[] => {
        const cols: DataTableColumn<T>[] = [];

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
                hideable: false,
            });
        }

        // Data columns from config
        for (const col of meta.columns) {
            cols.push({
                columnKey: col.key,
                columnLabel: col.label,
                hideable: col.hideable !== false,
                defaultHidden: Boolean(col.default_hidden),
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
                    : renderBuiltInColumn(col),
            });
        }

        // Action column
        if (hasRowActions) {
            cols.push({
                header: <span className="text-right">Actions</span>,
                className: 'text-right',
                hideable: false,
                cell: (item: T) => {
                    const isTrashed = Boolean((item as any).deleted_at);

                    if (isTrashed && softDeletes && effectiveBaseRoute) {
                        return (
                            <div className="flex items-center justify-end gap-1">
                                <Badge variant="destructive" className="mr-1 text-[10px]">
                                    Trashed
                                </Badge>
                                {canDelete && (
                                    <>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            title="Restore"
                                            onClick={() =>
                                                router.post(
                                                    `${effectiveBaseRoute}/${item.id}/restore`,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <RotateCcw className="h-4 w-4 text-emerald-600" />
                                        </Button>
                                        <ConfirmDialog
                                            trigger={
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Delete Permanently"
                                                >
                                                    <Trash2 className="text-destructive h-4 w-4" />
                                                </Button>
                                            }
                                            title={`Permanently delete ${meta.resource_name ?? 'Record'}?`}
                                            description="This cannot be undone and will delete associated files."
                                            confirmLabel="Delete Permanently"
                                            onConfirm={() =>
                                                router.delete(
                                                    `${effectiveBaseRoute}/${item.id}/force`,
                                                )
                                            }
                                        />
                                    </>
                                )}
                            </div>
                        );
                    }

                    return (
                        <div className="flex items-center justify-end gap-1">
                            {rowActions && rowActions(item)}

                            {rowCustomActions.map((action) => {
                                const Icon = resolveLucideIcon(action.icon);
                                const trigger = (
                                    <Button
                                        key={action.key}
                                        type="button"
                                        variant={action.variant ?? 'ghost'}
                                        size={Icon ? 'icon' : 'sm'}
                                        title={action.label}
                                        onClick={
                                            action.confirm
                                                ? undefined
                                                : () =>
                                                      executeCustomAction(
                                                          action,
                                                          { id: item.id },
                                                          item.id,
                                                      )
                                        }
                                    >
                                        {Icon ? (
                                            <Icon className="h-4 w-4" />
                                        ) : (
                                            action.label
                                        )}
                                    </Button>
                                );

                                if (action.confirm) {
                                    return (
                                        <ConfirmDialog
                                            key={action.key}
                                            trigger={trigger}
                                            title={
                                                action.confirm.title ??
                                                `${action.label}?`
                                            }
                                            description={
                                                action.confirm.description ??
                                                'Are you sure you want to continue?'
                                            }
                                            confirmLabel={
                                                action.confirm.confirmLabel ??
                                                action.label
                                            }
                                            variant={
                                                action.variant === 'destructive'
                                                    ? 'destructive'
                                                    : 'default'
                                            }
                                            onConfirm={() =>
                                                executeCustomAction(
                                                    action,
                                                    { id: item.id },
                                                    item.id,
                                                )
                                            }
                                        />
                                    );
                                }

                                return trigger;
                            })}

                            {canShow && effectiveBaseRoute && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    title="View"
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
                                    title="Edit"
                                    onClick={() => {
                                        if (isEmbeddedFormMode) {
                                            openEmbeddedForm('edit', item.id);
                                        } else {
                                            router.get(
                                                `${effectiveBaseRoute}/${item.id}/edit`,
                                            );
                                        }
                                    }}
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
                                            title="Delete"
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
                    );
                },
            });
        }

        return cols;
    };

    const renderBuiltInColumn = (col: ColumnConfig) => {
        switch (col.type) {
            case 'serial':
                return (_item: any, index: number) => (
                    <span className="text-muted-foreground">
                        {(data.current_page - 1) * data.per_page + index + 1}
                    </span>
                );

            case 'boolean':
                return (item: any) => {
                    const val = resolveCellValue(item, col.key);
                    return (
                        <StatusBadge
                            status={val ? 'Active' : 'Inactive'}
                            variant={val ? 'success' : 'danger'}
                        />
                    );
                };

            case 'toggle':
                return (item: any) => {
                    const val = Boolean(resolveCellValue(item, col.key));
                    if (!canEdit || !effectiveBaseRoute) {
                        return (
                            <StatusBadge
                                status={val ? 'Active' : 'Inactive'}
                                variant={val ? 'success' : 'danger'}
                            />
                        );
                    }
                    return (
                        <Checkbox
                            checked={val}
                            onCheckedChange={(checked) => {
                                router.post(
                                    `${effectiveBaseRoute}/${item.id}`,
                                    {
                                        _method: 'patch',
                                        [col.key]: Boolean(checked),
                                    },
                                    { preserveScroll: true },
                                );
                            }}
                        />
                    );
                };

            case 'badge':
                return (item: any) => (
                    <StatusBadge
                        status={String(resolveCellValue(item, col.key) ?? '')}
                    />
                );

            case 'status':
                return (item: any) => {
                    const sFilter = meta.filters.find((f) => f.key === col.key);
                    const val = resolveCellValue(item, col.key) ?? '';
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
                                    {sFilter.options.map((opt) => (
                                        <DropdownMenuItem
                                            key={opt.value}
                                            onClick={() => {
                                                router.post(
                                                    `${effectiveBaseRoute}/${item.id}`,
                                                    {
                                                        _method: 'patch',
                                                        [col.key]: opt.value,
                                                    },
                                                    { preserveScroll: true },
                                                );
                                            }}
                                        >
                                            {opt.label}
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        );
                    }
                    return <StatusBadge status={String(val)} />;
                };

            case 'copyable':
                return (item: any) => {
                    const val = resolveCellValue(item, col.key);
                    return val ? (
                        <CopyableCell text={String(val)} />
                    ) : (
                        <span className="text-muted-foreground">—</span>
                    );
                };

            case 'currency':
                return (item: any) => {
                    const val = resolveCellValue(item, col.key);
                    if (val === null || val === undefined || val === '') {
                        return <span className="text-muted-foreground">—</span>;
                    }
                    const num = Number(val);
                    if (isNaN(num)) return <span>{String(val)}</span>;
                    try {
                        return (
                            <span className="font-mono text-sm font-medium">
                                {new Intl.NumberFormat(
                                    col.locale || undefined,
                                    {
                                        style: 'currency',
                                        currency: col.currency || 'USD',
                                    },
                                ).format(num)}
                            </span>
                        );
                    } catch {
                        return (
                            <span className="font-mono text-sm">
                                {num.toFixed(2)}
                            </span>
                        );
                    }
                };

            case 'link':
                return (item: any) => {
                    const val = resolveCellValue(item, col.key);
                    if (!val) return <span className="text-muted-foreground">—</span>;
                    const href = String(val);
                    const isExternal =
                        href.startsWith('http://') ||
                        href.startsWith('https://');
                    return (
                        <a
                            href={href}
                            target={isExternal ? '_blank' : undefined}
                            rel={isExternal ? 'noopener noreferrer' : undefined}
                            className="text-primary inline-flex max-w-[200px] items-center gap-1 truncate text-sm hover:underline"
                        >
                            <span className="truncate">{href}</span>
                            {isExternal && (
                                <ExternalLink className="h-3 w-3 shrink-0" />
                            )}
                        </a>
                    );
                };

            case 'tags':
                return (item: any) => {
                    const val = resolveCellValue(item, col.key);
                    const tags = Array.isArray(val)
                        ? val
                        : typeof val === 'string' && val.trim() !== ''
                          ? val
                                .split(',')
                                .map((s) => s.trim())
                                .filter(Boolean)
                          : [];
                    if (tags.length === 0) {
                        return <span className="text-muted-foreground">—</span>;
                    }
                    return (
                        <div className="flex flex-wrap gap-1">
                            {tags.slice(0, 3).map((tag, idx) => (
                                <Badge
                                    key={idx}
                                    variant="secondary"
                                    className="text-xs font-normal"
                                >
                                    {String(tag)}
                                </Badge>
                            ))}
                            {tags.length > 3 && (
                                <Badge variant="outline" className="text-xs">
                                    +{tags.length - 3}
                                </Badge>
                            )}
                        </div>
                    );
                };

            case 'truncate':
                return (item: any) => {
                    const val = resolveCellValue(item, col.key);
                    if (!val) return <span className="text-muted-foreground">—</span>;
                    const str = String(val);
                    const limit = col.max_length ?? 40;
                    const truncated =
                        str.length > limit ? `${str.slice(0, limit)}…` : str;
                    return (
                        <span title={str} className="text-sm">
                            {truncated}
                        </span>
                    );
                };

            case 'date':
                return (item: any) => {
                    const val = resolveCellValue(item, col.key);
                    return (
                        <span className="text-muted-foreground text-sm">
                            {val ? new Date(val).toLocaleDateString() : '—'}
                        </span>
                    );
                };

            case 'datetime':
                return (item: any) => {
                    const val = resolveCellValue(item, col.key);
                    return (
                        <span className="text-muted-foreground text-sm">
                            {val ? new Date(val).toLocaleString() : '—'}
                        </span>
                    );
                };

            case 'image':
                return (item: any) => {
                    const val = resolveCellValue(item, col.key);
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
                };

            default:
                return undefined;
        }
    };

    // ── Page header actions ───────────────────────────────────

    const headerActionsList: any[] = [];
    if (canCreate && effectiveCreateRoute) {
        headerActionsList.push(
            isEmbeddedFormMode
                ? {
                      label: `Add ${meta.resource_name ?? ''}`.trim(),
                      icon: 'add',
                      onClick: () => openEmbeddedForm('create'),
                  }
                : {
                      label: `Add ${meta.resource_name ?? ''}`.trim(),
                      icon: 'add',
                      href: effectiveCreateRoute,
                  },
        );
    }
    if (canImport && effectiveImportRoute) {
        headerActionsList.push({
            label: 'Import',
            icon: 'import',
            onClick: () => setIsImportModalOpen(true),
            variant: 'outline',
        });
    }

    const submitImportPayload = async (formData: FormData) => {
        if (!effectiveImportRoute) return;
        setIsUploading(true);
        const csrfToken = getCsrfToken();

        try {
            const res = await fetch(effectiveImportRoute, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    ...(csrfToken
                        ? {
                              'X-CSRF-TOKEN': csrfToken,
                              'X-XSRF-TOKEN': csrfToken,
                          }
                        : {}),
                },
                body: formData,
                credentials: 'same-origin',
            });

            if (res.ok) {
                const json = await res.json();
                if (json?.status) {
                    setImportProgress(json.status);
                    if (json.status.status === 'completed') {
                        router.reload({ only: ['data', 'stats'] });
                    }
                    setIsUploading(false);
                    return;
                }
            }

            // Fallback to standard Inertia POST for custom import() overrides returning redirects
            router.post(effectiveImportRoute, formData, {
                onSuccess: () => {
                    setIsImportModalOpen(false);
                    setIsUploading(false);
                },
                onError: () => setIsUploading(false),
                onFinish: () => setIsUploading(false),
            });
        } catch {
            setIsUploading(false);
        }
    };

    const handleImportUpload = (file: File) => {
        const formData = new FormData();
        formData.append('file', file);
        submitImportPayload(formData);
    };

    const handleImportToken = (token: string, fileName: string) => {
        const formData = new FormData();
        formData.append('chunk_token', token);
        formData.append('file_name', fileName);
        submitImportPayload(formData);
    };

    const viewingTrashed =
        softDeletes &&
        (currentFilters.trashed === 'only' ||
            currentFilters.trashed === 'with');

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

                {/* KPI Stats Row */}
                {stats && stats.length > 0 && (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {stats.map((stat, idx) => (
                            <StatCard
                                key={`${stat.title}-${idx}`}
                                title={stat.title}
                                value={stat.value}
                                change={stat.change}
                                description={stat.description}
                                icon={resolveLucideIcon(stat.icon)}
                            />
                        ))}
                    </div>
                )}

                {/* Background Import Progress Banner when dialog is closed */}
                {importProgress && !isImportModalOpen && (
                    <div className="bg-muted/40 flex flex-col gap-2 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex flex-1 items-center gap-3">
                            {(importProgress.status === 'queued' ||
                                importProgress.status === 'processing') && (
                                <Loader2 className="text-primary h-4 w-4 animate-spin shrink-0" />
                            )}
                            <div className="min-w-0 flex-1 space-y-1">
                                <div className="flex items-center gap-2 text-xs font-medium">
                                    <span className="truncate">
                                        Import ({importProgress.file_name}):{' '}
                                        <span className="capitalize">
                                            {importProgress.status}
                                        </span>
                                    </span>
                                    <span className="text-muted-foreground font-mono">
                                        {importProgress.progress}% (
                                        {importProgress.successful_rows} ok
                                        {importProgress.failed_rows > 0
                                            ? `, ${importProgress.failed_rows} failed`
                                            : ''}
                                        )
                                    </span>
                                </div>
                                <Progress
                                    value={importProgress.progress}
                                    className="h-1.5"
                                />
                            </div>
                        </div>
                        <div className="flex items-center gap-1.5">
                            {importProgress.error_report_url && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    asChild
                                    className="h-7 text-xs"
                                >
                                    <a
                                        href={importProgress.error_report_url}
                                        download
                                    >
                                        <Download className="mr-1 h-3 w-3" />
                                        Errors CSV
                                    </a>
                                </Button>
                            )}
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-7 text-xs"
                                onClick={() => setIsImportModalOpen(true)}
                            >
                                Details
                            </Button>
                            {importProgress.status !== 'queued' &&
                                importProgress.status !== 'processing' && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="h-7 w-7"
                                        onClick={() => setImportProgress(null)}
                                    >
                                        <X className="h-3.5 w-3.5" />
                                    </Button>
                                )}
                        </div>
                    </div>
                )}

                {meta.row_selection && (
                    <BulkActions
                        selectedCount={selectedIds.size}
                        onDelete={
                            canDelete &&
                            effectiveBulkDestroyRoute &&
                            currentFilters.trashed !== 'only'
                                ? handleBulkDelete
                                : undefined
                        }
                        onRestore={
                            canDelete &&
                            viewingTrashed &&
                            effectiveBulkRestoreRoute
                                ? handleBulkRestore
                                : undefined
                        }
                        onForceDelete={
                            canDelete &&
                            viewingTrashed &&
                            effectiveBulkForceDestroyRoute
                                ? handleBulkForceDelete
                                : undefined
                        }
                        customActions={bulkCustomActions}
                        onCustomAction={(action) =>
                            executeCustomAction(action, {
                                ids: Array.from(selectedIds),
                            })
                        }
                    />
                )}

                <DataTable
                    data={data.data}
                    columns={buildColumns()}
                    storageKey={meta.route_prefix}
                    rowClassName={(item: any) =>
                        item?.deleted_at ? 'opacity-65 bg-destructive/5' : undefined
                    }
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
                            softDeletes={softDeletes}
                        />
                    }
                />
            </div>

            {/* Import Dialog */}
            {effectiveImportRoute && (
                <Dialog
                    open={isImportModalOpen}
                    onOpenChange={setIsImportModalOpen}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Import {resourceName}</DialogTitle>
                            <DialogDescription>
                                Upload a CSV file to batch import{' '}
                                {resourceName.toLowerCase()}.
                                {meta.import_mode !== 'sync' &&
                                    ' Large files are processed automatically in the background.'}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="py-4">
                            <FileImport
                                onUpload={handleImportUpload}
                                onUploadToken={handleImportToken}
                                uploading={isUploading}
                                accept={effectiveImportAccept}
                                maxSizeMB={effectiveImportMaxSizeMB}
                                formatDownloadUrl={effectiveImportFormatUrl}
                                chunked={Boolean(meta.import_chunked)}
                                chunkEndpoint={
                                    meta.routes?.chunk_upload ?? undefined
                                }
                                importProgress={importProgress}
                                onResetProgress={() => setImportProgress(null)}
                            />
                        </div>
                    </DialogContent>
                </Dialog>
            )}

            {/* Embedded Modal Form Mode */}
            {formMode === 'modal' && (
                <Dialog
                    open={embeddedForm.open}
                    onOpenChange={(open) =>
                        setEmbeddedForm((prev) => ({ ...prev, open }))
                    }
                >
                    <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle>
                                {embeddedForm.meta?.title ??
                                    `${embeddedForm.mode === 'create' ? 'Create' : 'Edit'} ${meta.resource_name}`}
                            </DialogTitle>
                        </DialogHeader>
                        {embeddedForm.loading ? (
                            <div className="flex items-center justify-center py-12">
                                <Loader2 className="text-primary h-6 w-6 animate-spin" />
                            </div>
                        ) : (
                            <ResourceForm
                                meta={embeddedForm.meta ?? meta}
                                fields={embeddedForm.fields}
                                record={embeddedForm.record}
                                mode={embeddedForm.mode}
                                embedded
                                onSuccess={closeEmbeddedForm}
                                onCancel={closeEmbeddedForm}
                            />
                        )}
                    </DialogContent>
                </Dialog>
            )}

            {/* Embedded Slide-Over Drawer Form Mode */}
            {formMode === 'drawer' && embeddedForm.open && (
                <div className="fixed inset-0 z-50 flex justify-end">
                    <div
                        className="fixed inset-0 bg-black/50 backdrop-blur-xs"
                        onClick={closeEmbeddedForm}
                    />
                    <div className="bg-background relative z-10 flex h-full w-full max-w-xl flex-col border-l shadow-xl">
                        <div className="flex items-center justify-between border-b px-6 py-4">
                            <h3 className="text-lg font-semibold">
                                {embeddedForm.meta?.title ??
                                    `${embeddedForm.mode === 'create' ? 'Create' : 'Edit'} ${meta.resource_name}`}
                            </h3>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={closeEmbeddedForm}
                            >
                                <X className="h-4 w-4" />
                            </Button>
                        </div>
                        <div className="flex-1 overflow-y-auto p-6">
                            {embeddedForm.loading ? (
                                <div className="flex items-center justify-center py-12">
                                    <Loader2 className="text-primary h-6 w-6 animate-spin" />
                                </div>
                            ) : (
                                <ResourceForm
                                    meta={embeddedForm.meta ?? meta}
                                    fields={embeddedForm.fields}
                                    record={embeddedForm.record}
                                    mode={embeddedForm.mode}
                                    embedded
                                    onSuccess={closeEmbeddedForm}
                                    onCancel={closeEmbeddedForm}
                                />
                            )}
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
