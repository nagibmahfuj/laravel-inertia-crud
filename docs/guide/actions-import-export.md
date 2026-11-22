# Actions, Export, Import, Soft Deletes & Custom Actions

Control standard actions, CSV export, sync/queued background CSV imports, SoftDeletes trash management, and declarative row/bulk custom actions directly from `resourceConfig()`.

## Action-Driven Visibility

```php
'actions' => ['create', 'edit', 'delete', 'show', 'export', 'import'],
```

| Action | Placement | Policy Ability | Behavior |
| --- | --- | --- | --- |
| `create` | PageHeader (`Add {Resource}`) | `create` | Navigates to `/create` (or opens modal/drawer when `form_mode` is `modal`/`drawer`) |
| `export` | PageHeader (`Export`) | `export` | Streams filtered CSV (`GET /{uri}/export`); returns `403` when omitted |
| `import` | PageHeader (`Import`) | `import` | Opens `FileImport` dialog posting to `POST /{uri}/import` |
| `show` | Row action (`Eye`) | `view` | Opens `ResourceShow` (`GET /{uri}/{id}`) |
| `edit` | Row action (`Pencil`) | `update` | Opens edit form + enables inline `status` & `toggle` columns |
| `delete` | Row action & `BulkActions` | `delete` | Single delete, `bulkDestroy`, plus `restore` / `forceDestroy` on `SoftDeletes` models |

---

## Default & Background Queued CSV Import

`CrudController` ships with a built-in, streaming CSV import engine (`CsvImportService` + `ProcessCrudImportJob`) designed for both quick small uploads and 100 MB+ background imports.

### Configuration Example

```php
'actions' => ['create', 'edit', 'delete', 'export', 'import'],
'import'  => [
    'mode'               => 'auto',      // 'sync' | 'queued' | 'auto'
    'queue_threshold_kb' => 1024,        // Auto-queue files > 1 MB
    'chunked_upload'     => true,        // Use ChunkedFileInput inside Import dialog for huge CSVs
    'chunk_size'         => 500,         // DB insert/upsert batch size
    'strategy'           => 'upsert',    // 'insert' | 'insert_or_ignore' | 'upsert' | 'model'
    'unique_by'          => ['sku'],     // For 'upsert' strategy
    'columns'            => [
        'Name'  => ['attribute' => 'name',  'rules' => 'required|string|max:255'],
        'SKU'   => ['attribute' => 'sku',   'rules' => 'required|string|max:100'],
        'Price' => ['attribute' => 'price', 'rules' => 'nullable|numeric|min:0', 'cast' => 'float', 'default' => 0],
    ],
    'transform'   => fn (array $row) => [...$row, 'sku' => strtoupper((string) $row['sku'])],
    'sample_rows' => [
        ['Classic Cola 500ml', 'COKE-500', '1.99'],
    ],
],
```

### How Large-File Background Importing Works

1. **Chunked Upload Support (`chunked_upload => true`)**: When enabled, the Import modal uses `ChunkedFileInput` to slice huge CSV files via `/crud/chunk-upload` (bypassing PHP/Nginx/Cloudflare size caps) and sends only the assembled `chunk_token` to `POST {uri}/import`.
2. **Automatic Queue Dispatch (`mode => 'auto'`)**: Files under `queue_threshold_kb` run synchronously for instant feedback; larger files dispatch `ProcessCrudImportJob`.
3. **Closure-Safe Worker Resolution**: `ProcessCrudImportJob` stores only primitive IDs and resolves fresh `resourceConfig()` from your controller class inside the queue worker — so `'transform'` closures work safely with Redis, Database, SQS, and Horizon without serialization errors.
4. **O(1) Memory Streaming & Real-Time Polling**: Streams CSV rows via `fgetcsv`, validates each row, flushes batches of `chunk_size` rows, updates Cache-backed progress (`GET /crud/imports/{importId}/status`), and streams invalid rows into a downloadable failed-rows CSV (`GET /crud/imports/{importId}/errors`).
5. **Live UI Banner & Auto-Reload**: `ResourceIndex` polls progress every 2 seconds (even if the user closes the dialog!), offers a **Download Error CSV** button when any rows fail, and reloads table data & stats automatically upon completion.
6. **Auto-Generated Sample CSV**: `GET {uri}/import-sample` streams a UTF-8 BOM template built from `import.columns` and `import.sample_rows` — automatically linked as **Download Sample Format** inside the Import modal.

### Import Lifecycle Events

Listen to `CrudImportCompleted` and `CrudImportFailed` to send email/Slack notifications or log to a custom database audit table:

```php
use NagibMahfuj\Crud\Events\CrudImportCompleted;
use NagibMahfuj\Crud\Events\CrudImportFailed;

Event::listen(CrudImportCompleted::class, function (CrudImportCompleted $event) {
    // $event->importId, $event->resource, $event->userId, $event->summary
});

Event::listen(CrudImportFailed::class, function (CrudImportFailed $event) {
    // $event->importId, $event->errorMessage
});
```

::: tip Custom Import Overrides
You can still override `public function import(Request $request)` on any controller whenever you need completely custom parsing or domain-specific batch models.
:::

---

## Soft Deletes & Trash Management

When your model uses `Illuminate\Database\Eloquent\SoftDeletes`, the package enables trash management automatically (or toggle manually via `'soft_deletes' => true|false`):

- **FilterBar**: Adds an **Active Only / With Trashed / Only Trashed** dropdown (`?trashed=with|only`).
- **Row Actions**: Trashed rows display a `Trashed` badge alongside **Restore** (`POST {uri}/{id}/restore`) and **Delete Permanently** (`DELETE {uri}/{id}/force`) buttons.
- **Bulk Actions**: `POST {uri}/bulk-restore` and `POST {uri}/bulk-force-destroy` appear in `BulkActions` when viewing trashed records.
- **File Safety**: Soft-deleting preserves uploaded files on disk so restored records stay intact; `forceDestroy` / `bulkForceDestroy` permanently removes associated files.
- **Policy**: `BaseCrudPolicy` provides `restore()` and `forceDelete()` (defaulting to `hasDeleteAccess()`).

---

## Declarative Custom Row & Bulk Actions

Define custom actions in `resourceConfig()['custom_actions']` without writing extra routes or JSX:

```php
'custom_actions' => [
    [
        'key'        => 'resend_sms',
        'label'      => 'Resend SMS',
        'scope'      => 'row',        // 'row' | 'bulk'
        'icon'       => 'Send',
        'variant'    => 'ghost',
        'permission' => 'update',     // Policy ability checked per model
        'confirm'    => [
            'title'       => 'Resend SMS?',
            'description' => 'Dispatch the performance link SMS again?',
        ],
        'handler' => function ($record, \Illuminate\Http\Request $request) {
            // Your action logic
            return back()->with('success', 'SMS queued.');
        },
    ],
    [
        'key'        => 'mark_featured',
        'label'      => 'Mark Featured',
        'scope'      => 'bulk',
        'icon'       => 'Star',
        'variant'    => 'outline',
        'permission' => 'update',
        'handler'    => function ($records, \Illuminate\Http\Request $request) {
            $records->each->update(['is_featured' => true]);
        },
    ],
],
```

`Route::crudResource()` automatically registers `POST {uri}/action/{action}` → `CrudController::handleAction()`. You can also point an action at an external/custom route via `'url' => '/dashboard/recordings/{id}/send-sms'`.
