import { Head, useForm } from '@inertiajs/react';

import { FormField, type FieldConfig } from '@/components/crud/FormField';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';

interface ResourceFormProps {
    meta: {
        resource_name: string;
    };
    fields: FieldConfig[];
    record?: Record<string, any>;
    mode: 'create' | 'edit';
    // Customization
    title?: string;
    breadcrumbs?: BreadcrumbItem[];
    submitRoute?: string;
    indexRoute?: string;
    // Extra form fields not in config
    children?: React.ReactNode;
}

export default function ResourceForm({
    meta,
    fields,
    record,
    mode,
    title,
    breadcrumbs,
    submitRoute,
    indexRoute,
    children,
}: ResourceFormProps) {
    const resourceName = meta.resource_name ?? 'Record';
    const pageTitle =
        title ?? `${mode === 'create' ? 'Create' : 'Edit'} ${resourceName}`;

    // Build initial form data from fields + record
    const initialData: Record<string, any> = {};
    for (const field of fields) {
        if (field.type === 'repeater') {
            initialData[field.key] = record?.[field.key] ?? field.default ?? [];
        } else {
            initialData[field.key] = record?.[field.key] ?? field.default ?? '';
        }
    }

    const { data, setData, post, put, processing, errors, transform } =
        useForm(initialData);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const hasFiles = Object.values(data).some(
            (v) => v instanceof File || v instanceof Blob,
        );

        if (mode === 'create' && submitRoute) {
            post(submitRoute);
        } else if (mode === 'edit' && submitRoute) {
            if (hasFiles) {
                // Laravel requires POST method with _method=put for multipart file uploads
                transform((currentData) => ({
                    ...currentData,
                    _method: 'put',
                }));
                post(submitRoute);
            } else {
                put(submitRoute);
            }
        }
    };

    const handleFieldChange = (key: string, value: any) => {
        setData(key as any, value);
    };

    const defaultBreadcrumbs: BreadcrumbItem[] = breadcrumbs ?? [
        { title: resourceName, href: indexRoute ?? '' },
        { title: mode === 'create' ? 'Create' : 'Edit', href: '' },
    ];

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
                                    />
                                ))}

                                {children}

                                <div className="flex justify-end gap-3">
                                    {indexRoute && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                window.history.back()
                                            }
                                        >
                                            Cancel
                                        </Button>
                                    )}
                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Saving...'
                                            : mode === 'create'
                                              ? `Create ${resourceName}`
                                              : `Update ${resourceName}`}
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
