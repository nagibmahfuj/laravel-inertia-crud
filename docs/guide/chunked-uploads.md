# Chunked File Uploads

Large files (videos, desktop installers, archives) often hit PHP `upload_max_filesize`, Nginx `client_max_body_size`, or Cloudflare's 100 MB request body cap.

v2.0 includes **native chunked file uploads** out of the box:
- **Zero manual routes** — `CrudServiceProvider` automatically registers `POST /crud/chunk-upload` and `DELETE /crud/chunk-upload/{uploadId}`.
- **Field-driven configuration** — Control chunk size, max file size, accepted extensions, disk, directory, and column mappings straight from `resourceConfig()['fields']`.
- **Signed server enforcement** — Field limits (`max_size_mb`, `accept`) are encrypted per form render and enforced server-side on every slice.
- **Zero-boilerplate persistence** — `CrudController` validates tokens, streams files to disk, resolves edit-mode `existing_file` previews, and deletes replaced/removed files automatically.

---

## Global Defaults (`config/crud.php`)

```php
'chunk_upload' => [
    'enabled'            => true,
    'route'              => '/crud/chunk-upload',
    'route_name'         => 'crud.chunk-upload',
    'middleware'         => ['web', 'auth'],
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

::: tip Custom Auth Guard
If your dashboard uses a named guard (e.g. `auth:web` with `verified`), update `chunk_upload.middleware` in `config/crud.php`:
```php
'middleware' => ['web', 'auth:web', 'verified'],
```
:::

---

## Usage in `resourceConfig()`

::: code-group

```php [Single-Column Storage]
'fields' => [
    [
        'key'           => 'video_path',
        'label'         => 'Performance Video',
        'type'          => 'chunked-file',
        'rules'         => 'required',
        'accept'        => '.mp4,.mov,.webm',
        'chunk_size_mb' => 8,
        'max_size_mb'   => 300,
        'disk'          => 'public',
        'directory'     => 'recordings',
    ],
],
```

```php [Multi-Column + Dynamic Directory]
'fields' => [
    [
        'key'               => 'mac_file',
        'label'             => 'macOS Installer (.dmg, .zip, .pkg)',
        'type'              => 'chunked-file',
        'rules'             => 'nullable',
        'accept'            => '.dmg,.zip,.pkg',
        'chunk_size_mb'     => 8,
        'max_size_mb'       => 500,
        'max_retries'       => 3,
        'disk'              => 'public',
        'directory'         => fn ($model, $data) => 'releases/' . ($data['version'] ?? $model->version ?? 'v1'),
        'preserve_filename' => true,
        'delete_old'        => true,
        'path_column'       => 'mac_file_path',
        'name_column'       => 'mac_file_name',
        'size_column'       => 'mac_file_size',
        'url_column'        => 'mac_url',
    ],
],
```

:::

### How Lifecycle Persistence Works

1. **Create / Edit render**: `CrudService::prepareFieldsForFrontend()` strips server-only closures (`directory`, `filename`), injects the native endpoint, chunk limits, a signed `upload_config` token, and (on edit) the `existing_file` metadata (`path`, `name`, `size`, `url`).
2. **Client slicing**: `ChunkedFileInput` validates extension and file size upfront, slices the file into `chunk_size_mb` pieces, retries transient/5xx failures up to `max_retries` times, and disables form submission while any chunked field is uploading (tracked per field key so multiple concurrent uploads work safely).
3. **Server assembly**: `ChunkUploadController` verifies the signed config, stores each slice under `storage/app/crud-chunks/uploads/{uploadId}`, uses `flock()` for concurrency-safe reassembly, checks assembled byte count against `file_size`, writes a user-scoped `.meta.json` sidecar, and returns `{ completed: true, token, name, size }`.
4. **Form submit (`store` / `update`)**: `ChunkedFileRule` verifies token validity & user ownership (and ensures `'required'` passes on edit when keeping an existing file). `ChunkUploadService::processField()` then streams the temp file to the target `disk`/`directory`, sets `path_column`/`name_column`/`size_column`, deletes old files if replaced or removed (`{ removed: true }`), and deletes the temp file.
5. **Model deletion (`destroy` / `bulkDestroy`)**: When `delete_old => true` and the model does **not** use `SoftDeletes`, associated files are automatically deleted from disk.

---

## Field Options Reference

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `accept` | `string\|null` | `null` | Comma-separated extensions or MIME types (validated client & server-side) |
| `chunk_size_mb` | `int` | `config('crud.chunk_upload.chunk_size_mb')` | Slice size in MB |
| `max_size_mb` | `int` | `config('crud.chunk_upload.max_file_size_mb')` | Max total file size in MB |
| `max_retries` | `int` | `config('crud.chunk_upload.max_retries')` | Retry attempts per slice on network/5xx errors |
| `endpoint` | `string\|null` | Native `/crud/chunk-upload` | Optional custom endpoint override |
| `disk` | `string` | `config('crud.chunk_upload.disk')` | Target Laravel filesystem disk |
| `directory` | `string\|Closure` | `'uploads'` | Destination path (`fn($model, $data) => ...` supported) |
| `filename` | `Closure\|null` | `null` | Custom filename callback `fn($originalName, $model, $data)` |
| `preserve_filename` | `bool` | `false` | Keep sanitized original name (`false` appends a random suffix) |
| `visibility` | `string\|null` | `null` | Optional `'public'` or `'private'` visibility |
| `delete_old` | `bool` | `true` | Delete old file on replace, remove, or permanent destroy |
| `path_column` | `string` | `$field['key']` | Model attribute storing the saved path |
| `name_column` | `string\|null` | `null` | Optional attribute storing original filename |
| `size_column` | `string\|null` | `null` | Optional attribute storing byte size |
| `url_column` | `string\|null` | `null` | Optional attribute/accessor for download link (fallback: `Storage::disk($disk)->url($path)`) |
| `auto_handle` | `bool` | `true` | Set `false` to manually resolve tokens inside `beforeSave()` |

---

## Standalone Usage (Custom Forms & Modals)

Use `ChunkedFileInput` and `ChunkUploadService` directly on custom Inertia pages or modals:

::: code-group

```tsx [React Component]
import ChunkedFileInput from '@/components/crud/ChunkedFileInput';

<ChunkedFileInput
    accept=".mp4,.webm,.mov"
    chunkSizeMB={8}
    maxSizeMB={500}
    onChange={(val) => {
        setVideoToken(val && 'token' in val ? val.token : null);
    }}
    onUploadingChange={setIsUploading}
/>
```

```php [Controller]
use NagibMahfuj\Crud\Services\ChunkUploadService;

public function attachVideo(Request $request, ChunkUploadService $chunkUploads)
{
    $resolved = $chunkUploads->resolveToken((string) $request->input('video_token'));

    if (!$resolved) {
        return response()->json(['message' => 'Upload token invalid or expired.'], 422);
    }

    // $resolved = ['token', 'path', 'meta_path', 'name', 'size', 'mime_type']
    Storage::disk('public')->putFileAs('recordings', new \Illuminate\Http\File($resolved['path']), 'final.mp4');
    @unlink($resolved['path']);
}
```

:::

---

## Housekeeping & Stale Upload Cleanup

Partial slices are deleted immediately when a user clicks **Cancel** (`DELETE /crud/chunk-upload/{uploadId}`), and expired uploads older than `ttl_hours` are pruned opportunistically after completed merges.

For busy apps, schedule the built-in cleanup command in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('crud:cleanup-chunks')->hourly();
```

You can also run it manually with a custom retention window:

```bash
php artisan crud:cleanup-chunks --hours=2
```

