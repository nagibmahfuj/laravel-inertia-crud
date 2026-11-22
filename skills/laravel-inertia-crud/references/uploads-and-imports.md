# Uploads, Async Options & Background CSV Imports (v2)

## Native Package Endpoints (Auto-Registered by `CrudServiceProvider`)

- `POST /crud/chunk-upload` (`crud.chunk-upload`) & `DELETE /crud/chunk-upload/{uploadId}` (`crud.chunk-upload.cancel`)
- `GET /crud/options` (`crud.options`) — signed async relationship search
- `GET /crud/imports/{importId}/status` (`crud.import.status`) — Cache-backed import progress
- `GET /crud/imports/{importId}/errors` (`crud.import.errors`) — failed-rows CSV download

## `chunked-file` Field Config

```php
[
    'key'               => 'mac_file',
    'label'             => 'macOS Installer',
    'type'              => 'chunked-file',
    'rules'             => 'nullable',
    'accept'            => '.dmg,.zip,.pkg',
    'chunk_size_mb'     => 8,
    'max_size_mb'       => 500,
    'max_retries'       => 3,
    'disk'              => 'public',
    'directory'         => fn ($model, $data) => 'releases/' . ($data['version'] ?? 'v1'),
    'preserve_filename' => true,
    'delete_old'        => true,
    'path_column'       => 'mac_file_path',
    'name_column'       => 'mac_file_name',
    'size_column'       => 'mac_file_size',
    'url_column'        => 'mac_url',
    'auto_handle'       => true,
]
```

## Standard `file` & `image` Field Config

```php
[
    'key'         => 'cover',
    'label'       => 'Cover Image',
    'type'        => 'image', // or 'file'
    'rules'       => 'nullable|image|max:3072',
    'disk'        => 'public',
    'directory'   => 'articles/covers',
    'path_column' => 'cover_path',
    'delete_old'  => true,
]
```

## Async Searchable Relationship Select

```php
[
    'key'          => 'category_id',
    'label'        => 'Category',
    'type'         => 'select',
    'rules'        => 'required|exists:categories,id',
    'relationship' => [
        'model'          => \App\Models\Category::class,
        'value'          => 'id',
        'label'          => 'name',
        'search_columns' => ['name'],
        'limit'          => 25,
        'async'          => true,
    ],
]
```

## Built-In Sync & Background Queued CSV Import

```php
'actions' => ['create', 'edit', 'delete', 'export', 'import'],
'import'  => [
    'mode'               => 'auto',   // 'sync' | 'queued' | 'auto'
    'queue_threshold_kb' => 1024,     // Auto-queue > 1 MB
    'chunked_upload'     => true,     // Uses ChunkedFileInput inside Import dialog
    'chunk_size'         => 500,
    'strategy'           => 'upsert', // 'insert' | 'insert_or_ignore' | 'upsert' | 'model'
    'unique_by'          => ['sku'],
    'columns'            => [
        'Name'  => ['attribute' => 'name',  'rules' => 'required|string|max:255'],
        'SKU'   => ['attribute' => 'sku',   'rules' => 'required|string|max:100'],
        'Price' => ['attribute' => 'price', 'rules' => 'nullable|numeric|min:0', 'cast' => 'float', 'default' => 0],
    ],
    'transform'   => fn (array $row) => [...$row, 'sku' => strtoupper((string) $row['sku'])],
    'sample_rows' => [['Sample Item', 'SKU-001', '9.99']],
],
```

- Sample CSV downloads automatically from `GET {uri}/import-sample`.
- Events dispatched: `NagibMahfuj\Crud\Events\CrudImportCompleted` and `NagibMahfuj\Crud\Events\CrudImportFailed`.

## Artisan Commands

- `php artisan crud:install [--stubs] [--ai] [--force]` — Publish config, frontend assets, optional stubs/AI skill, and audit npm/shadcn prerequisites.
- `php artisan crud:generate` — Interactive 7-step wizard (or `--model=... --all --actions=...`).
- `php artisan crud:doctor` — Audits config, native routes, queue driver, storage writability, and every `CrudController`.
- `php artisan crud:cleanup-chunks [--hours=6]` — Prunes stale temporary chunks and unclaimed uploads.
