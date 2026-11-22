// CRUD components barrel export
export { default as ResourceIndex } from './ResourceIndex';
export { default as ResourceForm } from './ResourceForm';
export { default as ResourceShow } from './ResourceShow';
export { default as ChunkedFileInput } from './ChunkedFileInput';
export { PageHeader } from './PageHeader';
export { FilterBar } from './FilterBar';
export {
    FormField,
    evaluateVisibleWhen,
    formatDateInput,
    formatDateTimeLocal,
    slugifyText,
} from './FormField';
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
export { crudIconMap, resolveLucideIcon } from './icon-map';

// Shared TypeScript types
export type {
    ChunkedUploadedFile,
    ColumnConfig,
    CrudBreadcrumbItem,
    CustomActionConfig,
    ExistingFile,
    FieldConfig,
    FilterConfig,
    FormSectionConfig,
    ImportProgressState,
    PaginatedData,
    ResourceFormProps,
    ResourceIndexProps,
    ResourceMeta,
    ResourceRoutes,
    ResourceShowProps,
    ShowFieldConfig,
    StatItem,
    VisibleWhenRule,
} from './types';
