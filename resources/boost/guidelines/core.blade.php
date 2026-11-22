## Laravel Inertia CRUD (`nagibmahfuj/laravel-inertia-crud` v2)

This application uses `nagibmahfuj/laravel-inertia-crud` v2 for config-driven Laravel + Inertia.js (React/TypeScript) CRUD resources.

### Core Rules & Conventions
- **Single Source of Truth (`resourceConfig()`):** Define `model`, `resource_name`, `title`, `description`, `route_prefix`, `page_prefix`, `columns`, `filters`, `fields`, `show_fields`, `actions`, `custom_actions`, `stats`, `form_mode`, `soft_deletes`, and `import` inside `CrudController::resourceConfig()`.
- **Action-Driven Export & Import:** Visibility and access for Export and Import are strictly controlled by `'export'` and `'import'` inside `resourceConfig()['actions']` (policy-filtered via `BaseCrudPolicy`). Never rely on hardcoded `exportRoute` or `importRoute` props in `Index.tsx` to toggle buttons.
- **Normalized Frontend Pages:** Keep `Index.tsx`, `Form.tsx`, and `Show.tsx` minimal (`<ResourceIndex {...props} />`, `<ResourceForm {...props} />`, `<ResourceShow {...props} />`) and bind persistent `AppLayout` breadcrumbs to `page.props?.meta?.breadcrumbs ?? []`. Do not wrap `<AppLayout>` inside `ResourceIndex` or `ResourceForm`.
- **Method Signatures on Controller Overrides:** `CrudController` uses `int|string $id` on `show()`, `edit()`, `update()`, `destroy()`, `restore()`, and `forceDestroy()` (supporting integer, UUID, and ULID keys). Match `int|string $id` when overriding these methods, and keep `create()` parameterless.
- **Route Registration:** Always register resources via `Route::crudResource('prefix', Controller::class)` which registers standard resource routes plus `export`, `import`, `import-sample`, `bulk-destroy`, `bulk-restore`, `bulk-force-destroy`, `action/{action}`, `{id}/restore`, and `{id}/force`.
- **Native Package Endpoints (No Manual Routes):**
  - Chunked uploads: `POST /crud/chunk-upload` and `DELETE /crud/chunk-upload/{uploadId}` — configure `'type' => 'chunked-file'` directly in `fields` with `disk`, `directory`, `accept`, `chunk_size_mb`, `max_size_mb`, and `path_column`/`name_column`/`size_column`.
  - Async relationship selects: `GET /crud/options` — use `'relationship' => ['model' => ..., 'async' => true]` or lazy `'options' => fn () => ...` closures so dropdown queries never run on `store`/`update`/`destroy`.
  - Background CSV imports: `GET /crud/imports/{importId}/status` and `GET /crud/imports/{importId}/errors` — configure `'import' => ['mode' => 'auto'|'queued'|'sync', 'chunked_upload' => true, 'strategy' => 'upsert'|'insert'|'insert_or_ignore'|'model', 'columns' => [...]]`.
- **Standard `file` / `image` Fields:** Auto-handled by `FileUploadService` (`disk`, `directory`, `path_column`, `delete_old`) with edit previews and `{ removed: true }` removal unless `'ignore_on_save' => true` or `'auto_handle' => false`.
- **Permissions:** Extend `NagibMahfuj\Crud\BaseCrudPolicy` and configure `config/crud.php` under `resources.{route_prefix}` (`roles`, `read_only`, `create_only`, `no_delete`).
- **Artisan Tooling:** Prefer `php artisan crud:generate` to scaffold resources and `php artisan crud:doctor` to audit controller configs, policies, routes, and queues.

