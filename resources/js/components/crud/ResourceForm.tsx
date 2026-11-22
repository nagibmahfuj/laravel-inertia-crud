import React, { useCallback, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';

import {
    FormField,
    evaluateVisibleWhen,
    formatDateInput,
    formatDateTimeLocal,
} from '@/components/crud/FormField';
import type { FieldConfig, ResourceFormProps } from '@/components/crud/types';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

export type { ResourceFormProps };

export default function ResourceForm({
    meta,
    fields,
    record,
    mode,
    title,
    submitRoute,
    indexRoute,
    embedded = false,
    onSuccess,
    onCancel,
    children,
}: ResourceFormProps) {
    const resourceName = meta.resource_name ?? 'Record';
    const pageTitle =
        title ??
        meta.title ??
        `${mode === 'create' ? 'Create' : 'Edit'} ${resourceName}`;

    const effectiveIndexRoute =
        indexRoute ?? meta.routes?.index ?? meta.base_route;
    const effectiveSubmitRoute =
        submitRoute ??
        (mode === 'edit'
            ? (meta.routes?.update ??
              (effectiveIndexRoute && record?.id !== undefined
                  ? `${effectiveIndexRoute}/${record.id}`
                  : undefined))
            : (meta.routes?.store ?? effectiveIndexRoute));

    // Build initial form data from fields + record
    const initialData: Record<string, any> = {};
    for (const field of fields) {
        if (field.type === 'repeater' || field.type === 'tags') {
            initialData[field.key] = record?.[field.key] ?? field.default ?? [];
        } else if (field.type === 'key-value') {
            initialData[field.key] = record?.[field.key] ?? field.default ?? {};
        } else if (field.type === 'datetime') {
            const rawVal = record?.[field.key] ?? field.default ?? '';
            initialData[field.key] = formatDateTimeLocal(rawVal);
        } else if (field.type === 'date') {
            const rawVal = record?.[field.key] ?? field.default ?? '';
            initialData[field.key] = formatDateInput(rawVal);
        } else if (field.type === 'checkbox' || field.type === 'toggle') {
            const rawVal = record?.[field.key] ?? field.default ?? false;
            initialData[field.key] = Boolean(
                rawVal && rawVal !== '0' && rawVal !== 'false',
            );
        } else if (
            field.type === 'chunked-file' ||
            field.type === 'file' ||
            field.type === 'image'
        ) {
            initialData[field.key] = null;
        } else {
            initialData[field.key] = record?.[field.key] ?? field.default ?? '';
        }
    }

    const { data, setData, post, put, processing, errors, transform } =
        useForm(initialData);

    // Track concurrent chunked uploads by field key
    const [uploadingFields, setUploadingFields] = useState<Set<string>>(
        () => new Set(),
    );
    const isUploading = uploadingFields.size > 0;

    const handleUploadingChange = useCallback(
        (fieldKey: string, uploading: boolean) => {
            setUploadingFields((prev) => {
                const next = new Set(prev);
                if (uploading) {
                    next.add(fieldKey);
                } else {
                    next.delete(fieldKey);
                }
                return next;
            });
        },
        [],
    );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isUploading || !effectiveSubmitRoute) {
            return;
        }

        const hasFiles = Object.values(data).some(
            (v) => v instanceof File || v instanceof Blob,
        );

        const visitOptions = {
            preserveScroll: embedded,
            onSuccess: () => {
                onSuccess?.();
            },
        };

        if (mode === 'create') {
            post(effectiveSubmitRoute, visitOptions);
        } else if (mode === 'edit') {
            if (hasFiles) {
                transform((currentData) => ({
                    ...currentData,
                    _method: 'put',
                }));
                post(effectiveSubmitRoute, visitOptions);
            } else {
                put(effectiveSubmitRoute, visitOptions);
            }
        }
    };

    const handleFieldChange = (key: string, value: any) => {
        setData(key as any, value);
    };

    const handleCancel = () => {
        if (onCancel) {
            onCancel();
            return;
        }
        if (effectiveIndexRoute) {
            router.visit(effectiveIndexRoute);
        } else {
            window.history.back();
        }
    };

    const useTwoColGrid =
        meta.form_columns === 2 || fields.some((f) => f.col_span !== undefined);
    const hasSections = fields.some((f) => Boolean(f.section));

    const renderFieldGrid = (
        sectionFields: FieldConfig[],
        forceCols?: 1 | 2,
    ) => {
        const isTwoCol =
            forceCols === 2 ||
            (forceCols === undefined &&
                (useTwoColGrid ||
                    sectionFields.some((f) => f.col_span !== undefined)));

        return (
            <div
                className={cn(
                    isTwoCol
                        ? 'grid grid-cols-1 gap-5 md:grid-cols-2'
                        : 'space-y-6',
                )}
            >
                {sectionFields.map((field) => {
                    if (
                        field.type === 'hidden' ||
                        !evaluateVisibleWhen(field.visible_when, data)
                    ) {
                        return null;
                    }
                    return (
                        <div
                            key={field.key}
                            className={
                                isTwoCol && field.col_span === 2
                                    ? 'md:col-span-2'
                                    : undefined
                            }
                        >
                            <FormField
                                field={field}
                                value={data[field.key]}
                                error={
                                    errors[field.key as keyof typeof errors]
                                }
                                errors={errors}
                                allValues={data}
                                onChange={handleFieldChange}
                                onUploadingChange={handleUploadingChange}
                            />
                        </div>
                    );
                })}
            </div>
        );
    };

    // Build section buckets when sections are configured
    const sectionGroups: { key: string; title: string; description?: string; columns?: 1 | 2; fields: FieldConfig[] }[] =
        [];

    if (hasSections) {
        const bucketMap = new Map<string, FieldConfig[]>();
        for (const field of fields) {
            const secKey = field.section || 'General';
            if (!bucketMap.has(secKey)) bucketMap.set(secKey, []);
            bucketMap.get(secKey)!.push(field);
        }
        bucketMap.forEach((secFields, secKey) => {
            const secCfg = meta.form_sections?.[secKey];
            sectionGroups.push({
                key: secKey,
                title: secCfg?.title ?? secKey,
                description: secCfg?.description,
                columns: secCfg?.columns,
                fields: secFields,
            });
        });
    }

    const formBody = (
        <form onSubmit={handleSubmit} className="space-y-6">
            {hasSections ? (
                <div className="space-y-6">
                    {sectionGroups.map((sec) => (
                        <Card key={sec.key}>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base">
                                    {sec.title}
                                </CardTitle>
                                {sec.description && (
                                    <CardDescription>
                                        {sec.description}
                                    </CardDescription>
                                )}
                            </CardHeader>
                            <CardContent>
                                {renderFieldGrid(sec.fields, sec.columns)}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            ) : (
                renderFieldGrid(fields)
            )}

            {children}

            <div className="flex flex-col justify-between gap-3 pt-2 sm:flex-row sm:items-center">
                {isUploading ? (
                    <p className="flex animate-pulse items-center gap-1.5 text-xs font-medium text-amber-600 dark:text-amber-400">
                        <span className="inline-block h-2 w-2 rounded-full bg-amber-500" />
                        File upload in progress. Please wait for upload to
                        complete before saving.
                    </p>
                ) : (
                    <div />
                )}

                <div className="flex justify-end gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={isUploading}
                        onClick={handleCancel}
                    >
                        Cancel
                    </Button>
                    <Button type="submit" disabled={processing || isUploading}>
                        {isUploading
                            ? 'Uploading...'
                            : processing
                              ? 'Saving...'
                              : mode === 'create'
                                ? `Create ${resourceName}`
                                : `Update ${resourceName}`}
                    </Button>
                </div>
            </div>
        </form>
    );

    if (embedded) {
        return formBody;
    }

    return (
        <>
            <Head title={pageTitle} />
            <div className="py-12">
                <div
                    className={cn(
                        'mx-auto sm:px-6 lg:px-8',
                        useTwoColGrid ? 'max-w-5xl' : 'max-w-3xl',
                    )}
                >
                    {hasSections ? (
                        <div className="space-y-6">
                            <h2 className="text-xl font-semibold tracking-tight">
                                {pageTitle}
                            </h2>
                            {formBody}
                        </div>
                    ) : (
                        <div className="overflow-hidden border bg-white shadow-xs sm:rounded-lg dark:bg-zinc-900">
                            <div className="p-6">
                                <h2 className="mb-6 text-lg font-semibold">
                                    {pageTitle}
                                </h2>
                                {formBody}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
