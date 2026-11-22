// CRUD components barrel export
export { default as ResourceIndex } from './ResourceIndex';
export { default as ResourceForm } from './ResourceForm';
export { default as ChunkedFileInput } from './ChunkedFileInput';
export { PageHeader } from './PageHeader';
export { FilterBar } from './FilterBar';
export { FormField, formatDateInput, formatDateTimeLocal } from './FormField';
export { StatCard } from './StatCard';
export { StatusBadge } from './StatusBadge';
export { ConfirmDialog } from './ConfirmDialog';
export { BulkActions } from './BulkActions';
export { ExportButton } from './ExportButton';
export { FileImport } from './FileImport';
export { DataTable } from './DataTable';
export { DataTableFacetedFilter } from './DataTableFacetedFilter';
export { DatePicker } from './DatePicker';
export { DatePickerWithRange } from './DatePickerWithRange';

// Shared TypeScript types
export type {
    ChunkedUploadedFile,
    ColumnConfig,
    CrudBreadcrumbItem,
    ExistingFile,
    FieldConfig,
    FilterConfig,
    PaginatedData,
    ResourceFormProps,
    ResourceIndexProps,
    ResourceMeta,
    ResourceRoutes,
} from './types';
