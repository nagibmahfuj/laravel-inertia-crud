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

### Minimal Install (Config & Assets Only)

```bash
php artisan crud:install
```

`crud:install` publishes `config/crud.php` and frontend assets (`resources/js/components/crud/`, `resources/js/lib/crud-theme.ts`, `resources/js/hooks/use-nav-items.ts`), inspects `package.json` and `resources/js/components/ui/`, and prints exact commands for any missing npm packages or shadcn/ui components.

### Turnkey New-Project Bootstrap (`--starter`)

Starting from a fresh Laravel React Starter Kit (`laravel new my-app --react`)? Pass `--starter` to interactively pick your **role system** (`users.role` column, `spatie/laravel-permission`, or none) and **starter CRUD resources** (`Users` and/or `Posts` showcase), automatically wire `HandleInertiaRequests`, `AppLayout`, `app.tsx`, `AppSidebar`, and `routes/console.php`, and optionally auto-install missing npm/shadcn/Spatie packages:

::: code-group

```bash [Interactive Starter Wizard]
php artisan crud:install --starter
```

```bash [Non-Interactive (Column Roles + Users CRUD)]
php artisan crud:install --starter \
  --roles=column \
  --examples=users \
  --wire-app \
  --install-deps \
  --ai \
  --no-interaction
```

```bash [Non-Interactive (Spatie Roles + Users & Posts Showcase)]
php artisan crud:install --starter \
  --roles=spatie \
  --examples=users,posts \
  --wire-app \
  --install-deps \
  --ai \
  --no-interaction
```

:::

Then migrate & seed the default admin user (`admin@example.com` / `password`):

```bash
php artisan migrate
php artisan db:seed --class=CrudAdminSeeder
```

#### What `--starter` Scaffolds (Based on Your Choices)

- **Role system (`--roles=`):**
  - `column` — `add_role_to_users_table` migration (`default('viewer')`), adds `'role'` to `User::$fillable`, and creates `CrudAdminSeeder` (`super_admin` + `viewer`).
  - `spatie` — Adds `HasRoles` trait to `App\Models\User`, creates a Spatie-aware `CrudAdminSeeder` (`Role::firstOrCreate` + `syncRoles`), and installs/publishes `spatie/laravel-permission` when `--install-deps` is enabled.
  - `none` — Leaves your existing auth/role setup untouched.
- **Starter CRUD resources (`--examples=`):**
  - `users` — `UserController` (column or Spatie-aware), `UserPolicy` (with self-delete guard), `Dashboard/Users/{Index,Form,Show}.tsx`, KPI stats, role filter, route registration, and `config/crud.php` permissions.
  - `posts` — Full v2 showcase (`Post` model + migration, `PostController`, `PostPolicy`, `Dashboard/Posts/{Index,Form,Show}.tsx`) featuring `image` auto-upload, `slug`, `rich-text`, `tags`, 2-column `form_sections`, `visible_when`, `SoftDeletes`, inline `toggle`/`status`, declarative `custom_actions`, KPI `stats`, and built-in CSV `import`.
- **Host app wiring (`--wire-app`):** Idempotently wires `HandleInertiaRequests.php` (`flash` + `NavBuilder`), `app-layout.tsx` (`meta.breadcrumbs` + `sonner` toasts), `app.tsx` (`<Toaster />` + `applyCustomTheme()`), `app-sidebar.tsx` (`useNavGroups()`), and `routes/console.php` (`crud:cleanup-chunks` hourly schedule).

Or publish assets individually:

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
