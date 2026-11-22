# React Components & Types

All frontend assets are published to your application via `php artisan vendor:publish --tag=crud-assets` (or `php artisan crud:install`) so you fully own and customize them.

## Published Components (`resources/js/components/crud/`)

| File | Export(s) | Purpose |
| --- | --- | --- |
| `ResourceIndex.tsx` | `default` (`ResourceIndex`) | Full config-driven index with KPI stats, search, filters, SoftDeletes trash controls, custom actions, inline toggles, modal/drawer forms, CSV export, and background import polling |
| `ResourceForm.tsx` | `default` (`ResourceForm`) | Config-driven create/edit form supporting standalone pages, embedded modal/drawer mode, card sections, 2-column grid, conditional visibility, and concurrent uploads |
| `ResourceShow.tsx` | `default` (`ResourceShow`) | Config-driven detail view with grouped section cards, formatted field rendering, Edit/Delete header actions, and `fieldOverrides` |
| `FormField.tsx` | `FormField`, `evaluateVisibleWhen`, `formatDateInput`, `formatDateTimeLocal`, `slugifyText` | Renders all 22 field types including `image`/`file` preview & remove, async `select`, `slug`, `tags`, `radio`, `color`, `key-value`, zero-dependency `rich-text`, `repeater`, and `chunked-file` |
| `ChunkedFileInput.tsx` | `default` (`ChunkedFileInput`) | Drag-and-drop chunked uploader with retry logic, progress bar, cancel cleanup, and edit-mode preview |
| `DataTable.tsx` | `DataTable` | Paginated shadcn/ui table with `localStorage`-persisted column visibility toggle, per-page selector, and First/Prev/Next/Last controls |
| `FilterBar.tsx` | `FilterBar` | Debounced search, SoftDeletes trashed selector, select/faceted multi-select, boolean, text, date, and date-range filters |
| `FileImport.tsx` | `FileImport` | Standard & chunked CSV uploader with sample template link, live background polling progress, error sample preview, and failed-row CSV download |
| `BulkActions.tsx` | `BulkActions` | Selection count bar with bulk delete, restore, force-delete, export, and declarative custom bulk actions |
| `icon-map.ts` | `crudIconMap`, `resolveLucideIcon` | Shared string-to-`LucideIcon` resolver for stats and custom actions |
| `DataTableFacetedFilter.tsx` | `DataTableFacetedFilter` | Searchable multi-select dropdown filter |
| `DatePicker.tsx` / `DatePickerWithRange.tsx` | `DatePicker`, `DatePickerWithRange` | `react-day-picker` v9/v10 compatible date pickers |
| `PageHeader.tsx`, `ExportButton.tsx`, `ConfirmDialog.tsx`, `StatusBadge.tsx`, `StatCard.tsx` | Named exports | Reusable dashboard building blocks |
| `types.ts` | Shared TypeScript interfaces | `ResourceMeta`, `ResourceRoutes`, `ResourceIndexProps`, `ResourceFormProps`, `ResourceShowProps`, `ShowFieldConfig`, `StatItem`, `CustomActionConfig`, `ImportProgressState`, `ColumnConfig`, `FilterConfig`, `FieldConfig`, `VisibleWhenRule`, `FormSectionConfig`, `ChunkedUploadedFile`, `ExistingFile`, `CrudBreadcrumbItem`, `PaginatedData` |
| `index.ts` | Barrel re-exports | Re-exports all components, helpers, and types from `@/components/crud` |

## Barrel Import Example

```tsx
import {
    ResourceIndex,
    ResourceForm,
    ResourceShow,
    ChunkedFileInput,
    type ResourceIndexProps,
    type ResourceFormProps,
    type ResourceShowProps,
} from '@/components/crud';
```
