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
    import_sample?: string | null;
    bulk_destroy?: string | null;
    bulk_restore?: string | null;
    bulk_force_destroy?: string | null;
    action_base?: string | null;
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
        | 'toggle'
        | 'date'
        | 'datetime'
        | 'image'
        | 'serial'
        | 'copyable'
        | 'currency'
        | 'link'
        | 'tags'
        | 'truncate';
    className?: string;
    default_hidden?: boolean;
    hideable?: boolean;
    currency?: string;
    locale?: string;
    max_length?: number;
}

export interface FilterConfig {
    key: string;
    type: 'select' | 'boolean' | 'date_range' | 'date' | 'text';
    label: string;
    options?: { label: string; value: string }[];
    options_endpoint?: string;
    options_config?: string;
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

export interface VisibleWhenRule {
    field: string;
    equals?: any;
    not_equals?: any;
    in?: any[];
    not_in?: any[];
    truthy?: boolean;
    falsy?: boolean;
    empty?: boolean;
    not_empty?: boolean;
}

export interface FormSectionConfig {
    title?: string;
    description?: string;
    columns?: 1 | 2;
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
        | 'radio'
        | 'datetime'
        | 'date'
        | 'toggle'
        | 'checkbox'
        | 'file'
        | 'image'
        | 'chunked-file'
        | 'slug'
        | 'tags'
        | 'color'
        | 'key-value'
        | 'rich-text'
        | 'options-builder'
        | 'hidden'
        | 'repeater';
    placeholder?: string;
    help?: string;
    section?: string;
    col_span?: 1 | 2;
    visible_when?: VisibleWhenRule | null;
    options?: { label: string; value: string }[];
    options_endpoint?: string;
    options_config?: string;
    multiple?: boolean;
    required?: boolean;
    disabled?: boolean;
    default?: any;
    accept?: string;
    slug_from?: string;
    // Chunked & standard file upload config
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

export interface ShowFieldConfig {
    key: string;
    label: string;
    type: string;
    value: any;
    section?: string | null;
    col_span?: 1 | 2;
    currency?: string | null;
    locale?: string | null;
}

export interface StatItem {
    title: string;
    value: string | number;
    change?: number;
    description?: string;
    icon?: string;
}

export interface CustomActionConfig {
    key: string;
    label: string;
    scope: 'row' | 'bulk';
    icon?: string | null;
    variant?: 'default' | 'outline' | 'secondary' | 'ghost' | 'destructive';
    method?: 'post' | 'put' | 'patch' | 'delete' | 'get';
    url: string;
    confirm?: {
        title?: string;
        description?: string;
        confirmLabel?: string;
    } | null;
}

export interface ImportProgressState {
    id: string;
    resource: string;
    file_name: string;
    status: 'queued' | 'processing' | 'completed' | 'failed';
    total_rows: number;
    processed_rows: number;
    successful_rows: number;
    failed_rows: number;
    progress: number;
    errors_sample: { row: number; message: string }[];
    error_report_url?: string | null;
    status_url?: string | null;
    error_message?: string | null;
    started_at?: string | null;
    finished_at?: string | null;
}

export interface ResourceMeta {
    resource_name: string;
    resource_plural?: string;
    title?: string;
    description?: string | null;
    route_prefix?: string;
    base_route?: string;
    form_mode?: 'page' | 'modal' | 'drawer';
    form_columns?: 1 | 2;
    form_sections?: Record<string, FormSectionConfig>;
    soft_deletes?: boolean;
    routes?: ResourceRoutes;
    breadcrumbs?: CrudBreadcrumbItem[];
    columns: ColumnConfig[];
    filters: FilterConfig[];
    actions: string[];
    custom_actions?: CustomActionConfig[];
    row_selection: boolean;
    per_page: number;
    default_separator?: string;
    import_route?: string | null;
    import_format_url?: string | null;
    import_accept?: string;
    import_max_size_mb?: number;
    import_chunked?: boolean;
    import_mode?: 'sync' | 'queued' | 'auto';
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
    stats?: StatItem[];
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
        | 'resource_name'
        | 'resource_plural'
        | 'title'
        | 'base_route'
        | 'routes'
        | 'breadcrumbs'
        | 'form_columns'
        | 'form_sections'
    >;
    fields: FieldConfig[];
    record?: Record<string, any> | null;
    mode: 'create' | 'edit';
    // Optional overrides (default values come from meta / resourceConfig)
    title?: string;
    submitRoute?: string;
    indexRoute?: string;
    // Embedded modal/drawer support
    embedded?: boolean;
    onSuccess?: () => void;
    onCancel?: () => void;
    // Extra form fields not in config
    children?: React.ReactNode;
}

export interface ResourceShowProps<T = Record<string, any>> {
    meta: ResourceMeta;
    record: T;
    showFields?: ShowFieldConfig[];
    title?: string;
    description?: string;
    baseRoute?: string;
    fieldOverrides?: Record<
        string,
        (value: any, record: T) => React.ReactNode
    >;
    headerActions?: React.ReactNode;
    children?: React.ReactNode;
}
