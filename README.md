# Laravel Inertia CRUD

[![Latest Version](https://img.shields.io/packagist/v/nagibmahfuj/laravel-inertia-crud.svg)](https://packagist.org/packages/nagibmahfuj/laravel-inertia-crud)
[![Documentation](https://img.shields.io/badge/docs-GitHub%20Pages-0ea5e9.svg)](https://nagibmahfuj.github.io/laravel-inertia-crud/)
[![License](https://img.shields.io/packagist/l/nagibmahfuj/laravel-inertia-crud.svg)](https://packagist.org/packages/nagibmahfuj/laravel-inertia-crud)

A **config-driven CRUD scaffolding** package for **Laravel + Inertia.js (React/TypeScript)**. Define your columns, fields, filters, chunked uploads, and actions in a single PHP `resourceConfig()` array — the package handles searching, filtering, sorting, pagination, validation, bulk deletion, CSV export, native chunked file uploads, and renders normalized React/TypeScript UI components automatically.

> [!TIP]
> **Full Documentation:** Browse the interactive, searchable v2 documentation at **[nagibmahfuj.github.io/laravel-inertia-crud](https://nagibmahfuj.github.io/laravel-inertia-crud/)**.
> Upgrading from **v1.x** to **v2.0**? See the dedicated [Upgrade Guide (v1 → v2)](UPGRADE.md) or the [online upgrade guide](https://nagibmahfuj.github.io/laravel-inertia-crud/upgrade/v1-to-v2).

---

## ✨ Features

- **Config-Driven & Normalized (`Index`, `Form`, `Show`)**: Define titles, breadcrumbs, routes, KPI stats, columns, fields, detail sections, filters, and actions in a single `resourceConfig()` array — `ResourceIndex`, `ResourceForm`, `ResourceShow`, and `AppLayout` consume normalized `meta` automatically
- **Action-Driven Visibility & Custom Actions**: Control `create`, `edit`, `delete`, `show`, `export`, `import`, inline boolean toggles, and declarative row/bulk `custom_actions` from one config array filtered by `BaseCrudPolicy`
- **Native Chunked & Auto-Handled File Uploads**: Built-in `chunked-file` (`POST /crud/chunk-upload`) plus auto-handled `file` & `image` fields with live previews, signed server validation, atomic reassembly, disk persistence, and delete cleanup
- **Sync & Background Queued CSV Imports**: Streaming CSV import engine with `sync`/`queued`/`auto` modes, chunked-upload CSV support, Cache progress polling (`/crud/imports/{id}/status`), downloadable failed-rows CSV (`/crud/imports/{id}/errors`), auto sample CSV (`GET {uri}/import-sample`), and `CrudImportCompleted`/`CrudImportFailed` events
- **Rich Form Layouts & 22 Field Types**: Card sections, 2-column grid (`form_columns` / `col_span`), `help` text, conditional `visible_when`, Modal/Drawer form mode (`form_mode`), lazy Closure options, signed async relationship selects (`/crud/options`), plus `image`, `slug`, `tags`, `radio`, `color`, `key-value`, and zero-dependency Markdown `rich-text`
- **Soft Deletes & Table UX**: Auto-detected `SoftDeletes` trash filter + `restore`/`forceDestroy`/`bulkRestore`/`bulkForceDestroy`, `localStorage`-persisted column visibility toggle (`default_hidden`), KPI `StatCard` stats row, and 15 column types (`copyable`, `currency`, `link`, `tags`, `truncate`, `toggle`, etc.)
- **Full Artisan CLI Toolkit**: `crud:install` (guided setup + prerequisite check), `crud:generate` (scaffolds controllers, policies, `Index.tsx`, `Form.tsx`, `Show.tsx`), `crud:doctor` (health audit), and `crud:cleanup-chunks`
- **Role-Based Permissions & Spatie**: Configurable per-resource `roles`, `read_only`, `create_only`, and `no_delete` overrides with automatic `spatie/laravel-permission` detection
- **Laravel 11 / 12 / 13 & Inertia v1 / v2 / v3** compatible

---

## 📋 Requirements

| Dependency | Version             |
| ---------- | ------------------- |
| PHP        | ^8.2                |
| Laravel    | 11.x, 12.x, or 13.x |
| Inertia.js | ^1.0, ^2.0, or ^3.0 |
| React      | 18+ / 19+           |
| Node.js    | 18+                 |

### Frontend Prerequisites

Your Laravel app must have the following installed (standard in Laravel React/TypeScript starter kits):

- **shadcn/ui** components: `Button`, `Input`, `Select`, `Label`, `Table`, `Badge`, `Checkbox`, `Dialog`, `Calendar`, `Popover`, `Card`, `Progress`, `Separator`, `DropdownMenu`, `Textarea`, `Command`
- **lucide-react** for icons
- **date-fns** for date formatting
- **clsx** + **tailwind-merge** for class merging
- **lodash** (for `get` utility)
- **react-day-picker** (v9 / v10 for calendar/date range)

---

## 🚀 Installation

### 1. Install the package

```bash
composer require nagibmahfuj/laravel-inertia-crud:^2.0
```

The service provider automatically registers `Route::crudResource()`, native chunk upload (`/crud/chunk-upload`), async options (`/crud/options`), background import (`/crud/imports/{id}/status|errors`) routes, and Artisan commands (`crud:install`, `crud:generate`, `crud:doctor`, `crud:cleanup-chunks`).

### 2. Run the guided installer

```bash
php artisan crud:install
# Optionally also publish stubs & portable AI Agent Skill:
php artisan crud:install --stubs --ai
```

`crud:install` publishes `config/crud.php`, all React/TypeScript components (`resources/js/components/crud/`), theme (`resources/js/lib/crud-theme.ts`), and navigation hook (`resources/js/hooks/use-nav-items.ts`), and audits `package.json` + `resources/js/components/ui/` for missing npm or shadcn/ui prerequisites.

Or publish assets selectively:

```bash
php artisan vendor:publish --tag=crud-config
php artisan vendor:publish --tag=crud-assets
php artisan vendor:publish --tag=crud-stubs
php artisan vendor:publish --tag=crud-ai
```

Run `php artisan crud:doctor` anytime to audit your package setup, queue connection, storage disks, and every `CrudController` in your app.

### 3. Install npm dependencies (if reported missing by `crud:install`)

```bash
npm install date-fns lodash react-day-picker
npm install -D @types/lodash
```

### 4. Install required shadcn/ui components (if reported missing by `crud:install`)

```bash
npx shadcn@latest add button input label select table badge checkbox dialog calendar popover card progress separator dropdown-menu textarea command
```

> [!NOTE]
> If you get an installation error for shadcn/ui components:
> - Remove any stray pnpm files: `rm -f pnpm-lock.yaml pnpm-workspace.yaml`
> - Pin npm in `package.json`: `npm pkg set packageManager="npm@10.8.0"`
> - Re-run the `npx shadcn@latest add ...` command.

---

## ⚡ Quick Start — Interactive Generator

The fastest way to scaffold a new CRUD resource:

```bash
php artisan crud:generate
```

The command renders a package/environment overview banner and walks you through a 7-step wizard:

1. **Select a model** discovered from `app/Models/` (including nested subdirectories)
2. **Route prefix** (e.g., `products`)
3. **Inertia page prefix** (e.g., `Dashboard/Products`)
4. **Database column inspection** with automatic CRUD type mapping
5. **Enabled actions** (`create`, `edit`, `delete`, `show`, `export`, `import`)
6. **Artifacts to generate** (Controller, Policy, Inertia TSX Pages, `config/crud.php` permissions)
7. **Confirmation summary** & generation status table

### Non-Interactive Mode

```bash
# Generate everything for a model
php artisan crud:generate --model=Product --all

# Specify enabled actions explicitly
php artisan crud:generate --model=Product --all --actions=create,edit,delete,show,export,import

# Nested model with custom page prefix
php artisan crud:generate --model=Billing/Invoice --all --page-prefix=Admin/Billing/Invoices

# Overwrite existing files
php artisan crud:generate --model=Product --all --force
```

### All CLI Options

| Option                   | Description                                                      |
| ------------------------ | ---------------------------------------------------------------- |
| `--model`, `-M`          | Model class name (e.g., `Product` or `Billing/Invoice`)          |
| `--route-prefix`         | Route URL prefix (e.g., `products`)                              |
| `--page-prefix`          | Inertia page directory (e.g., `Dashboard/Products`)              |
| `--controller-namespace` | Controller namespace                                             |
| `--actions`              | Comma-separated actions (`create,edit,delete,show,export,import`)|
| `--policy`               | Also generate a policy                                           |
| `--pages`                | Also generate Inertia TSX pages                                  |
| `--permissions`          | Also add entry to `config/crud.php`                              |
| `--all`                  | Generate everything (controller + policy + pages + config)       |
| `--force`                | Overwrite existing files                                         |

---

## 📖 Manual Setup (Step by Step)

### Step 1: Create Your Model

```bash
php artisan make:model Product -m
```

### Step 2: Create a Controller

```php
<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Product;
use NagibMahfuj\Crud\CrudController;

class ProductController extends CrudController
{
    protected function resourceConfig(): array
    {
        return [
            'model'             => Product::class,
            'resource_name'     => 'Product',
            'resource_plural'   => 'Products',        // Optional (auto-pluralized from resource_name)
            'title'             => 'Product Catalog', // Optional index page title override
            'description'       => 'Manage store products and downloadable assets.',
            'route_prefix'      => 'products',
            'page_prefix'       => 'Dashboard/Products',
            'per_page'          => 15,
            'default_sort'      => 'id',
            'default_direction' => 'desc',
            'searchable'        => ['name', 'sku', 'category.name'],
            'with'              => ['category'],
            'with_count'        => ['orders'],
            'row_selection'     => true,              // Enables checkboxes & built-in bulkDestroy
            'actions'           => ['create', 'edit', 'delete', 'show', 'export', 'import'],
            'import_format_url' => '/samples/products-template.csv',

            'columns' => [
                ['key' => 'id',            'label' => 'ID',       'sortable' => true,  'type' => 'number'],
                ['key' => 'image_path',    'label' => 'Image',    'sortable' => false, 'type' => 'image'],
                ['key' => 'name',          'label' => 'Name',     'sortable' => true,  'type' => 'text'],
                ['key' => 'sku',           'label' => 'SKU',      'sortable' => true,  'type' => 'text'],
                ['key' => 'status',        'label' => 'Status',   'sortable' => true,  'type' => 'status'],
                ['key' => 'is_active',     'label' => 'Active',   'sortable' => true,  'type' => 'boolean'],
                [
                    'key'           => 'category.name',
                    'label'         => 'Category',
                    'sortable'      => false,
                    'type'          => 'text',
                    'export_format' => fn ($record) => $record->category?->name ?? 'N/A',
                ],
                ['key' => 'created_at',    'label' => 'Created',  'sortable' => true,  'type' => 'datetime'],
            ],

            'filters' => [
                [
                    'key'     => 'status',
                    'label'   => 'Status',
                    'type'    => 'select',
                    'options' => [
                        ['label' => 'Draft',     'value' => 'draft'],
                        ['label' => 'Published', 'value' => 'published'],
                    ],
                ],
                [
                    'key'      => 'category_id',
                    'label'    => 'Category',
                    'type'     => 'select',
                    'multiple' => true,
                    'options'  => [],
                ],
                ['key' => 'is_active',  'label' => 'Active',  'type' => 'boolean'],
                ['key' => 'sku',        'label' => 'SKU',     'type' => 'text'],
                ['key' => 'created_at', 'label' => 'Created', 'type' => 'date_range'],
            ],

            'fields' => [
                ['key' => 'name',        'label' => 'Product Name', 'type' => 'text',     'rules' => 'required|string|max:255', 'required' => true],
                ['key' => 'sku',         'label' => 'SKU',          'type' => 'text',     'rules' => 'required|string|unique:products,sku,{id}', 'required' => true],
                ['key' => 'description', 'label' => 'Description',  'type' => 'textarea', 'rules' => 'nullable|string'],
                ['key' => 'price',       'label' => 'Price',        'type' => 'number',   'rules' => 'required|numeric|min:0', 'required' => true],
                ['key' => 'is_active',   'label' => 'Active',       'type' => 'checkbox', 'default' => true],
                [
                    'key'           => 'manual_file',
                    'label'         => 'Product Manual (PDF / ZIP)',
                    'type'          => 'chunked-file',
                    'rules'         => 'nullable',
                    'accept'        => '.pdf,.zip',
                    'chunk_size_mb' => 8,
                    'max_size_mb'   => 250,
                    'disk'          => 'public',
                    'directory'     => 'products/manuals',
                    'path_column'   => 'manual_path',
                    'name_column'   => 'manual_name',
                    'size_column'   => 'manual_size',
                ],
            ],
        ];
    }
}
```

### Step 3: Create a Policy

```php
<?php

namespace App\Policies;

use NagibMahfuj\Crud\BaseCrudPolicy;

class ProductPolicy extends BaseCrudPolicy
{
    protected function resourceKey(): string
    {
        return 'products';
    }
}
```

### Step 4: Configure Permissions

In `config/crud.php`:

```php
'resources' => [
    'products' => [
        'roles'       => ['super_admin', 'admin', 'manager', 'viewer'],
        'read_only'   => ['viewer'],   // Can view & export only (alias: 'read_only_roles')
        'create_only' => ['manager'],  // Can view, export & create; cannot edit/delete/import (alias: 'create_only_roles')
        'no_delete'   => ['admin'],    // Cannot delete (bool `true` disables delete for all; alias: 'no_delete_roles')
    ],
],

'read_only_roles' => ['viewer'],  // Global: read-only across all resources
'no_delete_roles' => ['manager'], // Global: cannot delete across all resources
```

### Step 5: Register Routes

In `routes/web.php`:

```php
use App\Http\Controllers\Dashboard\ProductController;

Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    Route::crudResource('products', ProductController::class);
});
```

`Route::crudResource()` automatically registers:

- Standard `Route::resource()` routes (`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`)
- `GET {uri}/export` → `ProductController@export` (`{name}.export`)
- `POST {uri}/import` → `ProductController@import` (`{name}.import`)
- `GET {uri}/import-sample` → `ProductController@importSample` (`{name}.import-sample`)
- `POST {uri}/bulk-destroy` → `ProductController@bulkDestroy` (`{name}.bulk-destroy`)
- `POST {uri}/bulk-restore` → `ProductController@bulkRestore` (`{name}.bulk-restore`)
- `POST {uri}/bulk-force-destroy` → `ProductController@bulkForceDestroy` (`{name}.bulk-force-destroy`)
- `POST {uri}/action/{action}` → `ProductController@handleAction` (`{name}.action`)
- `POST {uri}/{id}/restore` → `ProductController@restore` (`{name}.restore`)
- `DELETE {uri}/{id}/force` → `ProductController@forceDestroy` (`{name}.force-destroy`)

> `CrudServiceProvider` also natively registers `POST /crud/chunk-upload`, `DELETE /crud/chunk-upload/{uploadId}`, `GET /crud/options`, `GET /crud/imports/{importId}/status`, and `GET /crud/imports/{importId}/errors` — zero manual routes required.

### Step 6: Create Normalized Inertia Pages

Because `CrudService::buildMeta()` provides normalized `title`, `description`, `routes`, `breadcrumbs`, `actions`, and import settings directly from `resourceConfig()`, your Inertia pages stay completely clean:

**`resources/js/pages/Dashboard/Products/Index.tsx`**

```tsx
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

**`resources/js/pages/Dashboard/Products/Form.tsx`**

```tsx
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

**`resources/js/pages/Dashboard/Products/Show.tsx`** *(auto-generated when `'show'` is in `actions`)*

```tsx
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

Any prop (`title`, `description`, `baseRoute`, `exportRoute`, `importRoute`, `submitRoute`, `indexRoute`, `cellOverrides`, `fieldOverrides`, `rowActions`, `headerActions`) can still be passed on `<ResourceIndex />`, `<ResourceForm />`, or `<ResourceShow />` to override the `meta` default for a specific page.

---

## 📦 Normalized `resourceConfig()` & `meta` Contract

### Supported `resourceConfig()` Keys

| Key                  | Type           | Default / Description                                                                     |
| -------------------- | -------------- | ----------------------------------------------------------------------------------------- |
| `model`              | `string`       | **Required.** Model FQCN (e.g. `Product::class`)                                          |
| `resource_name`      | `string`       | Singular display name (auto-derived from model class if omitted)                          |
| `resource_plural`    | `string`       | Plural display name (`Str::plural($resource_name)`)                                       |
| `title`              | `string\|null` | Index page title override (defaults to `resource_plural`)                                 |
| `create_title`       | `string\|null` | Create form title override (defaults to `"Create {$resource_name}"`)                      |
| `edit_title`         | `string\|null` | Edit form title override (defaults to `"Edit {$resource_name}"`)                          |
| `show_title`         | `string\|null` | Show page title override (defaults to `"{$resource_name} Details"`)                       |
| `description`        | `string\|null` | Optional subtitle rendered in `PageHeader`                                                |
| `route_prefix`       | `string`       | Named route prefix (e.g. `'products'`)                                                    |
| `base_route`         | `string\|null` | Override index base URL (auto-resolved via `route("{$route_prefix}.index", [], false)`)   |
| `url_prefix`         | `string\|null` | Fallback URL prefix when named route isn't registered (defaults to `config('crud.url_prefix')`) |
| `page_prefix`        | `string`       | Inertia page directory prefix (e.g. `'Dashboard/Products'`)                               |
| `form_mode`          | `string`       | Create/Edit UX: `'page'` (default), `'modal'`, or `'drawer'`                              |
| `form_columns`       | `1\|2`         | Responsive form grid column count (default `1`)                                           |
| `form_sections`      | `array`        | Optional section card metadata (`title`, `description`, `columns`)                        |
| `soft_deletes`       | `bool`         | Auto-detected from `SoftDeletes` trait; enables trashed filter, restore & force-delete    |
| `per_page`           | `int`          | Default rows per page (default `10`, clamped by `config('crud.max_per_page')`)            |
| `searchable`         | `string[]`     | Columns / dot-notated relations to search (e.g. `['name', 'category.name']`)              |
| `default_sort`       | `string`       | Default sort column (falls back to `created_at` or primary key for non-timestamped models)|
| `default_direction`  | `string`       | `'asc'` or `'desc'` (default `'desc'`)                                                    |
| `with`               | `string[]`     | Eloquent eager-loaded relations                                                           |
| `with_count`         | `string[]`     | Eloquent `withCount` relations                                                            |
| `stats`              | `array\|Closure`| KPI `StatCard` array or `fn ($baseQuery) => [...]` rendered above the index table        |
| `actions`            | `string[]`     | Enabled actions: `'create'`, `'edit'`, `'delete'`, `'show'`, `'export'`, `'import'`       |
| `custom_actions`     | `array[]`      | Declarative row and bulk actions (`POST {uri}/action/{key}`) with policy & confirm dialog |
| `row_selection`      | `bool`         | Enable multi-row selection checkboxes and `BulkActions` bar                               |
| `import`             | `array`        | Built-in sync/queued CSV import configuration (`mode`, `strategy`, `columns`, etc.)       |
| `export_route`       | `string\|null` | Override export URL (auto-resolved when `'export'` is in `actions`)                       |
| `import_route`       | `string\|null` | Override import URL (auto-resolved when `'import'` is in `actions`)                       |
| `import_format_url`  | `string\|null` | Optional sample template URL (defaults to `GET {uri}/import-sample`)                      |
| `import_accept`      | `string`       | Accepted file types in Import modal (default `'.csv,.txt'`)                               |
| `import_max_size_mb` | `int`          | Max file size in MB for Import modal (default `100`)                                      |
| `breadcrumbs`        | `array\|null`  | Custom base breadcrumbs (`[['title' => '...', 'href' => '...']]`); Create/Edit/Details auto-appended |
| `columns`            | `array[]`      | Index table & CSV export column definitions                                               |
| `show_fields`        | `array[]`      | Optional detail-page field layout for `ResourceShow` (falls back to `columns` / `fields`) |
| `filters`            | `array[]`      | Index `FilterBar` filter definitions                                                      |
| `fields`             | `array[]`      | Create/Edit `ResourceForm` field definitions                                              |

---

## 🧩 Native Chunked File Uploads (`chunked-file`)

For large files (videos, desktop installers, archives) that exceed PHP `upload_max_filesize`, Nginx `client_max_body_size`, or Cloudflare's 100 MB request body limit, use the built-in `chunked-file` field type.

### Zero-Route Setup

`CrudServiceProvider` automatically registers:

- `POST /crud/chunk-upload` (`crud.chunk-upload`) — receives chunk slices, validates limits/extensions, locks & assembles atomically, and returns a user-scoped token
- `DELETE /crud/chunk-upload/{uploadId}` (`crud.chunk-upload.cancel`) — removes partial slices when a user cancels an upload

Customize defaults globally in `config/crud.php`:

```php
'chunk_upload' => [
    'enabled'            => true,
    'route'              => '/crud/chunk-upload',
    'route_name'         => 'crud.chunk-upload',
    'middleware'         => ['web', 'auth'],
    'disk'               => 'public',
    'temp_directory'     => 'crud-chunks',
    'chunk_size_mb'      => 5,
    'max_chunk_size_mb'  => 25,
    'max_file_size_mb'   => 512,
    'max_retries'        => 3,
    'ttl_hours'          => 6,
    'allowed_extensions' => [],
],
```

### Simple Single-Column Example

When the model stores the file path directly in a column matching the field `key`:

```php
'fields' => [
    [
        'key'           => 'video_path',
        'label'         => 'Performance Video',
        'type'          => 'chunked-file',
        'rules'         => 'required',
        'optional_on_update' => false, // Existing file on edit satisfies `required` unless removed
        'accept'        => '.mp4,.mov,.webm',
        'chunk_size_mb' => 8,
        'max_size_mb'   => 300,
        'disk'          => 'public',
        'directory'     => 'recordings',
    ],
],
```

No custom controller methods or `beforeSave()` hooks are required — `CrudController` validates the token, streams the assembled file to `Storage::disk('public')`, cleans up temp chunks, shows the existing file on edit, and deletes old files on replace, remove, or permanent delete.

### Multi-Column & Dynamic Directory Example

When a single upload populates multiple model columns (`*_path`, `*_name`, `*_size`) and uses a dynamic folder:

```php
'fields' => [
    [
        'key'               => 'mac_file',
        'label'             => 'macOS Installer (.dmg, .zip, .pkg)',
        'type'              => 'chunked-file',
        'rules'             => 'nullable',
        'accept'            => '.dmg,.zip,.pkg',
        'chunk_size_mb'     => 8,
        'max_size_mb'       => 500,
        'max_retries'       => 3,
        'disk'              => 'public',
        'directory'         => fn ($model, $data) => 'releases/' . ($data['version'] ?? $model->version ?? 'v1'),
        'preserve_filename' => true,
        'delete_old'        => true,
        'path_column'       => 'mac_file_path',
        'name_column'       => 'mac_file_name',
        'size_column'       => 'mac_file_size',
        'url_column'        => 'mac_url', // Optional model accessor; defaults to Storage::disk($disk)->url($path)
    ],
],
```

### `chunked-file` Field Options Reference

| Key                 | Type                | Default                                    | Description                                                                |
| ------------------- | ------------------- | ------------------------------------------ | -------------------------------------------------------------------------- |
| `accept`            | `string\|null`      | `null`                                     | Comma-separated extensions or MIME types (enforced client & server-side)   |
| `chunk_size_mb`     | `int`               | `config('crud.chunk_upload.chunk_size_mb')`| Size of each uploaded slice in MB                                          |
| `max_size_mb`       | `int`               | `config('crud.chunk_upload.max_file_size_mb')` | Maximum total file size in MB (enforced client & server-side)         |
| `max_retries`       | `int`               | `config('crud.chunk_upload.max_retries')`  | Number of retry attempts per slice on transient/5xx errors                 |
| `endpoint`          | `string\|null`      | Native package route (`/crud/chunk-upload`)| Optional custom upload endpoint override                                   |
| `disk`              | `string`            | `config('crud.chunk_upload.disk')`         | Target Laravel filesystem disk                                             |
| `directory`         | `string\|Closure`   | `'uploads'`                                | Destination directory (`fn($model, $data) => ...` supported)               |
| `filename`          | `Closure\|null`     | `null`                                     | Optional custom filename generator `fn($originalName, $model, $data)`      |
| `preserve_filename` | `bool`              | `false`                                    | Keep sanitized original filename (`false` adds a unique random suffix)     |
| `visibility`        | `string\|null`      | `null`                                     | Optional disk visibility (`'public'` or `'private'`)                       |
| `delete_old`        | `bool`              | `true`                                     | Delete replaced/removed files and delete files on non-`SoftDeletes` destroy|
| `path_column`       | `string`            | `$field['key']`                            | Model attribute storing the saved file path                                |
| `name_column`       | `string\|null`      | `null`                                     | Optional model attribute storing the original file name                    |
| `size_column`       | `string\|null`      | `null`                                     | Optional model attribute storing the file size in bytes                    |
| `url_column`        | `string\|null`      | `null`                                     | Optional model attribute/accessor used for the edit-mode download link     |
| `auto_handle`       | `bool`              | `true`                                     | Set to `false` if you want to resolve the token manually in `beforeSave()` |

### Standalone Usage (Custom Pages & Modals)

You can also use `ChunkedFileInput` and `ChunkUploadService` directly outside `ResourceForm`:

```tsx
import ChunkedFileInput from '@/components/crud/ChunkedFileInput';

<ChunkedFileInput
    accept=".mp4,.mov,.webm"
    chunkSizeMB={8}
    maxSizeMB={500}
    onChange={(val) => setVideoToken(val && 'token' in val ? val.token : null)}
    onUploadingChange={setIsUploading}
/>
```

```php
use NagibMahfuj\Crud\Services\ChunkUploadService;

$resolved = app(ChunkUploadService::class)->resolveToken($request->input('video_token'));
// Returns ['token', 'path', 'meta_path', 'name', 'size', 'mime_type'] or null
```

### Cleaning Up Stale Chunks

Expired partial slices and unclaimed completed files are pruned automatically on upload completion, and you can also schedule the built-in Artisan command:

```php
// routes/console.php
Schedule::command('crud:cleanup-chunks')->hourly();
```

---

## 📊 Column Configuration

Each column in the `columns` array supports:

| Key              | Type       | Description                                                                                       |
| ---------------- | ---------- | ------------------------------------------------------------------------------------------------- |
| `key`            | `string`   | Model attribute or dot-notation path (e.g., `category.name`)                                      |
| `label`          | `string`   | Column header text                                                                                |
| `sortable`       | `bool`     | Whether the column is sortable                                                                    |
| `type`           | `string`   | Display type: `text`, `number`, `badge`, `status`, `boolean`, `toggle`, `date`, `datetime`, `image`, `serial`, `copyable`, `currency`, `link`, `tags`, `truncate` |
| `className`      | `string`   | Optional Tailwind classes applied to `<th>` and `<td>`                                            |
| `default_hidden` | `bool`     | Hide column by default until toggled on via the `DataTable` Columns menu (`localStorage`)         |
| `hideable`       | `bool`     | Set `false` to prevent hiding this column (default `true`)                                        |
| `currency`/`locale` | `string`| Currency formatting options for `type => 'currency'` (e.g., `'USD'`, `'en-US'`)                   |
| `max_length`     | `int`      | Truncation length for `type => 'truncate'` (default `40`)                                         |
| `export_format`  | `callable` | Custom CSV export formatter: `fn($record) => $record->name`                                       |
| `display_format` | `callable` | Custom display formatter applied server-side before sending to frontend                           |

---

## 📝 Field Configuration

Each field in the `fields` array supports:

| Key                  | Type            | Description                                                            |
| -------------------- | --------------- | ---------------------------------------------------------------------- |
| `key`                | `string`        | Form field name / model attribute                                      |
| `label`              | `string`        | Field label                                                            |
| `type`               | `string`        | Field type (see below)                                                 |
| `rules`              | `string\|array` | Validation rules (Laravel syntax; supports `Rule` objects and `{id}`)  |
| `rules_update`       | `string\|array` | Override validation rules on update                                    |
| `required`           | `bool`          | Show required indicator in UI                                          |
| `optional_on_update` | `bool`          | Replace `required` with `sometimes` on update                          |
| `options`            | `array\|Closure`| Static array (`[['label' => 'X', 'value' => 'y']]` or `['y' => 'X']`) or lazy `Closure` |
| `relationship`       | `array`         | Declarative model options (`model`, `value`, `label`, `search_columns`, `limit`, `query`, `async`) |
| `multiple`           | `bool`          | Enables multi-select combobox for `select` fields                      |
| `slug_from`          | `string`        | Auto-slugify source field key for `slug` fields                        |
| `help`               | `string`        | Helper text displayed beneath the input                                |
| `section` / `col_span`| `string`/`1\|2`| Group into `form_sections` cards & span 1 or 2 grid columns            |
| `visible_when`       | `array`         | Conditional display rule (`field` + `equals`, `not_equals`, `in`, `not_in`, `truthy`, `falsy`, `empty`, `not_empty`) |
| `accept`, `disk`, `directory`, `path_column`, `delete_old` | various | Auto-handled storage & preview options for `file`, `image`, and `chunked-file` fields |
| `default`            | `mixed`         | Default value on create                                                |
| `disabled`           | `bool`          | Render input disabled                                                  |
| `hide_on_create`     | `bool`          | Hide this field on the create form                                     |
| `hide_on_edit`       | `bool`          | Hide this field on the edit form                                       |
| `hidden`             | `bool`          | Hide on both forms                                                     |
| `ignore_on_save`     | `bool`          | Do not assign this attribute in `fillModel()`                          |
| `placeholder`        | `string`        | Input placeholder or helper text                                       |

### Supported Field Types

`text`, `email`, `password`, `number`, `textarea`, `rich-text`, `select`, `radio`, `date`, `datetime`, `toggle`, `checkbox`, `file`, `image`, `chunked-file`, `slug`, `tags`, `color`, `key-value`, `hidden`, `options-builder`, `repeater`

---

## 🔍 Filter Configuration

Each filter in the `filters` array supports:

| Key           | Type     | Description                                                   |
| ------------- | -------- | ------------------------------------------------------------- |
| `key`         | `string` | Filter key (used as URL param: `filter_{key}`)                |
| `label`       | `string` | Filter label                                                  |
| `type`        | `string` | `select`, `boolean`, `text`, `date`, `date_range`             |
| `column`      | `string` | Database column (defaults to `key`)                           |
| `options`     | `array\|Closure` | Static or lazy Closure options for `select` filters           |
| `relationship`| `array`          | Declarative Eloquent options (`model`, `value`, `label`, `search_columns`, `limit`, `query`, `async`) |
| `multiple`    | `bool`   | Enable multi-select faceted filter for `select` filters       |
| `relation`    | `string` | Filter via a relationship (`whereHas`)                        |
| `separator`   | `string` | Custom separator for multi-select URL values                  |
| `placeholder` | `string` | Optional placeholder for `text` filter                        |

---

## 📤 Action-Driven Import & Export

Both **Export** and **Import** visibility are controlled by the `actions` array in `resourceConfig()` and filtered through your resource policy (`BaseCrudPolicy::export()` and `BaseCrudPolicy::import()`).

### Export

Include `'export'` in `actions`:

```php
'actions' => ['create', 'edit', 'delete', 'export'],
```

- When `'export'` is present and the user passes `Policy::export()`, `ResourceIndex` renders the Export button using the auto-resolved `{route_prefix}.export` route.
- When `'export'` is omitted from `actions`, the Export button is hidden **and** `GET /{resource}/export` returns `403 Forbidden`.

### Import (Built-In Sync & Background Queued CSV Engine)

Include `'import'` in `actions` and configure `'import'` in `resourceConfig()` — no custom `import()` controller method required:

```php
'actions' => ['create', 'edit', 'delete', 'export', 'import'],
'import'  => [
    'mode'               => 'auto',      // 'sync' | 'queued' | 'auto'
    'queue_threshold_kb' => 1024,        // Auto-queue files > 1 MB
    'chunked_upload'     => true,        // Uses ChunkedFileInput (/crud/chunk-upload) for huge CSVs
    'chunk_size'         => 500,         // DB insert/upsert batch size
    'strategy'           => 'upsert',    // 'insert' | 'insert_or_ignore' | 'upsert' | 'model'
    'unique_by'          => ['sku'],
    'columns'            => [
        'Name'  => ['attribute' => 'name',  'rules' => 'required|string|max:255'],
        'SKU'   => ['attribute' => 'sku',   'rules' => 'required|string|max:100'],
        'Price' => ['attribute' => 'price', 'rules' => 'nullable|numeric|min:0', 'cast' => 'float', 'default' => 0],
    ],
    'transform'   => fn (array $row) => [...$row, 'sku' => strtoupper((string) $row['sku'])],
    'sample_rows' => [['Classic Cola 500ml', 'COKE-500', '1.99']],
],
```

- `Route::crudResource()` automatically registers `POST {uri}/import` and `GET {uri}/import-sample` (streaming a downloadable template CSV). Both return `403` when `'import'` is omitted from `actions`.
- Files larger than `queue_threshold_kb` automatically dispatch `ProcessCrudImportJob`, update Cache-backed progress polled by `ResourceIndex` (`GET /crud/imports/{id}/status`), generate a downloadable failed-rows CSV (`GET /crud/imports/{id}/errors`), and dispatch `CrudImportCompleted` / `CrudImportFailed` events.
- Need a custom Excel or domain-specific importer? You can still override `public function import(Request $request)` on any controller.

---

## ✨ KPI Stats, Soft Deletes, Custom Actions & Modal Forms

- **KPI Stats (`stats`):** Add `'stats' => fn ($query) => [['title' => 'Total', 'value' => (clone $query)->count(), 'icon' => 'Package']]` (or override `protected function stats($baseQuery): array`) to render responsive `<StatCard />` metrics above the index table.
- **Soft Deletes:** Models using `Illuminate\Database\Eloquent\SoftDeletes` automatically gain the trashed filter (`?trashed=with|only`), row/bulk **Restore** & **Delete Permanently** actions, and `BaseCrudPolicy::restore()` / `forceDelete()`.
- **Declarative Custom Actions (`custom_actions`):** Define `'scope' => 'row' | 'bulk'` actions with `'permission'`, `'confirm'`, `'icon'`, and `'handler' => fn ($recordOrCollection, Request $request) => ...` handled automatically via `POST {uri}/action/{action}`.
- **Modal / Drawer Forms (`form_mode`):** Set `'form_mode' => 'modal'` or `'drawer'` to open create/edit forms directly inside `ResourceIndex` via `X-Crud-Form: 1` without leaving the table.
- **Auto-Handled `file` & `image` Fields & Async Selects:** Configure `disk`/`directory`/`path_column`/`delete_old` directly on `file`/`image` fields, and use `'options' => fn () => ...` or `'relationship' => ['model' => ..., 'async' => true]` (`GET /crud/options`) for fast searchable selects.

See the [full v2 documentation](https://nagibmahfuj.github.io/laravel-inertia-crud/) for detailed guides and code examples for every feature.

---

## ⚙️ Configuration Reference (`config/crud.php`)

```php
return [
    'default_separator' => '--',
    'url_prefix'        => '/dashboard',
    'max_per_page'      => 100,

    'breadcrumbs' => [
        'root' => [
            'title' => 'Dashboard',
            'href'  => '/dashboard',
        ],
    ],

    'role_resolver' => null,

    'resources' => [
        'users' => [
            'roles'       => ['super_admin', 'admin'],
            'read_only'   => ['viewer'],
            'create_only' => ['data_entry'],
            'no_delete'   => ['admin'],
        ],
    ],

    'read_only_roles' => ['viewer'],
    'no_delete_roles' => ['manager'],

    'chunk_upload' => [
        'enabled'            => true,
        'route'              => '/crud/chunk-upload',
        'route_name'         => 'crud.chunk-upload',
        'middleware'         => ['web', 'auth'],
        'disk'               => 'public',
        'temp_directory'     => 'crud-chunks',
        'chunk_size_mb'      => 5,
        'max_chunk_size_mb'  => 25,
        'max_file_size_mb'   => 512,
        'max_retries'        => 3,
        'ttl_hours'          => 6,
        'allowed_extensions' => [],
    ],

    'options' => [
        'enabled'    => true,
        'route'      => '/crud/options',
        'route_name' => 'crud.options',
        'middleware' => ['web', 'auth'],
        'limit'      => 25,
        'ttl_hours'  => 6,
    ],

    'import' => [
        'enabled'            => true,
        'route'              => '/crud/imports',
        'route_name'         => 'crud.import',
        'middleware'         => ['web', 'auth'],
        'mode'               => 'auto',      // 'sync' | 'queued' | 'auto'
        'queue_threshold_kb' => 1024,
        'queue'              => null,
        'connection'         => null,
        'chunk_size'         => 500,
        'disk'               => 'local',
        'temp_directory'     => 'crud-imports',
        'status_ttl_hours'   => 24,
        'max_errors_sample'  => 50,
        'tries'              => 3,
        'timeout'            => 600,
    ],

    'generator' => [
        'controller_namespace' => 'App\\Http\\Controllers\\Dashboard',
        'policy_namespace'     => 'App\\Policies',
        'pages_directory'      => null, // Auto-detects resources/js/pages vs resources/js/Pages
        'model_path'           => 'app/Models',
    ],
];
```

---

## 🔑 Spatie Integration & Custom Role Resolver

The package auto-detects `spatie/laravel-permission`. If installed, it uses `$user->hasRole()` and `$user->getRoleNames()`; otherwise it falls back to reading a `role` column on the `users` table.

If you do not use Spatie and need a `role` column:

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('role')->default('viewer');
});
```

### Custom Role Resolver

Implement `NagibMahfuj\Crud\Contracts\RoleResolver`:

```php
<?php

namespace App\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use NagibMahfuj\Crud\Contracts\RoleResolver;

class CustomRoleResolver implements RoleResolver
{
    public function getRoles(Authenticatable $user): array
    {
        return $user->roles->pluck('slug')->toArray();
    }

    public function hasRole(Authenticatable $user, string|array $roles): bool
    {
        return $user->roles->pluck('slug')->intersect((array) $roles)->isNotEmpty();
    }
}
```

Register in `config/crud.php`:

```php
'role_resolver' => \App\Services\CustomRoleResolver::class,
```

---

## ⚡ Inertia Layout, Flash Toasts & Theming

### 1. Normalized `AppLayout`

`ResourceIndex`, `ResourceForm`, and `ResourceShow` never wrap `<AppLayout>` internally, preventing layout duplication in Inertia v2/v3. Wire your `AppLayout` to read normalized `meta.breadcrumbs` as a fallback and display session flash toasts:

```tsx
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import type { BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from '@/components/ui/sonner';

export default function AppLayout({
    breadcrumbs,
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    const { flash, meta } = usePage().props as any;
    const resolvedBreadcrumbs = breadcrumbs ?? meta?.breadcrumbs ?? [];

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
        if (flash?.error) {
            toast.error(flash.error, { id: flash.error });
        }
    }, [flash]);

    return (
        <AppLayoutTemplate breadcrumbs={resolvedBreadcrumbs}>
            {children}
        </AppLayoutTemplate>
    );
}
```

### 2. Share Flash Messages in `HandleInertiaRequests`

```php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'flash' => [
            'success' => fn () => $request->session()->get('success'),
            'error'   => fn () => $request->session()->get('error'),
            'data'    => fn () => $request->session()->get('data'),
        ],
    ]);
}
```

### 3. Toaster & Runtime Theme (`app.tsx`)

**Inertia v3:**

```tsx
import { Toaster } from '@/components/ui/sonner';
import { createInertiaApp } from '@inertiajs/react';
import { applyCustomTheme, theme, customThemeColors } from '@/lib/crud-theme';

createInertiaApp({
    // ...
    withApp(app) {
        return (
            <>
                {app}
                <Toaster {...theme.toast} />
            </>
        );
    },
    progress: {
        color: customThemeColors.primary,
    },
});

applyCustomTheme();
```

**Inertia v1 / v2:**

```tsx
import { Toaster } from '@/components/ui/sonner';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { applyCustomTheme, theme, customThemeColors } from '@/lib/crud-theme';

createInertiaApp({
    // ...
    setup({ el, App, props }) {
        createRoot(el).render(
            <>
                <App {...props} />
                <Toaster {...theme.toast} />
            </>,
        );
    },
    progress: {
        color: customThemeColors.primary,
    },
});

applyCustomTheme();
```

---

## 🔧 Customization

### Controller Hooks & Base Query

```php
protected function baseQuery()
{
    return \App\Models\Product::query()
        ->with(['category', 'tags'])
        ->where('company_id', auth()->user()->company_id);
}

protected function beforeSave($model, array $data, string $action): void
{
    if (!empty($data['password'])) {
        $model->password = bcrypt($data['password']);
    }
}

protected function afterSave($model, array $data, string $action): void
{
    if (isset($data['tags'])) {
        $model->tags()->sync($data['tags']);
    }
}
```

### Publish & Customize Stubs

```bash
php artisan vendor:publish --tag=crud-stubs
```

Stubs are saved to `stubs/crud/` and take precedence over package defaults.

---

## 🧭 Dynamic Navigation

Use `NavBuilder::filter()` in `HandleInertiaRequests`:

```php
use NagibMahfuj\Crud\Support\NavBuilder;
use App\Models\User;
use App\Models\Product;

'navGroups' => fn () => NavBuilder::filter([
    [
        'title' => 'General',
        'items' => [
            ['title' => 'Dashboard', 'href' => '/dashboard', 'icon' => 'LayoutGrid'],
        ],
    ],
    [
        'title' => 'Management',
        'items' => [
            ['title' => 'Users', 'href' => '/dashboard/users', 'icon' => 'Users', 'permission' => User::class],
            ['title' => 'Products', 'href' => '/dashboard/products', 'icon' => 'Package', 'permission' => Product::class],
        ],
    ],
], $request->user()),
```

Consume in your sidebar with `useNavGroups()`:

```tsx
import { useNavGroups } from '@/hooks/use-nav-items';
import { NavMain } from '@/components/nav-main';

export function AppSidebar() {
    const navGroups = useNavGroups();

    return (
        <SidebarContent>
            {navGroups.map((group) => (
                <NavMain key={group.title} title={group.title} items={group.items} />
            ))}
        </SidebarContent>
    );
}
```

---

## 🤖 AI Code Editor & Laravel Boost Support

This package ships first-class AI context so coding agents (Antigravity, Claude Code, Cursor, Windsurf, GitHub Copilot, Codex, Laravel Boost, and Context7) generate accurate v2 code out of the box:

- **Laravel Boost (Automatic):** Includes `resources/boost/guidelines/core.blade.php` and `resources/boost/skills/laravel-inertia-crud/` — automatically discovered by `php artisan boost:install` with zero setup.
- **Portable Agent Skill (`.agents/skills/`):**
  ```bash
  # Install into any AI editor via the skills CLI
  npx skills add nagibmahfuj/laravel-inertia-crud

  # Or publish locally from Composer into .agents/skills/laravel-inertia-crud/
  php artisan crud:install --ai
  # or: php artisan vendor:publish --tag=crud-ai
  ```
- **`llms.txt` & `llms-full.txt`:** Auto-generated with the VitePress docs site at [llms.txt](https://nagibmahfuj.github.io/laravel-inertia-crud/llms.txt) and [llms-full.txt](https://nagibmahfuj.github.io/laravel-inertia-crud/llms-full.txt).
- **Context7 & `AGENTS.md`:** Ships `context7.json` and `AGENTS.md` at the repository root.

---

## 🔄 Upgrading from v1.x to v2.0

See the full **[UPGRADE.md](UPGRADE.md)** guide for every breaking change, before/after code diffs, and migration steps (including action-driven export/import, normalized `Index.tsx`/`Form.tsx`/`AppLayout`, and migrating manual chunk upload routes/controllers to the native `chunked-file` field).

---

## 🧪 Testing

```bash
composer install
composer test
```

---

## 🤝 Contributing

1. Fork the repository and create a feature branch (`git checkout -b feature/my-feature`).
2. Link locally via a Composer `path` repository to test in a Laravel app.
3. Run `composer test` and ensure all tests pass.
4. Update `README.md` / `UPGRADE.md` for any new options or breaking changes.
5. Submit a Pull Request against `main`.

---

## 📄 License

MIT License. See [LICENSE](LICENSE) for details.

---

## 👤 Author

**Nagib Mahfuj** — GitHub: [@nagibmahfuj](https://github.com/nagibmahfuj)

Made with ❤️ for the Laravel community.
