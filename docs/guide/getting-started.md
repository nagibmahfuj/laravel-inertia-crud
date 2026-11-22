# Installation & Setup

Get up and running with `nagibmahfuj/laravel-inertia-crud` v2 in under 5 minutes.

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

- **shadcn/ui** components: `Button`, `Input`, `Label`, `Select`, `Table`, `Badge`, `Checkbox`, `Dialog`, `Calendar`, `Popover`, `Card`, `Progress`, `Separator`, `DropdownMenu`, `Textarea`, `Command`
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
- Native chunk upload endpoints (`POST /crud/chunk-upload` and `DELETE /crud/chunk-upload/{uploadId}`)
- `crud:generate` and `crud:cleanup-chunks` Artisan commands
- Automatic `RoleResolver` detection (`SpatieRoleResolver` or `EloquentRoleResolver`)

## Step 2: Publish Config & Frontend Assets

```bash
php artisan vendor:publish --tag=crud-config
php artisan vendor:publish --tag=crud-assets
```

This publishes:
- `config/crud.php` — Permissions, defaults, chunk upload settings, and generator paths
- `resources/js/components/crud/` — All React components (`ResourceIndex`, `ResourceForm`, `FormField`, `ChunkedFileInput`, `DataTable`, `FilterBar`, `types.ts`, etc.)
- `resources/js/lib/crud-theme.ts` — Runtime CSS variable theme helpers
- `resources/js/hooks/use-nav-items.ts` — Permission-filtered navigation hook

## Step 3: Install Frontend Dependencies

```bash
npm install date-fns lodash react-day-picker
npm install -D @types/lodash
```

Install any missing shadcn/ui primitives:

```bash
npx shadcn@latest add button input label select table badge checkbox dialog calendar popover card progress separator dropdown-menu textarea command
```

::: tip Troubleshooting shadcn/ui CLI errors
If `npx shadcn@latest add` fails due to a stale pnpm file:
```bash
rm -f pnpm-lock.yaml pnpm-workspace.yaml
npm pkg set packageManager="npm@10.8.0"
```
Then re-run the shadcn command.
:::

## Step 4: Scaffold Your First Resource

Use the interactive wizard:

```bash
php artisan crud:generate
```

Then register the route in `routes/web.php`:

```php
use App\Http\Controllers\Dashboard\ProductController;

Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    Route::crudResource('products', ProductController::class);
});
```

### What `Route::crudResource()` Registers

| Method | URI | Controller Action | Route Name |
| --- | --- | --- | --- |
| `GET` | `{uri}/export` | `export` (if exists) | `{name}.export` |
| `POST` | `{uri}/import` | `import` (if exists) | `{name}.import` |
| `POST` | `{uri}/bulk-destroy` | `bulkDestroy` (if exists) | `{name}.bulk-destroy` |
| Standard | `Route::resource(...)` | `index`, `create`, `store`, `show`, `edit`, `update`, `destroy` | `{name}.*` |

Next: explore the [Interactive Generator](/guide/generator) or learn how [Normalized Resource Config](/guide/resource-config) eliminates repetitive page props.

