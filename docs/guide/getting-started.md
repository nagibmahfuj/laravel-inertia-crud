# Installation & Setup

Get up and running with `nagibmahfuj/laravel-inertia-crud` v2 in minutes.

## Requirements

| Dependency | Supported Versions |
| --- | --- |
| PHP | `^8.2` |
| Laravel | `11.x`, `12.x`, or `13.x` |
| Inertia.js | `^1.0`, `^2.0`, or `^3.0` |
| React | `18+` or `19+` |
| Node.js | `18+` |

### Frontend Prerequisites

Your Laravel React/TypeScript app should include:

- **shadcn/ui** components: `Badge`, `Button`, `Calendar`, `Card`, `Checkbox`, `Command`, `Dialog`, `DropdownMenu`, `Input`, `Label`, `Popover`, `Progress`, `Select`, `Separator`, `Table`, `Textarea`
- **lucide-react** — Icons
- **date-fns** — Date formatting
- **clsx** & **tailwind-merge** — Class merging
- **lodash** — Nested object lookup (`get`)
- **react-day-picker** (v9 / v10) — Calendar and date-range pickers

---

## Step 1: Install via Composer

```bash
composer require nagibmahfuj/laravel-inertia-crud:^2.0
```

Laravel auto-discovers `NagibMahfuj\Crud\CrudServiceProvider`, registering:
- `Route::crudResource()` macro
- Native chunk upload endpoints (`POST /crud/chunk-upload`, `DELETE /crud/chunk-upload/{uploadId}`)
- Native async relationship options endpoint (`GET /crud/options`)
- Native background import endpoints (`GET /crud/imports/{importId}/status`, `GET /crud/imports/{importId}/errors`)
- Artisan commands: `crud:install`, `crud:generate`, `crud:doctor`, and `crud:cleanup-chunks`

## Step 2: Run the Guided Installer

```bash
php artisan crud:install
```

`crud:install` publishes `config/crud.php` and frontend assets (`resources/js/components/crud/`, `resources/js/lib/crud-theme.ts`, `resources/js/hooks/use-nav-items.ts`), inspects `package.json` and `resources/js/components/ui/`, and prints exact commands for any missing npm packages or shadcn/ui components.

Or publish individually:

```bash
php artisan vendor:publish --tag=crud-config
php artisan vendor:publish --tag=crud-assets
```

## Step 3: Scaffold & Verify

```bash
php artisan crud:generate
php artisan crud:doctor
```

Register your resource route in `routes/web.php`:

```php
use App\Http\Controllers\Dashboard\ProductController;

Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    Route::crudResource('products', ProductController::class);
});
```

### What `Route::crudResource()` Registers

| Method | URI | Controller Action | Route Name |
| --- | --- | --- | --- |
| `GET` | `{uri}/export` | `export` | `{name}.export` |
| `POST` | `{uri}/import` | `import` | `{name}.import` |
| `GET` | `{uri}/import-sample` | `importSample` | `{name}.import-sample` |
| `POST` | `{uri}/bulk-destroy` | `bulkDestroy` | `{name}.bulk-destroy` |
| `POST` | `{uri}/bulk-restore` | `bulkRestore` | `{name}.bulk-restore` |
| `POST` | `{uri}/bulk-force-destroy` | `bulkForceDestroy` | `{name}.bulk-force-destroy` |
| `POST` | `{uri}/action/{action}` | `handleAction` | `{name}.action` |
| `POST` | `{uri}/{id}/restore` | `restore` | `{name}.restore` |
| `DELETE` | `{uri}/{id}/force` | `forceDestroy` | `{name}.force-destroy` |
| Standard | `Route::resource(...)` | `index`, `create`, `store`, `show`, `edit`, `update`, `destroy` | `{name}.*` |
