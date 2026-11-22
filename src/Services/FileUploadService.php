<?php

namespace NagibMahfuj\Crud\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles automatic persistence, replacement, explicit removal, edit-mode
 * preview metadata, and delete cleanup for standard `file` and `image` fields.
 */
class FileUploadService
{
    /**
     * Determine whether a `file` or `image` field should be auto-handled.
     * Respects legacy `ignore_on_save => true` and explicit `auto_handle => false`.
     */
    public function shouldAutoHandle(array $field): bool
    {
        $type = $field['type'] ?? '';
        if (!in_array($type, ['file', 'image'], true)) {
            return false;
        }

        if (($field['ignore_on_save'] ?? false) === true) {
            return false;
        }

        return ($field['auto_handle'] ?? true) !== false;
    }

    /**
     * Build `existing_file` metadata for edit forms.
     */
    public function buildExistingFile(array $field, Model $model): ?array
    {
        $pathColumn = $field['path_column'] ?? $field['key'];
        $path       = $model->{$pathColumn} ?? null;

        if (empty($path) || !is_string($path)) {
            return null;
        }

        $diskName   = $field['disk'] ?? config('crud.chunk_upload.disk', 'public');
        $nameColumn = $field['name_column'] ?? null;
        $sizeColumn = $field['size_column'] ?? null;
        $urlColumn  = $field['url_column'] ?? null;

        $name = ($nameColumn && !empty($model->{$nameColumn}))
            ? (string) $model->{$nameColumn}
            : basename($path);

        $size = ($sizeColumn && $model->{$sizeColumn} !== null)
            ? (int) $model->{$sizeColumn}
            : null;

        $url = null;
        if ($urlColumn && !empty($model->{$urlColumn})) {
            $url = (string) $model->{$urlColumn};
        } elseif (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            $url = $path;
        } else {
            try {
                $url = Storage::disk($diskName)->url($path);
            } catch (\Throwable) {
                $url = $diskName === 'public' ? "/storage/{$path}" : null;
            }
        }

        return [
            'path' => $path,
            'name' => $name,
            'size' => $size,
            'url'  => $url,
        ];
    }

    /**
     * Detect whether the request payload signals explicit removal of a file field.
     */
    public function isRemovedPayload(mixed $value): bool
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '__remove__') {
                return true;
            }
            if (str_starts_with($trimmed, '{')) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    $value = $decoded;
                }
            }
        }

        return is_array($value) && !empty($value['removed']);
    }

    /**
     * Persist, replace, or remove a standard `file` / `image` field on the model.
     */
    public function processField(array $field, Model $model, Request $request, array $data = []): void
    {
        if (!$this->shouldAutoHandle($field)) {
            return;
        }

        $key        = $field['key'];
        $pathColumn = $field['path_column'] ?? $key;
        $nameColumn = $field['name_column'] ?? null;
        $sizeColumn = $field['size_column'] ?? null;
        $diskName   = $field['disk'] ?? 'public';
        $deleteOld  = $field['delete_old'] ?? true;
        $disk       = Storage::disk($diskName);

        // Case 1: New UploadedFile received
        if ($request->hasFile($key)) {
            /** @var UploadedFile $file */
            $file = $request->file($key);
            if (!$file->isValid()) {
                return;
            }

            $this->deleteStoredFile($disk, $model->{$pathColumn} ?? null, $deleteOld);

            $originalName = $file->getClientOriginalName();
            $directory    = $this->resolveDirectory($field, $model, $data);
            $targetName   = $this->resolveTargetFilename($field, $originalName, $model, $data);

            $options = array_filter([
                'disk'       => $diskName,
                'visibility' => $field['visibility'] ?? null,
            ]);

            $storedPath = $file->storeAs($directory, $targetName, $options);

            $model->{$pathColumn} = $storedPath;
            if ($nameColumn) {
                $model->{$nameColumn} = $originalName;
            }
            if ($sizeColumn) {
                $model->{$sizeColumn} = $file->getSize();
            }
            return;
        }

        // Case 2: Explicit removal requested (`{ removed: true }` or `__remove__`)
        $rawValue = $data[$key] ?? $request->input($key);
        if ($this->isRemovedPayload($rawValue)) {
            $this->deleteStoredFile($disk, $model->{$pathColumn} ?? null, $deleteOld);
            $model->{$pathColumn} = null;
            if ($nameColumn) {
                $model->{$nameColumn} = null;
            }
            if ($sizeColumn) {
                $model->{$sizeColumn} = null;
            }
        }
    }

    /**
     * Delete stored files for a model being permanently deleted.
     */
    public function deleteModelFiles(Model $model, array $fields, bool $force = false): void
    {
        if (!$force && in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            return;
        }

        foreach ($fields as $field) {
            if (!$this->shouldAutoHandle($field)) {
                continue;
            }
            if (($field['delete_old'] ?? true) === false) {
                continue;
            }

            $pathColumn = $field['path_column'] ?? $field['key'];
            $path       = $model->{$pathColumn} ?? null;
            if (!empty($path) && is_string($path)) {
                $diskName = $field['disk'] ?? 'public';
                $this->deleteStoredFile(Storage::disk($diskName), $path, true);
            }
        }
    }

    protected function resolveDirectory(array $field, Model $model, array $data): string
    {
        $directory = $field['directory'] ?? 'uploads';

        if (is_callable($directory)) {
            $directory = call_user_func($directory, $model, $data);
        }

        return trim((string) $directory, '/');
    }

    protected function resolveTargetFilename(array $field, string $originalName, Model $model, array $data): string
    {
        if (isset($field['filename']) && is_callable($field['filename'])) {
            return $this->sanitizeFilename((string) call_user_func($field['filename'], $originalName, $model, $data));
        }

        $safeName = $this->sanitizeFilename($originalName);

        if ($field['preserve_filename'] ?? false) {
            return $safeName;
        }

        $ext    = pathinfo($safeName, PATHINFO_EXTENSION);
        $base   = pathinfo($safeName, PATHINFO_FILENAME);
        $suffix = Str::lower(Str::random(8));

        return $ext !== '' ? "{$base}_{$suffix}.{$ext}" : "{$base}_{$suffix}";
    }

    protected function sanitizeFilename(string $filename): string
    {
        $base      = basename($filename);
        $sanitized = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $base) ?: 'file';

        return ltrim($sanitized, '.') ?: 'file';
    }

    protected function deleteStoredFile($disk, ?string $path, bool $shouldDelete): void
    {
        if (!$shouldDelete || empty($path)) {
            return;
        }

        try {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        } catch (\Throwable) {
            // Ignore disk delete errors
        }
    }
}

