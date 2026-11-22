# Normalized Resource Config

In v2.0, `CrudController::resourceConfig()` is the **single source of truth** for titles, descriptions, routes, breadcrumbs, columns, filters, fields, and actions. `CrudService::buildMeta()` normalizes this configuration into a predictable `meta` prop consumed directly by `ResourceIndex`, `ResourceForm`, and `AppLayout`.

## Minimal Inertia Pages

Because routes, titles, and breadcrumbs come from `meta`, your generated pages need almost zero boilerplate:

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

:::

::: tip Optional Prop Overrides
Every normalized prop on `ResourceIndex` (`title`, `description`, `baseRoute`, `exportRoute`, `importRoute`, `importFormatUrl`, `importAccept`, `importMaxSizeMB`) and `ResourceForm` (`title`, `submitRoute`, `indexRoute`) remains available as an optional override whenever a single page needs custom behavior.
:::

---

## Complete `resourceConfig()` Keys

| Key | Type | Default / Behavior |
| --- | --- | --- |
| `model` | `string` | **Required.** Model FQCN (e.g. `App\Models\Product::class`) |
| `resource_name` | `string` | Singular label (auto-derived from model class name if omitted) |
| `resource_plural` | `string` | Plural label (`Str::plural($resource_name)`) |
| `title` | `string\|null` | Index page title (defaults to `resource_plural`) |
| `create_title` | `string\|null` | Create page title (defaults to `"Create {$resource_name}"`) |
| `edit_title` | `string\|null` | Edit page title (defaults to `"Edit {$resource_name}"`) |
| `show_title` | `string\|null` | Show page title (defaults to `"{$resource_name} Details"`) |
| `description` | `string\|null` | Optional subtitle rendered in `PageHeader` |
| `route_prefix` | `string` | Named route prefix (e.g. `'products'`) |
| `base_route` | `string\|null` | Base index URL (auto-resolved via `route("{$route_prefix}.index", [], false)`) |
| `url_prefix` | `string\|null` | Fallback URL prefix when named route isn't registered (`config('crud.url_prefix')`) |
| `page_prefix` | `string` | Inertia page folder prefix (e.g. `'Dashboard/Products'`) |
| `per_page` | `int` | Default page size (default `10`, clamped by `config('crud.max_per_page')`) |
| `searchable` | `string[]` | Searchable attributes & dot-notated relations |
| `default_sort` | `string` | Default sort column (falls back to `created_at` or primary key) |
| `default_direction` | `'asc'\|'desc'` | Default sort direction (`'desc'`) |
| `with` | `string[]` | Eloquent eager loads |
| `with_count` | `string[]` | Eloquent `withCount` relations |
| `actions` | `string[]` | Enabled actions: `'create'`, `'edit'`, `'delete'`, `'show'`, `'export'`, `'import'` |
| `row_selection` | `bool` | Enables row checkboxes and `BulkActions` bar (`bulkDestroy`) |
| `export_route` | `string\|null` | Custom export URL override (only populated when `'export'` is authorized) |
| `import_route` | `string\|null` | Custom import URL override (only populated when `'import'` is authorized) |
| `import_format_url` | `string\|null` | Sample template download URL shown inside the Import modal |
| `import_accept` | `string` | Accepted file types in Import modal (default `'.csv,.xlsx,.xls'`) |
| `import_max_size_mb` | `int` | Max import file size in MB (default `100`) |
| `breadcrumbs` | `array\|null` | Custom base breadcrumb trail (Create/Edit/Details appended automatically) |
| `columns` | `array[]` | Table & CSV export column definitions |
| `filters` | `array[]` | `FilterBar` filter definitions |
| `fields` | `array[]` | `ResourceForm` field definitions |

---

## Normalized `meta` Payload Shape

When `CrudController` renders `Index`, `Form`, or `Show`, it passes `meta` shaped as:

```json
{
  "resource_name": "Product",
  "resource_plural": "Products",
  "title": "Products",
  "description": null,
  "route_prefix": "products",
  "base_route": "/dashboard/products",
  "routes": {
    "index": "/dashboard/products",
    "create": "/dashboard/products/create",
    "store": "/dashboard/products",
    "update": "/dashboard/products/42",
    "export": "/dashboard/products/export",
    "import": "/dashboard/products/import",
    "bulk_destroy": "/dashboard/products/bulk-destroy",
    "chunk_upload": "/crud/chunk-upload"
  },
  "breadcrumbs": [
    { "title": "Dashboard", "href": "/dashboard" },
    { "title": "Products", "href": "/dashboard/products" }
  ],
  "columns": [],
  "filters": [],
  "actions": ["create", "edit", "delete", "export"],
  "row_selection": false,
  "per_page": 10,
  "default_separator": "--",
  "import_route": null,
  "import_format_url": null,
  "import_accept": ".csv,.xlsx,.xls",
  "import_max_size_mb": 100
}
```

### Custom Breadcrumbs Example

Override the auto-generated breadcrumbs right inside `resourceConfig()`:

```php
'breadcrumbs' => [
    ['title' => 'Dashboard', 'href' => '/dashboard'],
    ['title' => 'Catalog', 'href' => '#'],
    ['title' => 'Products', 'href' => '/dashboard/products'],
],
```

On `create`, `edit`, and `show` pages, the package automatically appends `{ title: 'Create' | 'Edit' | 'Details', href: '' }`.

