import type React from 'react';

export interface CrudBreadcrumbItem {
    title: string;
    href: string;
}

export interface ResourceRoutes {
    index: string;
    create?: string | null;
    store: string;
    update?: string | null;
    export?: string | null;
    import?: string | null;
    bulk_destroy?: string | null;
    chunk_upload?: string | null;
}

export interface ColumnConfig {
    key: string;
    label: string;
    sortable?: boolean;
    type?:
        | 'text'
        | 'number'
        | 'badge'
        | 'status'
        | 'boolean'
        | 'date'
        | 'datetime'
        | 'image'
        | 'serial';
    className?: string;
}

export interface FilterConfig {
    key: string;
    type: 'select' | 'boolean' | 'date_range' | 'date' | 'text';
    label: string;
    options?: { label: string; value: string }[];
    column?: string;
    multiple?: boolean;
    separator?: string;
    placeholder?: string;
}

export interface ChunkedUploadedFile {
    token: string;
    name: string;
    size: number;
    removed?: boolean;
}

export interface ExistingFile {
    path?: string;
    name: string;
    size?: number | null;
    url?: string | null;
}

export interface FieldConfig {
    key: string;
    label: string;
    type:
        | 'text'
        | 'email'
        | 'password'
        | 'number'
        | 'textarea'
        | 'select'
        | 'datetime'
        | 'date'
        | 'toggle'
        | 'checkbox'
        | 'file'
        | 'chunked-file'
        | 'options-builder'
        | 'hidden'
        | 'repeater';
    placeholder?: string;
    options?: { label: string; value: string }[];
    multiple?: boolean;
    required?: boolean;
    disabled?: boolean;
    default?: any;
    accept?: string;
    // Chunked file upload config (populated from resourceConfig / server)
    endpoint?: string;
    chunk_size_mb?: number;
    max_size_mb?: number;
    max_retries?: number;
    upload_config?: string;
    existing_file?: ExistingFile | null;
    // Repeater config
    fields?: FieldConfig[];
    hide_add_button?: boolean;
    hide_remove_button?: boolean;
}

export interface ResourceMeta {
    resource_name: string;
    resource_plural?: string;
    title?: string;
    description?: string | null;
    route_prefix?: string;
    base_route?: string;
    routes?: ResourceRoutes;
    breadcrumbs?: CrudBreadcrumbItem[];
    columns: ColumnConfig[];
    filters: FilterConfig[];
    actions: string[];
    row_selection: boolean;
    per_page: number;
    default_separator?: string;
    import_route?: string | null;
    import_format_url?: string | null;
    import_accept?: string;
    import_max_size_mb?: number;
}

export interface PaginatedData<T = any> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    first_page_url?: string | null;
    last_page_url?: string | null;
    prev_page_url?: string | null;
    next_page_url?: string | null;
    links: { url: string | null; label: string; active: boolean }[];
}

export interface ResourceIndexProps<T = any> {
    data: PaginatedData<T>;
    meta: ResourceMeta;
    filters: Record<string, string>;
    // Optional overrides (default values come from meta / resourceConfig)
    title?: string;
    description?: string;
    baseRoute?: string;
    exportRoute?: string;
    importRoute?: string;
    importFormatUrl?: string;
    importAccept?: string;
    importMaxSizeMB?: number;
    // Custom cell renderers
    cellOverrides?: Record<
        string,
        (item: T, index: number) => React.ReactNode
    >;
    // Extra actions per row
    rowActions?: (item: T) => React.ReactNode;
    // Custom header actions
    headerActions?: React.ReactNode;
}

export interface ResourceFormProps {
    meta: Pick<
        ResourceMeta,
        'resource_name' | 'resource_plural' | 'title' | 'base_route' | 'routes' | 'breadcrumbs'
    >;
    fields: FieldConfig[];
    record?: Record<string, any> | null;
    mode: 'create' | 'edit';
    // Optional overrides (default values come from meta / resourceConfig)
    title?: string;
    submitRoute?: string;
    indexRoute?: string;
    // Extra form fields not in config
    children?: React.ReactNode;
}

