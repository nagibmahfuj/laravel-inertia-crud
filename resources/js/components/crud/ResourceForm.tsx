import React, { useCallback, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';

import {
    FormField,
    formatDateInput,
    formatDateTimeLocal,
} from '@/components/crud/FormField';
import type { ResourceFormProps } from '@/components/crud/types';
import { Button } from '@/components/ui/button';

export type { ResourceFormProps };

export default function ResourceForm({
    meta,
    fields,
    record,
    mode,
    title,
    submitRoute,
    indexRoute,
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
        if (field.type === 'repeater') {
            initialData[field.key] = record?.[field.key] ?? field.default ?? [];
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
        } else if (field.type === 'chunked-file') {
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

        if (mode === 'create') {
            post(effectiveSubmitRoute);
        } else if (mode === 'edit') {
            if (hasFiles) {
                // Laravel requires POST with _method=put for multipart file uploads
                transform((currentData) => ({
                    ...currentData,
                    _method: 'put',
                }));
                post(effectiveSubmitRoute);
            } else {
                put(effectiveSubmitRoute);
            }
        }
    };

    const handleFieldChange = (key: string, value: any) => {
        setData(key as any, value);
    };

    const handleCancel = () => {
        if (effectiveIndexRoute) {
            router.visit(effectiveIndexRoute);
        } else {
            window.history.back();
        }
    };

    return (
        <>
            <Head title={pageTitle} />
            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden border bg-white shadow-xs sm:rounded-lg dark:bg-zinc-900">
                        <div className="p-6">
                            <h2 className="mb-6 text-lg font-semibold">
                                {pageTitle}
                            </h2>
                            <form onSubmit={handleSubmit} className="space-y-6">
                                {fields.map((field) => (
                                    <FormField
                                        key={field.key}
                                        field={field}
                                        value={data[field.key]}
                                        error={
                                            errors[
                                                field.key as keyof typeof errors
                                            ]
                                        }
                                        errors={errors}
                                        allValues={data}
                                        onChange={handleFieldChange}
                                        onUploadingChange={
                                            handleUploadingChange
                                        }
                                    />
                                ))}

                                {children}

                                <div className="flex flex-col justify-between gap-3 pt-2 sm:flex-row sm:items-center">
                                    {isUploading ? (
                                        <p className="flex animate-pulse items-center gap-1.5 text-xs font-medium text-amber-600 dark:text-amber-400">
                                            <span className="inline-block h-2 w-2 rounded-full bg-amber-500" />
                                            File upload in progress. Please wait
                                            for upload to complete before
                                            saving.
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
                                        <Button
                                            type="submit"
                                            disabled={processing || isUploading}
                                        >
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
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
