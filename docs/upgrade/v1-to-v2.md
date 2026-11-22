# Upgrading from v1.x to v2.0

This guide covers every breaking change, new capability, and recommended cleanup step when upgrading `nagibmahfuj/laravel-inertia-crud` from **v1.x** to **v2.0**.

## Summary of Breaking & Notable Changes

| Area | v1.x Behavior | v2.0 Behavior | Impact |
| --- | --- | --- | --- |
| **Export & Import Visibility** | Controlled by `exportRoute` and `importRoute` props in `Index.tsx` | Controlled by `'export'` and `'import'` in `resourceConfig()['actions']` (policy-filtered) | **Breaking** — add `'export'` / `'import'` to `'actions'` |
| **Export Backend Access** | `GET /{resource}/export` always callable when policy allowed `export` | Returns `403 Forbidden` unless `'export'` is in `resourceConfig()['actions']` | **Breaking** — add `'export'` to `'actions'` |
| **`ResourceIndex` Layout** | Wrapped output in `<AppLayout>` internally | Returns fragment (`<>...</>`) without `<AppLayout>`, matching `ResourceForm` | **Breaking** if `Index.tsx` did not define `Index.layout` |
| **Normalized Props** | `title`, `baseRoute`, `exportRoute`, `importRoute`, `submitRoute`, `indexRoute`, `breadcrumbs` passed manually | Provided automatically via `meta` from `resourceConfig()` | Optional cleanup (recommended) |
| **Chunked File Uploads** | Manual routes + per-controller `chunkUpload()` and `beforeSave()` logic | Native `/crud/chunk-upload` endpoint + config-driven `chunked-file` field | Optional migration |
| **`CrudController` ID Type-Hint** | `show(int $id)`, `edit(int $id)`, `update(Request $request, int $id)`, `destroy(int $id)` | `int\|string $id` (supports UUID/ULID models) | **Check subclass overrides** — widen `int $id` to `int\|string $id` |
| **Pagination & DatePicker** | First/Last buttons pointed at Prev/Next; `DatePicker` used `initialFocus` | Uses `first_page_url`/`last_page_url` and `autoFocus` (`react-day-picker` v9/v10) | Re-publish frontend assets |

---

## Step-by-Step Upgrade Checklist

### 1. Update Composer Dependency

```bash
composer require nagibmahfuj/laravel-inertia-crud:^2.0
```

### 2. Update `config/crud.php`

Add the new v2 keys to your published `config/crud.php`:

```php
'url_prefix'   => '/dashboard',
'max_per_page' => 100,

'breadcrumbs' => [
    'root' => [
        'title' => 'Dashboard',
        'href'  => '/dashboard',
    ],
],

'chunk_upload' => [
    'enabled'            => true,
    'route'              => '/crud/chunk-upload',
    'route_name'         => 'crud.chunk-upload',
    'middleware'         => ['web', 'auth'], // e.g. ['web', 'auth:web', 'verified']
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

### 3. Re-publish Frontend Components & Stubs

```bash
php artisan vendor:publish --tag=crud-assets --force
php artisan vendor:publish --tag=crud-stubs --force
```

::: warning Review Local Diffs
If you previously edited files inside `resources/js/components/crud/`, run `git diff` after publishing to preserve any project-specific customizations.
:::

### 4. Add `'export'` and `'import'` to `actions`

::: code-group

```php [Before (v1.x)]
protected function resourceConfig(): array
{
    return [
        // ...
        'import_route' => '/dashboard/codes/import',
        'actions'      => [],
    ];
}
```

```php [After (v2.0)]
protected function resourceConfig(): array
{
    return [
        // ...
        'title'   => 'Bottle Codes',
        'actions' => ['export', 'import'],
    ];
}
```

:::

### 5. Widen Subclass Method Signatures (`int|string $id`)

If any controller extends `CrudController` and overrides `show()`, `edit()`, `update()`, or `destroy()`, widen `int $id` to `int|string $id`:

```php
public function show(int|string $id) { /* ... */ }
public function edit(int|string $id) { /* ... */ }
public function update(Request $request, int|string $id) { /* ... */ }
public function destroy(int|string $id) { /* ... */ }
```

### 6. Simplify `Index.tsx` & `Form.tsx`

::: code-group

```tsx [Index.tsx (Before)]
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

```tsx [Index.tsx (After)]
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

```tsx [Form.tsx (Before)]
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

```tsx [Form.tsx (After)]
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

### 7. Migrate Custom Chunked Uploads (Optional)

1. Remove manual `POST .../chunk-upload` routes from `routes/web.php` — `/crud/chunk-upload` is registered natively.
2. Configure `disk`, `directory`, `accept`, `chunk_size_mb`, `max_size_mb`, `path_column`, `name_column`, and `size_column` directly on the `chunked-file` field in `resourceConfig()`.
3. Delete boilerplate `chunkUpload()`, `processUploadedBinary()`, and `cleanupOldCompletedUploads()` methods from your controllers.
4. For custom pages using `<ChunkedFileInput />` standalone, remove the hardcoded `endpoint` prop (now defaults to `/crud/chunk-upload`) and resolve tokens via `app(\NagibMahfuj\Crud\Services\ChunkUploadService::class)->resolveToken($token)`.
5. Optionally schedule `Schedule::command('crud:cleanup-chunks')->hourly();` in `routes/console.php`.

