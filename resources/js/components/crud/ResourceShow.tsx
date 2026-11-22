import { ConfirmDialog } from '@/components/crud/ConfirmDialog';
import { StatusBadge } from '@/components/crud/StatusBadge';
import type { ResourceShowProps, ShowFieldConfig } from '@/components/crud/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, Link, router } from '@inertiajs/react';
import { get } from 'lodash';
import {
    ArrowLeft,
    Check,
    Copy,
    Download,
    ExternalLink,
    Pencil,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';

export type { ResourceShowProps };

function CopyValueButton({ text }: { text: string }) {
    const [copied, setCopied] = useState(false);

    const handleCopy = () => {
        if (typeof navigator !== 'undefined' && navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                setCopied(true);
                setTimeout(() => setCopied(false), 1500);
            });
        }
    };

    return (
        <span className="inline-flex items-center gap-1.5 font-mono text-sm">
            <span>{text}</span>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="h-6 w-6"
                onClick={handleCopy}
                title="Copy to clipboard"
            >
                {copied ? (
                    <Check className="h-3.5 w-3.5 text-emerald-500" />
                ) : (
                    <Copy className="text-muted-foreground h-3.5 w-3.5" />
                )}
            </Button>
        </span>
    );
}

function renderShowValue(field: ShowFieldConfig) {
    const val = field.value;

    if (val === null || val === undefined || val === '') {
        return <span className="text-muted-foreground">—</span>;
    }

    switch (field.type) {
        case 'boolean':
        case 'toggle':
        case 'checkbox':
            return (
                <StatusBadge
                    status={val ? 'Active' : 'Inactive'}
                    variant={val ? 'success' : 'danger'}
                />
            );

        case 'badge':
        case 'status':
            return <StatusBadge status={String(val)} />;

        case 'date':
            return <span>{new Date(val).toLocaleDateString()}</span>;

        case 'datetime':
            return <span>{new Date(val).toLocaleString()}</span>;

        case 'currency': {
            const num = Number(val);
            if (isNaN(num)) return <span>{String(val)}</span>;
            try {
                return (
                    <span className="font-mono font-medium">
                        {new Intl.NumberFormat(field.locale || undefined, {
                            style: 'currency',
                            currency: field.currency || 'USD',
                        }).format(num)}
                    </span>
                );
            } catch {
                return <span className="font-mono">{num.toFixed(2)}</span>;
            }
        }

        case 'copyable':
            return <CopyValueButton text={String(val)} />;

        case 'link': {
            const href = String(val);
            const isExternal =
                href.startsWith('http://') || href.startsWith('https://');
            return (
                <a
                    href={href}
                    target={isExternal ? '_blank' : undefined}
                    rel={isExternal ? 'noopener noreferrer' : undefined}
                    className="text-primary inline-flex items-center gap-1 hover:underline"
                >
                    <span className="break-all">{href}</span>
                    {isExternal && <ExternalLink className="h-3.5 w-3.5 shrink-0" />}
                </a>
            );
        }

        case 'tags': {
            const tags = Array.isArray(val)
                ? val
                : String(val)
                      .split(',')
                      .map((s) => s.trim())
                      .filter(Boolean);
            return (
                <div className="flex flex-wrap gap-1">
                    {tags.map((tag, i) => (
                        <Badge key={i} variant="secondary">
                            {String(tag)}
                        </Badge>
                    ))}
                </div>
            );
        }

        case 'image': {
            const str = String(val);
            const src =
                str.startsWith('http://') ||
                str.startsWith('https://') ||
                str.startsWith('/')
                    ? str
                    : `/storage/${str}`;
            return (
                <a href={src} target="_blank" rel="noopener noreferrer">
                    <img
                        src={src}
                        alt={field.label}
                        className="max-h-48 rounded-lg border object-cover"
                    />
                </a>
            );
        }

        case 'file':
        case 'chunked-file': {
            const str = String(val);
            const href =
                str.startsWith('http://') ||
                str.startsWith('https://') ||
                str.startsWith('/')
                    ? str
                    : `/storage/${str}`;
            return (
                <Button type="button" variant="outline" size="sm" asChild>
                    <a href={href} target="_blank" rel="noopener noreferrer">
                        <Download className="mr-2 h-4 w-4" />
                        {str.split('/').pop() || 'Download File'}
                    </a>
                </Button>
            );
        }

        case 'key-value':
            if (typeof val === 'object') {
                return (
                    <div className="bg-muted/30 space-y-1 rounded-md border p-3 font-mono text-xs">
                        {Object.entries(val).map(([k, v]) => (
                            <div key={k} className="flex gap-2">
                                <span className="text-muted-foreground font-semibold">
                                    {k}:
                                </span>
                                <span>{String(v ?? '')}</span>
                            </div>
                        ))}
                    </div>
                );
            }
            return <span>{String(val)}</span>;

        case 'textarea':
        case 'rich-text':
            return (
                <div className="whitespace-pre-wrap text-sm leading-relaxed">
                    {String(val)}
                </div>
            );

        default:
            if (typeof val === 'object') {
                return (
                    <pre className="bg-muted/30 overflow-x-auto rounded-md border p-3 font-mono text-xs">
                        {JSON.stringify(val, null, 2)}
                    </pre>
                );
            }
            return <span className="break-words">{String(val)}</span>;
    }
}

export default function ResourceShow<T extends Record<string, any> = Record<string, any>>({
    meta,
    record,
    showFields,
    title,
    description,
    baseRoute,
    fieldOverrides,
    headerActions,
    children,
}: ResourceShowProps<T>) {
    const resourceName = meta.resource_name ?? 'Record';
    const pageTitle = title ?? meta.title ?? `${resourceName} Details`;
    const pageDescription = description ?? meta.description ?? undefined;
    const effectiveBaseRoute =
        baseRoute ?? meta.routes?.index ?? meta.base_route;
    const actions = meta.actions ?? [];
    const canEdit = actions.includes('edit') && Boolean(effectiveBaseRoute);
    const canDelete = actions.includes('delete') && Boolean(effectiveBaseRoute);

    // Fall back to columns if showFields wasn't provided explicitly
    const fields: ShowFieldConfig[] =
        showFields && showFields.length > 0
            ? showFields
            : (meta.columns ?? []).map((col) => ({
                  key: col.key,
                  label: col.label,
                  type: col.type ?? 'text',
                  value: get(record, col.key),
                  currency: col.currency,
                  locale: col.locale,
              }));

    // Group fields by section
    const hasSections = fields.some((f) => Boolean(f.section));
    const groupedSections: { title: string; fields: ShowFieldConfig[] }[] = [];

    if (hasSections) {
        const map = new Map<string, ShowFieldConfig[]>();
        for (const f of fields) {
            const sec = f.section || 'Details';
            if (!map.has(sec)) map.set(sec, []);
            map.get(sec)!.push(f);
        }
        map.forEach((items, secTitle) => {
            groupedSections.push({ title: secTitle, fields: items });
        });
    } else {
        groupedSections.push({ title: `${resourceName} Information`, fields });
    }

    const isTrashed = Boolean((record as any)?.deleted_at);

    return (
        <>
            <Head title={pageTitle} />
            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 sm:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        {effectiveBaseRoute && (
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                asChild
                            >
                                <Link href={effectiveBaseRoute}>
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                            </Button>
                        )}
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-semibold tracking-tight">
                                    {pageTitle}
                                </h1>
                                {isTrashed && (
                                    <Badge variant="destructive">Deleted</Badge>
                                )}
                            </div>
                            {pageDescription && (
                                <p className="text-muted-foreground text-sm">
                                    {pageDescription}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {headerActions}
                        {canEdit && !isTrashed && (
                            <Button type="button" variant="outline" size="sm" asChild>
                                <Link
                                    href={`${effectiveBaseRoute}/${record.id}/edit`}
                                >
                                    <Pencil className="mr-2 h-4 w-4" />
                                    Edit
                                </Link>
                            </Button>
                        )}
                        {canDelete && !isTrashed && (
                            <ConfirmDialog
                                trigger={
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                    >
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Delete
                                    </Button>
                                }
                                title={`Delete ${resourceName}?`}
                                description="This action cannot be undone."
                                confirmLabel="Delete"
                                onConfirm={() =>
                                    router.delete(
                                        `${effectiveBaseRoute}/${record.id}`,
                                    )
                                }
                            />
                        )}
                    </div>
                </div>

                {groupedSections.map((section) => (
                    <Card key={section.title}>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">
                                {section.title}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                                {section.fields.map((field) => (
                                    <div
                                        key={field.key}
                                        className={
                                            field.col_span === 2
                                                ? 'sm:col-span-2'
                                                : undefined
                                        }
                                    >
                                        <dt className="text-muted-foreground mb-1 text-xs font-medium tracking-wide uppercase">
                                            {field.label}
                                        </dt>
                                        <dd className="text-foreground text-sm">
                                            {fieldOverrides?.[field.key]
                                                ? fieldOverrides[field.key](
                                                      field.value,
                                                      record,
                                                  )
                                                : renderShowValue(field)}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </CardContent>
                    </Card>
                ))}

                {children}
            </div>
        </>
    );
}

