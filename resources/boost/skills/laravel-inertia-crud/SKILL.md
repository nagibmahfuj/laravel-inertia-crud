---
name: laravel-inertia-crud
description: Build, customize, and troubleshoot config-driven CRUD resources using nagibmahfuj/laravel-inertia-crud v2 (Laravel + Inertia.js React/TypeScript). Use when creating or editing CrudController classes, BaseCrudPolicy policies, Index/Form/Show Inertia pages, chunked-file uploads, sync/queued CSV imports, SoftDeletes trash management, KPI stats, lazy/async relationship selects, or running crud:generate / crud:install / crud:doctor.
---

# Laravel Inertia CRUD (v2) Skill

Use this skill whenever working on CRUD resources powered by `nagibmahfuj/laravel-inertia-crud` v2.

## Quick Workflow

1. **Scaffold first when adding a new resource:**
   ```bash
   php artisan crud:generate --model=Product --all --actions=create,edit,delete,show,export,import
   ```
2. **Register the route** in `routes/web.php`:
   ```php
   Route::crudResource('products', \App\Http\Controllers\Dashboard\ProductController::class);
   ```
3. **Register permissions** in `config/crud.php` under `resources.{route_prefix}` and bind a `BaseCrudPolicy` subclass if auto-discovery does not match.
4. **Verify wiring** with:
   ```bash
   php artisan crud:doctor
   ```

## Essential v2 Rules

- **Action-driven visibility:** Add `'export'` and `'import'` to `resourceConfig()['actions']` to show those buttons and allow access. Omitting `'export'` hides the button and returns `403` on `GET /{uri}/export`.
- **Zero-boilerplate TSX pages:** `Index.tsx`, `Form.tsx`, and `Show.tsx` pass `{...props}` directly to `<ResourceIndex />`, `<ResourceForm />`, and `<ResourceShow />`, with `AppLayout` breadcrumbs read from `page.props?.meta?.breadcrumbs ?? []`.
- **Override signatures:** Use `int|string $id` on `show()`, `edit()`, `update()`, `destroy()`, `restore()`, `forceDestroy()`, and keep `create()` parameterless.
- **Lazy options:** Wrap dynamic select queries in `'options' => fn () => ...` or use `'relationship' => ['model' => ..., 'async' => true]` (powered by native signed `GET /crud/options`).
- **Native chunked uploads:** Do NOT register custom chunk upload routes. Use `'type' => 'chunked-file'` in `fields` with `disk`, `directory`, `accept`, `chunk_size_mb`, `max_size_mb`, `path_column`, `name_column`, and `size_column`.
- **Auto-handled `file` & `image` fields:** Set `disk`, `directory`, `path_column`, and `delete_old` directly on `file`/`image` fields; omit `ignore_on_save` unless handling manually in `beforeSave()`.
- **Large CSV imports:** Configure `'import'` in `resourceConfig()` with `'mode' => 'auto'` (or `'queued'`), `'chunked_upload' => true`, `'strategy' => 'upsert'|'insert'|'insert_or_ignore'|'model'`, and `'columns'`. Progress polls `GET /crud/imports/{id}/status` and failed rows download from `GET /crud/imports/{id}/errors`.
- **SoftDeletes:** Models using `Illuminate\Database\Eloquent\SoftDeletes` automatically gain the trashed filter, `restore`, `forceDestroy`, `bulkRestore`, and `bulkForceDestroy`.

## Minimal Normalized Page Pattern

```tsx
// Index.tsx
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

## Detailed References

Read these reference files when configuring advanced options:
- [references/resource-config.md](references/resource-config.md) — All `resourceConfig()` keys, 15 column types, 22 field types, filters, sections/grid, `visible_when`, `show_fields`, `stats`, `form_mode`, `custom_actions`, and `SoftDeletes`.
- [references/uploads-and-imports.md](references/uploads-and-imports.md) — `chunked-file`, standard `file`/`image`, async `relationship` options, sync/queued CSV imports, events, and CLI commands.

