<?php

namespace NagibMahfuj\Crud\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Handles server-side chunked file uploads, signed field config tokens,
 * atomic chunk assembly, ownership-verified token resolution, disk persistence,
 * and stale upload housekeeping.
 */
class ChunkUploadService
{
    /**
     * Resolve the native chunk upload endpoint URL.
     */
    public function endpoint(?string $override = null): string
    {
        if ($override) {
            return $override;
        }

        $routeName = config('crud.chunk_upload.route_name', 'crud.chunk-upload');

        if ($routeName && Route::has($routeName)) {
            return route($routeName, [], false);
        }

        return '/' . ltrim((string) config('crud.chunk_upload.route', '/crud/chunk-upload'), '/');
    }

    /**
     * Base temporary storage directory for partial & completed chunks.
     */
    public function tempBasePath(): string
    {
        $subDir = trim((string) config('crud.chunk_upload.temp_directory', 'crud-chunks'), '/');

        return storage_path("app/{$subDir}");
    }

    /**
     * Directory for active partial uploads.
     */
    public function uploadsPath(): string
    {
        return $this->tempBasePath() . '/uploads';
    }

    /**
     * Directory for assembled uploads awaiting form submission.
     */
    public function completedPath(): string
    {
        return $this->tempBasePath() . '/completed';
    }

    /**
     * Encrypt field-level upload constraints so the native endpoint can
     * enforce per-field accept / max_size_mb rules server-side.
     */
    public function signFieldConfig(array $field, mixed $userId = null): string
    {
        $ttlHours = max(1, (int) config('crud.chunk_upload.ttl_hours', 6));

        $payload = [
            'user_id'       => $userId ?? Auth::id(),
            'accept'        => $field['accept'] ?? null,
            'max_size_mb'   => isset($field['max_size_mb']) ? (int) $field['max_size_mb'] : (int) config('crud.chunk_upload.max_file_size_mb', 512),
            'chunk_size_mb' => isset($field['chunk_size_mb']) ? (int) $field['chunk_size_mb'] : (int) config('crud.chunk_upload.chunk_size_mb', 5),
            'expires_at'    => now()->addHours($ttlHours)->timestamp,
        ];

        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * Decrypt and verify a signed field config token.
     *
     * @throws ValidationException
     */
    public function parseFieldConfig(?string $encryptedConfig, mixed $userId = null): array
    {
        if (empty($encryptedConfig)) {
            return [];
        }

        try {
            $decoded = json_decode(Crypt::decryptString($encryptedConfig), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'upload_config' => ['Invalid or tampered upload configuration.'],
            ]);
        }

        if (!is_array($decoded)) {
            throw ValidationException::withMessages([
                'upload_config' => ['Invalid upload configuration payload.'],
            ]);
        }

        if (isset($decoded['expires_at']) && time() > (int) $decoded['expires_at']) {
            throw ValidationException::withMessages([
                'upload_config' => ['Upload session has expired. Please refresh the page and try again.'],
            ]);
        }

        $currentUserId = $userId ?? Auth::id();
        if (
            array_key_exists('user_id', $decoded)
            && $decoded['user_id'] !== null
            && $currentUserId !== null
            && (string) $decoded['user_id'] !== (string) $currentUserId
        ) {
            throw ValidationException::withMessages([
                'upload_config' => ['Upload configuration does not belong to the authenticated user.'],
            ]);
        }

        return $decoded;
    }

    /**
     * Validate and store an incoming chunk slice, assembling the file atomically
     * once all slices have arrived.
     *
     * @throws ValidationException
     */
    public function handleChunk(Request $request): array
    {
        $fieldConfig = $this->parseFieldConfig($request->input('upload_config'));

        $maxChunkMb = max(1, (int) config('crud.chunk_upload.max_chunk_size_mb', 25));
        $maxChunkKb = $maxChunkMb * 1024;
        $maxFileMb  = max(1, (int) ($fieldConfig['max_size_mb'] ?? config('crud.chunk_upload.max_file_size_mb', 512)));
        $maxFileBytes = $maxFileMb * 1024 * 1024;

        $validator = Validator::make($request->all(), [
            'upload_id'    => ['required', 'string', 'regex:/^[a-zA-Z0-9_\-]+$/', 'max:100'],
            'chunk_index'  => ['required', 'integer', 'min:0'],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:10000'],
            'file_name'    => ['required', 'string', 'max:255'],
            'file_size'    => ['required', 'integer', 'min:1', "max:{$maxFileBytes}"],
            'chunk'        => ['required', 'file', "max:{$maxChunkKb}"],
        ], [
            'file_size.max' => "File size exceeds the maximum allowed limit of {$maxFileMb} MB.",
            'chunk.max'     => "Chunk size exceeds the maximum allowed slice limit of {$maxChunkMb} MB.",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $uploadId    = (string) $request->input('upload_id');
        $chunkIndex  = (int) $request->input('chunk_index');
        $totalChunks = (int) $request->input('total_chunks');
        $fileName    = (string) $request->input('file_name');
        $fileSize    = (int) $request->input('file_size');

        if ($chunkIndex >= $totalChunks) {
            throw ValidationException::withMessages([
                'chunk_index' => ['Chunk index must be less than total chunks.'],
            ]);
        }

        // Validate extension against field `accept` and global `allowed_extensions`
        $this->validateFileExtension($fileName, $fieldConfig['accept'] ?? null);

        $chunkDir = $this->uploadsPath() . '/' . basename($uploadId);
        if (!File::isDirectory($chunkDir)) {
            File::makeDirectory($chunkDir, 0755, true, true);
        }

        $userId = Auth::id();
        $ownerFile = "{$chunkDir}/.owner";
        if (File::exists($ownerFile)) {
            $recordedOwner = trim((string) File::get($ownerFile));
            if ($recordedOwner !== '' && $userId !== null && $recordedOwner !== (string) $userId) {
                throw ValidationException::withMessages([
                    'upload_id' => ['Upload ID belongs to another user.'],
                ]);
            }
        } elseif ($userId !== null) {
            File::put($ownerFile, (string) $userId);
        }

        /** @var UploadedFile $chunkFile */
        $chunkFile = $request->file('chunk');
        $chunkFile->move($chunkDir, "chunk_{$chunkIndex}");

        // Acquire exclusive lock per upload_id to prevent race conditions during assembly
        $lockHandle = fopen("{$chunkDir}/.lock", 'c+');
        if ($lockHandle) {
            flock($lockHandle, LOCK_EX);
        }

        try {
            $allChunksExist = true;
            for ($i = 0; $i < $totalChunks; $i++) {
                if (!file_exists("{$chunkDir}/chunk_{$i}")) {
                    $allChunksExist = false;
                    break;
                }
            }

            if (!$allChunksExist) {
                return [
                    'success'      => true,
                    'completed'    => false,
                    'chunk_index'  => $chunkIndex,
                    'total_chunks' => $totalChunks,
                ];
            }

            $completedDir = $this->completedPath();
            if (!File::isDirectory($completedDir)) {
                File::makeDirectory($completedDir, 0755, true, true);
            }

            $safeFileName = $this->sanitizeFilename($fileName);
            $token        = "{$uploadId}_{$safeFileName}";
            $finalPath    = "{$completedDir}/{$token}";
            $tempMerge    = "{$completedDir}/.merging_{$token}";

            $out = fopen($tempMerge, 'wb');
            if (!$out) {
                throw new \RuntimeException('Unable to create merged file on server.');
            }

            for ($i = 0; $i < $totalChunks; $i++) {
                $partPath = "{$chunkDir}/chunk_{$i}";
                $in = fopen($partPath, 'rb');
                if ($in) {
                    stream_copy_to_stream($in, $out);
                    fclose($in);
                }
            }
            fclose($out);

            $mergedSize = filesize($tempMerge);
            if ($mergedSize === false || $mergedSize > $maxFileBytes) {
                @unlink($tempMerge);
                throw ValidationException::withMessages([
                    'file_size' => ["Assembled file exceeds the maximum allowed limit of {$maxFileMb} MB."],
                ]);
            }

            if ($mergedSize !== $fileSize) {
                @unlink($tempMerge);
                throw ValidationException::withMessages([
                    'file_size' => ["Assembled file size ({$mergedSize} bytes) does not match expected size ({$fileSize} bytes)."],
                ]);
            }

            rename($tempMerge, $finalPath);

            // Persist metadata sidecar (tracks ownership & original name)
            $mimeType = @mime_content_type($finalPath) ?: 'application/octet-stream';
            File::put("{$finalPath}.meta.json", json_encode([
                'token'         => $token,
                'user_id'       => $userId,
                'original_name' => $fileName,
                'size'          => $mergedSize,
                'mime_type'     => $mimeType,
                'created_at'    => time(),
            ], JSON_THROW_ON_ERROR));

            // Remove partial slices directory after unlocking
            if ($lockHandle) {
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
                $lockHandle = null;
            }
            File::deleteDirectory($chunkDir);

            // Opportunistic housekeeping of expired uploads
            $this->cleanup();

            return [
                'success'   => true,
                'completed' => true,
                'token'     => $token,
                'name'      => $fileName,
                'size'      => $mergedSize,
                'mime_type' => $mimeType,
            ];
        } finally {
            if ($lockHandle) {
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
            }
        }
    }

    /**
     * Cancel an in-progress upload and delete partial slices.
     */
    public function cancelUpload(string $uploadId, mixed $userId = null): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $uploadId)) {
            return false;
        }

        $chunkDir = $this->uploadsPath() . '/' . basename($uploadId);
        if (!File::isDirectory($chunkDir)) {
            return false;
        }

        $ownerFile = "{$chunkDir}/.owner";
        $currentUserId = $userId ?? Auth::id();
        if (File::exists($ownerFile)) {
            $recordedOwner = trim((string) File::get($ownerFile));
            if ($recordedOwner !== '' && $currentUserId !== null && $recordedOwner !== (string) $currentUserId) {
                return false;
            }
        }

        return File::deleteDirectory($chunkDir);
    }

    /**
     * Resolve a completed upload token to its local file path and metadata.
     * Enforces path traversal protection and authenticated user ownership.
     */
    public function resolveToken(string $token, mixed $userId = null): ?array
    {
        $safeToken = basename($token);
        if ($safeToken === '' || str_ends_with($safeToken, '.meta.json')) {
            return null;
        }

        $filePath = $this->completedPath() . '/' . $safeToken;
        if (!file_exists($filePath) || !is_file($filePath)) {
            return null;
        }

        $metaPath = "{$filePath}.meta.json";
        $meta = [];
        if (file_exists($metaPath)) {
            $decoded = json_decode((string) file_get_contents($metaPath), true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        $currentUserId = $userId ?? Auth::id();
        if (
            array_key_exists('user_id', $meta)
            && $meta['user_id'] !== null
            && $currentUserId !== null
            && (string) $meta['user_id'] !== (string) $currentUserId
        ) {
            return null;
        }

        return [
            'token'     => $safeToken,
            'path'      => $filePath,
            'meta_path' => file_exists($metaPath) ? $metaPath : null,
            'name'      => $meta['original_name'] ?? $safeToken,
            'size'      => $meta['size'] ?? filesize($filePath),
            'mime_type' => $meta['mime_type'] ?? (@mime_content_type($filePath) ?: 'application/octet-stream'),
        ];
    }

    /**
     * Normalize the incoming request value for a chunked-file field.
     */
    public function normalizePayload(mixed $value): array
    {
        if ($value instanceof UploadedFile) {
            return ['type' => 'file', 'file' => $value];
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return ['type' => 'empty'];
            }
            if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    $value = $decoded;
                }
            } else {
                return ['type' => 'token', 'token' => $trimmed, 'name' => null, 'size' => null];
            }
        }

        if (is_array($value)) {
            if (!empty($value['removed'])) {
                return ['type' => 'removed'];
            }
            if (!empty($value['token'])) {
                return [
                    'type'  => 'token',
                    'token' => (string) $value['token'],
                    'name'  => $value['name'] ?? null,
                    'size'  => isset($value['size']) ? (int) $value['size'] : null,
                ];
            }
        }

        return ['type' => 'empty'];
    }

    /**
     * Build the `existing_file` metadata object for edit forms from a model instance.
     */
    public function buildExistingFile(array $field, Model $model): ?array
    {
        $pathColumn = $field['path_column'] ?? $field['key'];
        $path = $model->{$pathColumn} ?? null;

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
     * Process a single `chunked-file` field during store() or update() and update model attributes.
     */
    public function processField(array $field, Model $model, Request $request, array $data = []): void
    {
        if (($field['auto_handle'] ?? true) === false) {
            return;
        }

        $key        = $field['key'];
        $pathColumn = $field['path_column'] ?? $key;
        $nameColumn = $field['name_column'] ?? null;
        $sizeColumn = $field['size_column'] ?? null;
        $diskName   = $field['disk'] ?? config('crud.chunk_upload.disk', 'public');
        $deleteOld  = $field['delete_old'] ?? true;
        $disk       = Storage::disk($diskName);

        $rawValue = $request->hasFile($key)
            ? $request->file($key)
            : ($data[$key] ?? $request->input($key));

        $payload = $this->normalizePayload($rawValue);

        // Case 1: User removed existing file
        if ($payload['type'] === 'removed') {
            $this->deleteStoredFile($disk, $model->{$pathColumn} ?? null, $deleteOld);
            $model->{$pathColumn} = null;
            if ($nameColumn) {
                $model->{$nameColumn} = null;
            }
            if ($sizeColumn) {
                $model->{$sizeColumn} = null;
            }
            return;
        }

        // Case 2: Chunked upload token
        if ($payload['type'] === 'token') {
            $resolved = $this->resolveToken($payload['token']);
            if (!$resolved) {
                return;
            }

            $this->deleteStoredFile($disk, $model->{$pathColumn} ?? null, $deleteOld);

            $originalName = $payload['name'] ?: $resolved['name'];
            $directory    = $this->resolveDirectory($field, $model, $data);
            $targetName   = $this->resolveTargetFilename($field, $originalName, $model, $data);
            $destPath     = $directory !== '' ? "{$directory}/{$targetName}" : $targetName;

            $stream = fopen($resolved['path'], 'rb');
            try {
                $options = isset($field['visibility']) ? ['visibility' => $field['visibility']] : [];
                $disk->put($destPath, $stream, $options);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            // Clean up assembled temp file & metadata sidecar
            @unlink($resolved['path']);
            if (!empty($resolved['meta_path'])) {
                @unlink($resolved['meta_path']);
            }

            $model->{$pathColumn} = $destPath;
            if ($nameColumn) {
                $model->{$nameColumn} = $originalName;
            }
            if ($sizeColumn) {
                $model->{$sizeColumn} = $resolved['size'];
            }
            return;
        }

        // Case 3: Direct multipart UploadedFile fallback
        if ($payload['type'] === 'file') {
            /** @var UploadedFile $file */
            $file = $payload['file'];

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
        }
    }

    /**
     * Delete associated chunked-file assets when a record is permanently deleted.
     */
    public function deleteModelFiles(Model $model, array $fields): void
    {
        // Do not delete stored files for soft-deleted models
        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            return;
        }

        foreach ($fields as $field) {
            if (($field['type'] ?? '') !== 'chunked-file') {
                continue;
            }
            if (($field['auto_handle'] ?? true) === false || ($field['delete_old'] ?? true) === false) {
                continue;
            }

            $pathColumn = $field['path_column'] ?? $field['key'];
            $path       = $model->{$pathColumn} ?? null;
            if (!empty($path) && is_string($path)) {
                $diskName = $field['disk'] ?? config('crud.chunk_upload.disk', 'public');
                $this->deleteStoredFile(Storage::disk($diskName), $path, true);
            }
        }
    }

    /**
     * Delete expired temporary chunk directories and completed uploads.
     *
     * @return array{completed_deleted: int, partial_deleted: int}
     */
    public function cleanup(?int $ttlHours = null): array
    {
        $ttlSeconds = max(1, $ttlHours ?? (int) config('crud.chunk_upload.ttl_hours', 6)) * 3600;
        $now = time();
        $completedDeleted = 0;
        $partialDeleted = 0;

        try {
            $completedDir = $this->completedPath();
            if (File::isDirectory($completedDir)) {
                foreach (File::files($completedDir) as $file) {
                    if ($now - $file->getMTime() > $ttlSeconds) {
                        if (@unlink($file->getRealPath()) && !str_ends_with($file->getFilename(), '.meta.json')) {
                            $completedDeleted++;
                        }
                    }
                }
            }

            $uploadsDir = $this->uploadsPath();
            if (File::isDirectory($uploadsDir)) {
                foreach (File::directories($uploadsDir) as $dir) {
                    $mtime = @filemtime($dir) ?: 0;
                    if ($now - $mtime > $ttlSeconds) {
                        if (File::deleteDirectory($dir)) {
                            $partialDeleted++;
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // Best-effort cleanup
        }

        return [
            'completed_deleted' => $completedDeleted,
            'partial_deleted'   => $partialDeleted,
        ];
    }

    // ── Internal Helpers ─────────────────────────────────────

    protected function validateFileExtension(string $fileName, ?string $fieldAccept = null): void
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // 1. Check global allowlist if configured
        $globalAllowed = array_map(
            fn ($ext) => ltrim(strtolower(trim((string) $ext)), '.'),
            (array) config('crud.chunk_upload.allowed_extensions', [])
        );
        $globalAllowed = array_filter($globalAllowed);

        if (!empty($globalAllowed) && !in_array($extension, $globalAllowed, true)) {
            throw ValidationException::withMessages([
                'file_name' => ["File type '.{$extension}' is not allowed."],
            ]);
        }

        // 2. Check field-level `accept` attribute if it specifies file extensions (e.g. ".mp4,.mov,.dmg")
        if (!empty($fieldAccept)) {
            $tokens = array_filter(array_map('trim', explode(',', $fieldAccept)));
            $extensionTokens = [];
            $hasMimeWildcard = false;

            foreach ($tokens as $token) {
                if (str_starts_with($token, '.')) {
                    $extensionTokens[] = ltrim(strtolower($token), '.');
                } elseif (str_contains($token, '/')) {
                    $hasMimeWildcard = true;
                }
            }

            // Enforce strict extension match when accept contains explicit extensions without generic MIME wildcards
            if (!empty($extensionTokens) && !$hasMimeWildcard && !in_array($extension, $extensionTokens, true)) {
                $allowedList = implode(', ', array_map(fn ($e) => ".{$e}", $extensionTokens));
                throw ValidationException::withMessages([
                    'file_name' => ["Invalid file extension '.{$extension}'. Allowed: {$allowedList}."],
                ]);
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

        $ext  = pathinfo($safeName, PATHINFO_EXTENSION);
        $base = pathinfo($safeName, PATHINFO_FILENAME);
        $suffix = Str::lower(Str::random(8));

        return $ext !== '' ? "{$base}_{$suffix}.{$ext}" : "{$base}_{$suffix}";
    }

    protected function sanitizeFilename(string $filename): string
    {
        $base = basename($filename);
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

