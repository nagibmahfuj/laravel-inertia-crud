# Laravel Inertia CRUD

[![Latest Version](https://img.shields.io/packagist/v/nagibmahfuj/laravel-inertia-crud.svg)](https://packagist.org/packages/nagibmahfuj/laravel-inertia-crud)
[![License](https://img.shields.io/packagist/l/nagibmahfuj/laravel-inertia-crud.svg)](https://packagist.org/packages/nagibmahfuj/laravel-inertia-crud)

A **config-driven CRUD scaffolding** package for **Laravel + Inertia.js (React/TypeScript)**. Define your columns, fields, filters, and permissions in a single PHP config array — the package handles searching, filtering, sorting, pagination, validation, CSV export, and renders beautiful React UI components automatically.

## ✨ Features

- **Config-Driven**: Define models, columns, fields, filters, and actions in a single `resourceConfig()` array
- **Interactive Generator**: Beautiful `php artisan crud:generate` wizard with model/column auto-detection
- **Built-in Search**: Full-text search across multiple columns including relationships
- **Advanced Filters**: Select, multi-select, boolean, date, and date-range filters
- **Server-Side Sorting**: Sortable columns with direction toggle
- **CSV Export**: One-click data export with custom formatters
- **File Import**: Built-in import UI with file validation and progress
- **Role-Based Permissions**: Configurable per-resource permissions with read-only, create-only, and no-delete flags
- **Spatie Integration**: Auto-detects `spatie/laravel-permission` — zero config required
- **Lifecycle Hooks**: `beforeSave()` / `afterSave()` hooks for custom transform logic
- **Audit Columns**: Auto-sets `created_by` / `updated_by` if they exist
- **Publishable Components**: shadcn/ui-based React components you fully own and customize
- **Theming**: Runtime CSS variable customization for branding
- **Laravel 11/12/13** compatible

---

## 📋 Requirements

| Dependency | Version |
|---|---|
| PHP | ^8.2 |
| Laravel | 11.x, 12.x, or 13.x |
| Inertia.js | 1.x or 2.x |
| React | 18+ |
| Node.js | 18+ |

### Frontend Prerequisites

Your Laravel app must have the following installed (standard in Laravel Breeze React/TypeScript starter):

- **shadcn/ui** components: `Button`, `Input`, `Select`, `Label`, `Table`, `Badge`, `Checkbox`, `Dialog`, `Calendar`, `Popover`, `Card`, `Progress`, `Separator`, `DropdownMenu`
- **lucide-react** for icons
- **date-fns** for date formatting
- **clsx** + **tailwind-merge** for class merging
- **lodash** (for `get` utility)
- **react-day-picker** (for calendar/date range)

---

## 🚀 Installation

### 1. Install the package

```bash
composer require nagibmahfuj/laravel-inertia-crud
```

The service provider is auto-discovered.

### 2. Publish the config

```bash
php artisan vendor:publish --tag=crud-config
```

This creates `config/crud.php` where you define resource permissions.

### 3. Publish frontend components

```bash
php artisan vendor:publish --tag=crud-assets
```

This publishes:
- `resources/js/components/crud/` — All React components (ResourceIndex, ResourceForm, FormField, FilterBar, DataTable, etc.)
- `resources/js/lib/crud-theme.ts` — Dashboard theme configuration
- `resources/js/lib/crud-utils.ts` — Utility functions (`cn`, `toUrl`)

### 4. Install required shadcn/ui components (if not already installed)

```bash
npx shadcn@latest add button input label select table badge checkbox dialog calendar popover card progress separator dropdown-menu
```

### 5. Install npm dependencies (if not already installed)

```bash
npm install date-fns lodash react-day-picker
npm install -D @types/lodash
```

---

## ⚡ Quick Start — Interactive Generator

The fastest way to create a new CRUD resource:

```bash
php artisan crud:generate
```

This launches a step-by-step wizard that:

1. **Scans your models** from `app/Models/` and lets you pick one
2. **Suggests a route prefix** (e.g., `products`)
3. **Suggests a page prefix** (e.g., `Dashboard/Products`)
4. **Detects database columns** from your table schema, mapping types automatically
5. **Lets you choose** what to generate (controller, policy, pages, config)
6. **Shows a summary** and generates all files

### Non-Interactive Mode

```bash
# Generate everything for a model
php artisan crud:generate --model=Product --all

# Just the controller
php artisan crud:generate --model=Product --route-prefix=products

# Controller + policy with overwrite
php artisan crud:generate --model=Product --policy --force

# Custom controller namespace
php artisan crud:generate --model=Product --all --controller-namespace="App\Http\Controllers\Admin"
```

### All CLI Options

| Option | Description |
|---|---|
| `--model`, `-M` | Model class name (e.g., `Product`) |
| `--route-prefix` | Route URL prefix (e.g., `products`) |
| `--page-prefix` | Inertia page directory (e.g., `Dashboard/Products`) |
| `--controller-namespace` | Controller namespace |
| `--policy` | Also generate a policy |
| `--pages` | Also generate Inertia TSX pages |
| `--permissions` | Also add entry to crud config |
| `--all` | Generate everything (controller + policy + pages + config) |
| `--force` | Overwrite existing files |

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

use NagibMahfuj\Crud\CrudController;

class ProductController extends CrudController
{
    protected function resourceConfig(): array
    {
        return [
            'model'         => \App\Models\Product::class,
            'resource_name' => 'Product',
            'route_prefix'  => 'products',
            'page_prefix'   => 'Dashboard/Products',
            'per_page'      => 10,
            'searchable'    => ['name', 'sku', 'description'],
            'with'          => ['category'],       // Eager load
            'with_count'    => ['orders'],          // Count relations
            'actions'       => ['create', 'edit', 'delete', 'show'],

            'columns' => [
                ['key' => 'id',       'label' => 'ID',       'sortable' => true,  'type' => 'number'],
                ['key' => 'name',     'label' => 'Name',     'sortable' => true,  'type' => 'text'],
                ['key' => 'sku',      'label' => 'SKU',      'sortable' => true,  'type' => 'text'],
                ['key' => 'price',    'label' => 'Price',    'sortable' => true,  'type' => 'number'],
                ['key' => 'status',   'label' => 'Status',   'sortable' => true,  'type' => 'badge'],
                [
                    'key'            => 'category.name',
                    'label'          => 'Category',
                    'sortable'       => false,
                    'type'           => 'text',
                    'export_format'  => fn ($record) => $record->category?->name ?? 'N/A',
                ],
            ],

            'filters' => [
                [
                    'key'     => 'status',
                    'label'   => 'Status',
                    'type'    => 'select',
                    'options' => [
                        ['label' => 'Active',   'value' => 'active'],
                        ['label' => 'Inactive', 'value' => 'inactive'],
                    ],
                ],
                [
                    'key'     => 'category_id',
                    'label'   => 'Category',
                    'type'    => 'select',
                    'multi'   => true,
                    'options' => [], // Inject dynamically in create()/edit()
                ],
                [
                    'key'   => 'created_at',
                    'label' => 'Created',
                    'type'  => 'date_range',
                ],
            ],

            'fields' => [
                ['key' => 'name',        'label' => 'Product Name', 'type' => 'text',     'rules' => 'required|string|max:255',      'required' => true],
                ['key' => 'sku',         'label' => 'SKU',          'type' => 'text',     'rules' => 'required|string|unique:products,sku,{id}', 'required' => true],
                ['key' => 'description', 'label' => 'Description',  'type' => 'textarea', 'rules' => 'nullable|string'],
                ['key' => 'price',       'label' => 'Price',        'type' => 'number',   'rules' => 'required|numeric|min:0',       'required' => true],
                ['key' => 'category_id', 'label' => 'Category',     'type' => 'select',   'rules' => 'required|exists:categories,id', 'required' => true, 'options' => []],
                ['key' => 'status',      'label' => 'Status',       'type' => 'select',   'rules' => 'required|in:active,inactive',  'required' => true,
                    'options' => [
                        ['label' => 'Active',   'value' => 'active'],
                        ['label' => 'Inactive', 'value' => 'inactive'],
                    ],
                ],
                ['key' => 'image',       'label' => 'Image',        'type' => 'file',     'rules' => 'nullable|image|max:2048',      'optional_on_update' => true],
            ],
        ];
    }

    /**
     * Inject dynamic select options.
     */
    public function create()
    {
        $config = $this->resourceConfig();
        // Override to inject category options
        return parent::create();
    }

    /**
     * Hash password before saving (example hook).
     */
    protected function beforeSave($model, array $data, string $action): void
    {
        // Custom logic before saving
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

    // Override individual methods if needed:
    // public function delete(Authenticatable $user, $model): bool
    // {
    //     return $user->hasRole('super_admin');
    // }
}
```

### Step 4: Configure Permissions

In `config/crud.php`:

```php
'resources' => [
    'products' => [
        'roles' => ['super_admin', 'admin', 'manager'],
        'create_only_roles' => ['manager'],  // Managers can create but not edit/delete
    ],
],

'read_only_roles' => ['viewer'],    // Global: viewers can only see data
'no_delete_roles' => ['manager'],   // Global: managers cannot delete anything
```

### Step 5: Register Routes

In `routes/web.php`:

```php
use App\Http\Controllers\Dashboard\ProductController;

Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    Route::crudResource('products', ProductController::class);
});
```

The `Route::crudResource()` macro registers:
- Standard `Route::resource()` routes (index, create, store, show, edit, update, destroy)
- `GET products/export` → `ProductController@export` (if method exists)
- `POST products/import` → `ProductController@import` (if method exists)

### Step 6: Create Inertia Pages

**`resources/js/Pages/Dashboard/Products/Index.tsx`**

```tsx
import ResourceIndex from '@/components/crud/ResourceIndex';

export default function Index(props: any) {
    return (
        <ResourceIndex
            {...props}
            title="Products"
            baseRoute="/dashboard/products"
            exportRoute="/dashboard/products/export"
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Products', href: '/dashboard/products' },
            ]}
        />
    );
}
```

**`resources/js/Pages/Dashboard/Products/Form.tsx`**

```tsx
import ResourceForm from '@/components/crud/ResourceForm';

export default function Form(props: any) {
    const isEdit = props.mode === 'edit';
    const route = isEdit
        ? `/dashboard/products/${props.record?.id}`
        : '/dashboard/products';

    return (
        <ResourceForm
            {...props}
            submitRoute={route}
            indexRoute="/dashboard/products"
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Products', href: '/dashboard/products' },
                { title: isEdit ? 'Edit' : 'Create', href: '' },
            ]}
        />
    );
}
```

---

## ⚙️ Configuration Reference

### `config/crud.php`

```php
return [
    // Separator for multi-select filters in URL query strings
    'default_separator' => '--',

    // Override auto-detected role resolver (null = auto-detect)
    'role_resolver' => null,

    // Resource permission definitions
    'resources' => [
        'users' => [
            'roles'       => ['super_admin', 'admin'],
            'read_only'   => ['viewer'],         // Resource-level read-only
            'create_only' => ['data_entry'],      // Can create but not update/delete
        ],
    ],

    // Global role flags
    'read_only_roles' => ['viewer'],
    'no_delete_roles' => ['manager'],

    // Generator defaults
    'generator' => [
        'controller_namespace' => 'App\\Http\\Controllers\\Dashboard',
        'policy_namespace'     => 'App\\Policies',
        'pages_directory'      => 'resources/js/Pages/Dashboard',
        'model_path'           => 'app/Models',
    ],
];
```

---

## 🔑 Spatie Integration

The package auto-detects `spatie/laravel-permission`. If installed, it uses `$user->hasRole()` and `$user->getRoleNames()` instead of reading from a `role` column.

**No configuration needed** — just install Spatie and the package handles the rest:

```bash
composer require spatie/laravel-permission
```

### Custom Role Resolver

If you have a custom role system, implement the `RoleResolver` interface:

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

Register it in your `AppServiceProvider`:

```php
$this->app->singleton(
    \NagibMahfuj\Crud\Contracts\RoleResolver::class,
    \App\Services\CustomRoleResolver::class
);
```

Or via config:

```php
// config/crud.php
'role_resolver' => \App\Services\CustomRoleResolver::class,
```

---

## 🎨 Theming

After publishing, customize `resources/js/lib/crud-theme.ts`:

```typescript
export const customThemeColors = {
    primary: '#E4252F',              // Your brand color
    sidebarBackground: 'oklch(0.205 0 0)',
    sidebarForeground: 'oklch(0.985 0 0)',
    // ... more sidebar variables
};

// Call once in your app entry point:
import { applyCustomTheme, customThemeColors } from '@/lib/crud-theme';
applyCustomTheme(customThemeColors);
```

---

## 📊 Column Configuration

Each column in the `columns` array supports:

| Key | Type | Description |
|---|---|---|
| `key` | `string` | Model attribute or dot-notation path (e.g., `category.name`) |
| `label` | `string` | Column header text |
| `sortable` | `bool` | Whether the column is sortable |
| `type` | `string` | Display type: `text`, `number`, `badge`, `datetime`, `boolean` |
| `export_format` | `callable` | Custom CSV export formatter: `fn($record) => $record->name` |
| `display_format` | `callable` | Custom display formatter (applied server-side before sending to frontend) |

---

## 📝 Field Configuration

Each field in the `fields` array supports:

| Key | Type | Description |
|---|---|---|
| `key` | `string` | Form field name / model attribute |
| `label` | `string` | Field label |
| `type` | `string` | Field type (see below) |
| `rules` | `string\|array` | Validation rules (Laravel syntax) |
| `rules_update` | `string\|array` | Override rules for update action |
| `required` | `bool` | Show required indicator in UI |
| `optional_on_update` | `bool` | Replace `required` with `sometimes` on update |
| `options` | `array` | Options for `select` fields: `[['label' => 'X', 'value' => 'y']]` |
| `hide_on_create` | `bool` | Hide this field on the create form |
| `hide_on_edit` | `bool` | Hide this field on the edit form |
| `hidden` | `bool` | Hide on both forms |
| `ignore_on_save` | `bool` | Don't set this attribute when saving the model |
| `placeholder` | `string` | Input placeholder text |

### Supported Field Types

`text`, `email`, `password`, `number`, `textarea`, `select`, `date`, `datetime`, `toggle`, `file`, `hidden`, `options-builder`, `repeater`

---

## 🔍 Filter Configuration

Each filter in the `filters` array supports:

| Key | Type | Description |
|---|---|---|
| `key` | `string` | Filter key (used as URL param: `filter_{key}`) |
| `label` | `string` | Filter label |
| `type` | `string` | `select`, `boolean`, `date`, `date_range` |
| `column` | `string` | Database column (defaults to `key`) |
| `options` | `array` | Options for select filters |
| `multi` | `bool` | Allow multi-select |
| `relation` | `string` | Filter via a relationship |
| `separator` | `string` | Custom separator for multi-select URL values |

---

## 📤 Import / Export

### Export

Export is built-in. Any controller extending `CrudController` automatically gets a `GET /export` route that streams a CSV with all current filters applied.

### Import

Add an `import()` method to your controller:

```php
public function import(Request $request)
{
    $this->authorize('import', new \App\Models\Product);

    $request->validate(['file' => 'required|file|mimes:csv,xlsx|max:102400']);

    // Your import logic here (e.g., using Laravel Excel or custom CSV parsing)

    return back()->with('success', 'Import started successfully.');
}
```

Then set the `import_route` in your config:

```php
'import_route' => '/dashboard/products/import',
```

The frontend will automatically show an import button and upload modal.

---

## 🔧 Customization

### Override Controller Hooks

```php
protected function beforeSave($model, array $data, string $action): void
{
    if (isset($data['password'])) {
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

### Override Base Query

```php
protected function baseQuery()
{
    return \App\Models\Product::query()
        ->with(['category', 'tags'])
        ->where('company_id', auth()->user()->company_id);
}
```

### Override Create/Edit for Dynamic Options

```php
public function create()
{
    $response = parent::create();

    // Inject category options into the Inertia response
    $response->with('categories', \App\Models\Category::pluck('name', 'id'));

    return $response;
}
```

### Publish & Customize Stubs

```bash
php artisan vendor:publish --tag=crud-stubs
```

Stubs are saved to `stubs/crud/` and will be used instead of the package defaults.

---

## 🔄 Migration Guide (From Inline to Package)

If you're migrating from the inline `CrudController`/`CrudService` to this package:

1. Install the package: `composer require nagibmahfuj/laravel-inertia-crud`
2. Update your controllers: change `use App\Http\Controllers\Dashboard\CrudController` → `use NagibMahfuj\Crud\CrudController`
3. Update your policies: change `use App\Policies\BaseCrudPolicy` → `use NagibMahfuj\Crud\BaseCrudPolicy` and type-hint `Authenticatable` instead of `User`
4. Rename your config from `config/crud_permissions.php` → `config/crud.php` (update keys accordingly)
5. Update `AppServiceProvider`: remove the manual `RoleResolver` binding and `Route::crudResource` macro (the package handles both)
6. Publish and use the CRUD frontend components from `@/components/crud/` instead of `@/components/dashboard/`

---

## 🧪 Testing

```bash
# From the package directory
composer test
```

---

## 📄 License

MIT License. See [LICENSE](LICENSE) for details.

---

## 👤 Author

**Nagib Mahfuj**
- GitHub: [@nagibmahfuj](https://github.com/nagibmahfuj)

---

Made with ❤️ for the Laravel community.
