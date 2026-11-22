# Upgrading from v1.x to v2.0

This guide covers all breaking changes, new capabilities, and recommended cleanup steps when upgrading `nagibmahfuj/laravel-inertia-crud` from **v1.x** to **v2.0**.

---

## Summary of Breaking & Notable Changes

| Area | v1.x Behavior | v2.0 Behavior | Impact |
| --- | --- | --- | --- |
| **Export & Import Visibility** | Controlled by `exportRoute` and `importRoute` props in `Index.tsx` | Controlled by `'export'` and `'import'` in `resourceConfig()['actions']` (policy-filtered) | **Breaking** — add `'export'` / `'import'` to `'actions'` |
| **Export Backend Access** | `GET /{resource}/export` always callable if policy allowed `export` | Returns `403 Forbidden` unless `'export'` is included in `resourceConfig()['actions']` | **Breaking** — add `'export'` to `'actions'` |
| **`ResourceIndex` Layout** | Wrapped output in `<AppLayout>` internally (causing double layouts with `Index.layout`) | Returns fragment (`<>...</>`) without `<AppLayout>`, matching `ResourceForm` | **Breaking** if your `Index.tsx` didn't define `Index.layout` |
| **Normalized Props** | `title`, `baseRoute`, `exportRoute`, `importRoute`, `submitRoute`, `indexRoute`, `breadcrumbs` passed manually in every TSX page | Automatically supplied via `meta` from `resourceConfig()`; TSX props are optional overrides | Optional cleanup (recommended) |
| **Chunked File Uploads** | Manual `POST .../chunk-upload` route + per-controller `chunkUpload()` & `beforeSave()` | Native package endpoint (`POST /crud/chunk-upload`) + config-driven `chunked-file` field | Optional migration (removes ~150+ lines/controller) |
| **`CrudController` ID Type-Hint** | `show(int $id)`, `edit(int $id)`, `update(Request $request, int $id)`, `destroy(int $id)` | `int\|string $id` (adds UUID/ULID support) | **Check subclass overrides** — widen `int $id` to `int\|string $id` |
| **`CrudService::buildMeta()` Signature** | `buildMeta(array $config)` | `buildMeta(array $config, string $context = 'index', ?Model $record = null)` | Backwards-compatible (new params optional) |
| **Pagination & DatePicker** | `DataTable` First/Last buttons pointed at Prev/Next; `DatePicker` used `initialFocus` | Uses `first_page_url`/`last_page_url` and `autoFocus` (`react-day-picker` v9/v10) | Re-publish frontend assets |

---

## Step-by-Step Upgrade Checklist

### 1. Update Composer Dependency

```bash
composer require nagibmahfuj/laravel-inertia-crud:^2.0
```

### 2. Update `config/crud.php`

Merge the new v2 keys into your published `config/crud.php` (or re-publish and diff):

```php
// New top-level defaults
'url_prefix'   => '/dashboard',
'max_per_page' => 100,

'breadcrumbs' => [
    'root' => [
        'title' => 'Dashboard',
        'href'  => '/dashboard',
    ],
],

// New native chunked upload config
'chunk_upload' => [
    'enabled'            => true,
    'route'              => '/crud/chunk-upload',
    'route_name'         => 'crud.chunk-upload',
    'middleware'         => ['web', 'auth'], // e.g. ['web', 'auth:web', 'verified'] if using a custom guard
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

> **Tip:** If your dashboard uses a non-default auth guard (e.g., `auth:web`), update `chunk_upload.middleware` in `config/crud.php` to match (for example `['web', 'auth:web', 'verified']`).

### 3. Re-publish Frontend Components & Stubs

```bash
php artisan vendor:publish --tag=crud-assets --force
# If you previously published stubs:
php artisan vendor:publish --tag=crud-stubs --force
```

> [!WARNING]
> Use `git diff` after `--force` if you made project-specific customizations inside `resources/js/components/crud/` so you don't overwrite intentional changes.

This adds two new files to `resources/js/components/crud/`:
- `types.ts` (shared TypeScript interfaces for `ResourceIndexProps`, `ResourceFormProps`, `ResourceMeta`, `FieldConfig`, etc.)
- `ChunkedFileInput.tsx` (generic config-driven chunked uploader)

### 4. Add `'export'` and `'import'` to `actions` in Controllers

In v2.0, the **Export** and **Import** buttons (and `GET /{resource}/export` access) are strictly governed by `resourceConfig()['actions']` and your policy.

**Before (v1.x):**

```php
// CodeController.php
'import_route' => '/dashboard/codes/import',
'actions'      => [],
```

```tsx
// Codes/Index.tsx
<ResourceIndex
    {...props}
    title="Bottle Codes"
    baseRoute="/dashboard/codes"
    exportRoute="/dashboard/codes/export"
    importRoute="/dashboard/codes/import"
/>
```

**After (v2.0):**

```php
// CodeController.php
'title'   => 'Bottle Codes',
'actions' => ['export', 'import'],
```

```tsx
// Codes/Index.tsx
<ResourceIndex {...props} cellOverrides={{ ... }} />
```

Users whose role fails `BaseCrudPolicy::import()` (e.g. `read_only_roles`) will automatically have `'import'` filtered out of `meta.actions` and will not see the Import button.

### 5. Widen Method Signatures on Subclass Overrides (PHP 8 Compatibility)

If any controller extending `CrudController` overrides `show()`, `edit()`, `update()`, or `destroy()`, update `int $id` to `int|string $id`:

```diff
- public function show(int $id)
+ public function show(int|string $id)

- public function edit(int $id)
+ public function edit(int|string $id)

- public function update(Request $request, int $id)
+ public function update(Request $request, int|string $id)

- public function destroy(int $id)
+ public function destroy(int|string $id)
```

### 6. Simplify `Index.tsx`, `Form.tsx`, and `AppLayout`

Because `ResourceIndex` no longer wraps `<AppLayout>` internally and `meta` provides `title`, `routes`, and `breadcrumbs` automatically:

**`Index.tsx` (Before → After):**

```tsx
// Before (v1.x)
export default function Index(props: any) {
    return (
        <ResourceIndex
            {...props}
            title="Products"
            baseRoute="/dashboard/products"
            exportRoute="/dashboard/products/export"
        />
    );
}

Index.layout = (page: any) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Products', href: '/dashboard/products' },
        ]}
    >
        {page}
    </AppLayout>
);
```

```tsx
// After (v2.0)
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

**`Form.tsx` (Before → After):**

```tsx
// Before (v1.x)
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
        />
    );
}
```

```tsx
// After (v2.0)
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

**Optional `AppLayout` enhancement** — default `breadcrumbs` to `meta?.breadcrumbs` so even pages using Inertia v3 global layouts receive automatic breadcrumbs:

```tsx
export default function AppLayout({ breadcrumbs, children }: AppLayoutProps) {
    const { flash, meta } = usePage().props as any;
    const resolvedBreadcrumbs = breadcrumbs ?? meta?.breadcrumbs ?? [];
    // ...
}
```

### 7. Migrate Custom Chunked Uploads to Native `chunked-file` (Optional)

If you previously built custom chunk upload routes and controller methods (such as `POST /dashboard/app-versions/chunk-upload`):

1. **Remove manual chunk-upload routes** from `routes/web.php`:
   ```diff
   - Route::post('app-versions/chunk-upload', [AppVersionController::class, 'chunkUpload'])->name('app-versions.chunk-upload');
     Route::crudResource('app-versions', AppVersionController::class);
   ```

2. **Configure chunk upload directly on the field** inside `resourceConfig()['fields']`:
   ```php
   [
       'key'               => 'mac_file',
       'label'             => 'Upload macOS Installer (.dmg, .zip, .pkg)',
       'type'              => 'chunked-file',
       'rules'             => 'nullable',
       'accept'            => '.dmg,.zip,.pkg',
       'chunk_size_mb'     => 8,
       'max_size_mb'       => 500,
       'disk'              => 'public',
       'directory'         => fn ($model, $data) => 'releases/' . ($data['version'] ?? $model->version ?? 'v1'),
       'preserve_filename' => true,
       'path_column'       => 'mac_file_path',
       'name_column'       => 'mac_file_name',
       'size_column'       => 'mac_file_size',
       'url_column'        => 'mac_url',
   ],
   ```

3. **Delete boilerplate methods** (`chunkUpload()`, `processUploadedBinary()`, `cleanupOldCompletedUploads()`) from your controller.

4. **Update standalone `<ChunkedFileInput />` usages** (if any exist in custom pages/modals):
   - Remove hardcoded `endpoint="/dashboard/.../chunk-upload"` — it now defaults to the native `/crud/chunk-upload` endpoint.
   - Replace manual `storage_path("app/chunks/completed/{$token}")` lookups with `app(\NagibMahfuj\Crud\Services\ChunkUploadService::class)->resolveToken($token)`.

5. **Schedule stale chunk cleanup** (optional, recommended):
   ```php
   Schedule::command('crud:cleanup-chunks')->hourly();
   ```

