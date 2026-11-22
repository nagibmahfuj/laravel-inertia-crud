# React Components & Types

All frontend assets are published to your application via `php artisan vendor:publish --tag=crud-assets` so you have full ownership and customization freedom.

## Published Components (`resources/js/components/crud/`)

| File | Export(s) | Purpose |
| --- | --- | --- |
| `ResourceIndex.tsx` | `default` (`ResourceIndex`) | Full config-driven index page with search, filters, sorting, pagination, bulk actions, export, and import modal |
| `ResourceForm.tsx` | `default` (`ResourceForm`) | Config-driven create/edit form with file upload handling and concurrent chunked-upload state tracking |
| `FormField.tsx` | `FormField`, `formatDateInput`, `formatDateTimeLocal` | Renders all 15 field types including `select` combobox, `checkbox`/`toggle`, `repeater`, and `chunked-file` |
| `ChunkedFileInput.tsx` | `default` (`ChunkedFileInput`) | Drag-and-drop chunked uploader with retry logic, progress bar, server cancel cleanup, and edit-mode preview |
| `DataTable.tsx` | `DataTable` | Paginated shadcn/ui table with per-page selector and First/Prev/Next/Last controls |
| `FilterBar.tsx` | `FilterBar` | Debounced search input, select/faceted multi-select, boolean, text, date, and date-range filters |
| `DataTableFacetedFilter.tsx` | `DataTableFacetedFilter` | Searchable multi-select dropdown filter |
| `DatePicker.tsx` | `DatePicker` | Single date picker (`react-day-picker` v9/v10 compatible) |
| `DatePickerWithRange.tsx` | `DatePickerWithRange` | Two-month date range picker |
| `PageHeader.tsx` | `PageHeader` | Title, optional subtitle, and action buttons |
| `ExportButton.tsx` | `ExportButton` | Downloads filtered CSV export |
| `FileImport.tsx` | `FileImport` | Drag/select import uploader with format template link and size check |
| `BulkActions.tsx` | `BulkActions` | Selection count bar with bulk delete/export triggers |
| `ConfirmDialog.tsx` | `ConfirmDialog` | Reusable confirmation modal |
| `StatusBadge.tsx` | `StatusBadge` | Auto-colored semantic status badge |
| `StatCard.tsx` | `StatCard` | Dashboard KPI summary card |
| `types.ts` | Shared TypeScript interfaces | `ResourceMeta`, `ResourceRoutes`, `ResourceIndexProps`, `ResourceFormProps`, `ColumnConfig`, `FilterConfig`, `FieldConfig`, `ChunkedUploadedFile`, `ExistingFile`, `CrudBreadcrumbItem`, `PaginatedData` |
| `index.ts` | Barrel re-exports | Re-exports all components and types from `@/components/crud` |

## Barrel Import Example

```tsx
import {
    ResourceIndex,
    ResourceForm,
    ChunkedFileInput,
    type ResourceIndexProps,
    type ResourceFormProps,
} from '@/components/crud';
```

