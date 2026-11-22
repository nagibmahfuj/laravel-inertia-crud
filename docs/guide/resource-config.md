# Normalized Resource Config

In v2.0, `CrudController::resourceConfig()` is the **single source of truth** for titles, descriptions, routes, breadcrumbs, KPI stats, columns, filters, fields, detail views, form modes, soft deletes, custom actions, and imports.

## Minimal Inertia Pages

::: code-group

```tsx [Index.tsx]
import ResourceIndex from '@/components/crud/ResourceIndex';
import type { ResourceIndexProps } from '@/components/crud/types';
import AppLayout from '@/layouts/app-layout';

export default function Index(props: ResourceIndexProps) {
    return <ResourceIndex {...props} />;
}

Index.layout = (page: any) => (
    <AppLayout breadcrumbs={page.props?.meta?.breadcrumbs ?? []}>
        {page}
    </AppLayout>
);
```

```tsx [Form.tsx]
import ResourceForm from '@/components/crud/ResourceForm';
import type { ResourceFormProps } from '@/components/crud/types';
import AppLayout from '@/layouts/app-layout';

export default function Form(props: ResourceFormProps) {
    return <ResourceForm {...props} />;
}

Form.layout = (page: any) => (
    <AppLayout breadcrumbs={page.props?.meta?.breadcrumbs ?? []}>
        {page}
    </AppLayout>
);
```

```tsx [Show.tsx]
import ResourceShow from '@/components/crud/ResourceShow';
import type { ResourceShowProps } from '@/components/crud/types';
import AppLayout from '@/layouts/app-layout';

export default function Show(props: ResourceShowProps) {
    return <ResourceShow {...props} />;
}

Show.layout = (page: any) => (
    <AppLayout breadcrumbs={page.props?.meta?.breadcrumbs ?? []}>
        {page}
    </AppLayout>
);
```

:::

---

## Complete `resourceConfig()` Keys

| Key | Type | Default / Behavior |
| --- | --- | --- |
| `model` | `string` | **Required.** Model FQCN (e.g. `App\Models\Product::class`) |
| `resource_name` | `string` | Singular label (auto-derived from model class name if omitted) |
| `resource_plural` | `string` | Plural label (`Str::plural($resource_name)`) |
| `title` / `create_title` / `edit_title` / `show_title` | `string\|null` | Context-aware page titles |
| `description` | `string\|null` | Optional subtitle rendered in `PageHeader` & `ResourceShow` |
| `route_prefix` | `string` | Named route prefix (e.g. `'products'`) |
| `base_route` / `url_prefix` | `string\|null` | Auto-resolved via `route("{$route_prefix}.index", [], false)` |
| `page_prefix` | `string` | Inertia page folder prefix (e.g. `'Dashboard/Products'`) |
| `form_mode` | `'page'\|'modal'\|'drawer'` | Create/Edit UX mode (default `'page'`) |
| `form_columns` | `1\|2` | Responsive form grid column count (default `1`) |
| `form_sections` | `array` | Optional section titles, descriptions, and per-section column overrides |
| `soft_deletes` | `bool` | Auto-detected from `Illuminate\Database\Eloquent\SoftDeletes` trait |
| `per_page` | `int` | Default page size (default `10`, clamped by `config('crud.max_per_page')`) |
| `searchable` | `string[]` | Searchable attributes & dot-notated relations |
| `default_sort` / `default_direction` | `string` | Default sort column & direction (`'desc'`) |
| `with` / `with_count` | `string[]` | Eloquent eager loads & count relations |
| `stats` | `array\|Closure` | KPI `StatCard` definitions rendered above the table on `index()` |
| `actions` | `string[]` | `'create'`, `'edit'`, `'delete'`, `'show'`, `'export'`, `'import'` |
| `custom_actions` | `array[]` | Declarative row and bulk actions with confirmation & policy checks |
| `row_selection` | `bool` | Enables checkboxes, `bulkDestroy`, `bulkRestore`, `bulkForceDestroy`, & bulk custom actions |
| `import` | `array` | Config-driven sync/queued CSV import engine ([see Actions, Export & Import](/guide/actions-import-export)) |
| `import_format_url` / `import_accept` / `import_max_size_mb` | `string\|int` | Optional overrides for import template link, accepted file types (default `'.csv,.txt'`), and max upload size in MB (default `100`) |
| `create_route` / `export_route` / `import_route` / `bulk_*_route` | `string\|null` | Optional URL overrides (auto-resolved from `route_prefix` / `base_route` by default) |
| `columns` | `array[]` | Index table & CSV export column definitions |
| `show_fields` | `array[]` | Optional detail-page field layout for `ResourceShow` (falls back to `columns` / `fields`) |
| `filters` | `array[]` | `FilterBar` definitions (supports lazy Closure & async relationship options) |
| `fields` | `array[]` | `ResourceForm` field definitions |
| `breadcrumbs` | `array\|null` | Custom base breadcrumb trail (Create/Edit/Details auto-appended) |

---

## Normalized `meta` Contract

`CrudService::buildMeta()` automatically builds and sends a normalized `meta` object to `Index.tsx`, `Form.tsx`, `Show.tsx`, and `AppLayout`:

- **Identity & Titles:** `resource_name`, `resource_plural`, `title`, `description`, `route_prefix`, `base_route`
- **Resolved Routes (`meta.routes`):** `index`, `create`, `store`, `update`, `export`, `import`, `import_sample`, `bulk_destroy`, `bulk_restore`, `bulk_force_destroy`, `action_base`, `chunk_upload`
- **Layout & UX:** `breadcrumbs`, `form_mode`, `form_columns`, `form_sections`, `soft_deletes`, `row_selection`, `per_page`, `default_separator`
- **Permissions & Schema:** Policy-filtered `actions`, `custom_actions`, `columns`, `filters`, plus `import_format_url`, `import_accept`, `import_max_size_mb`, `import_chunked`, and `import_mode`

---

## KPI Stats Row (`stats`)

Return KPI summary cards from either `resourceConfig()['stats']` or by overriding `protected function stats($baseQuery): array`:

```php
'stats' => fn ($query) => [
    ['title' => 'Total Products', 'value' => (clone $query)->count(), 'icon' => 'Package', 'description' => 'All catalog items'],
    ['title' => 'Active',         'value' => (clone $query)->where('is_active', true)->count(), 'change' => 12, 'icon' => 'CheckCircle'],
],
```

Each stat item supports:
- `title` (`string`, required) — Card label
- `value` (`string|int|float`, required) — Metric value
- `change` (`int|float`, optional) — Percentage delta badge (positive green, negative red)
- `description` (`string`, optional) — Helper subtext below the value
- `icon` (`string`, optional) — Lucide icon name from `resources/js/components/crud/icon-map.ts` (e.g., `Package`, `Users`, `TrendingUp`, `CheckCircle`, `DollarSign`)

`ResourceIndex` renders a responsive 1–4 column `<StatCard />` grid directly above the DataTable automatically.

---

## Modal & Slide-Over Drawer Forms (`form_mode`)

For lightweight resources (tags, categories, blacklisted phones), set `'form_mode'` to `'modal'` or `'drawer'`:

```php
'form_mode' => 'drawer', // 'page' (default) | 'modal' | 'drawer'
```

Clicking **Add** or row **Edit** fetches the server-prepared fields, signed upload configs, and `existing_file` metadata from `create()` / `edit()` (using `X-Crud-Form: 1` with full Policy authorization) and renders `<ResourceForm embedded />` in place without leaving the table!

---

## Built-In Detail View (`ResourceShow`)

When `'show'` is in `actions`, `CrudController::show()` passes `showFields` to `Show.tsx`:

```php
'show_fields' => [
    ['key' => 'title',      'label' => 'Title',   'type' => 'text',      'section' => 'Overview'],
    ['key' => 'sku',        'label' => 'SKU',     'type' => 'copyable',  'section' => 'Overview'],
    ['key' => 'price',      'label' => 'Price',   'type' => 'currency',  'currency' => 'USD', 'section' => 'Overview'],
    ['key' => 'cover_path', 'label' => 'Cover',   'type' => 'image',     'section' => 'Media'],
    ['key' => 'body',       'label' => 'Content', 'type' => 'rich-text', 'section' => 'Content', 'col_span' => 2],
],
```

Each `show_fields` entry supports `key` (dot-notation supported), `label`, `type`, `section`, `col_span` (`1` or `2`), `currency`, `locale`, `display_format` (`fn ($record) => ...`), and `hide_on_show => true`. If `show_fields` is omitted, `ResourceShow` falls back cleanly to your `columns` (or `fields`) definitions and automatically omits `password` and `serial` fields.
