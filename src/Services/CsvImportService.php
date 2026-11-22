<?php

namespace NagibMahfuj\Crud\Services;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use NagibMahfuj\Crud\Events\CrudImportCompleted;
use NagibMahfuj\Crud\Events\CrudImportFailed;
use NagibMahfuj\Crud\Jobs\ProcessCrudImportJob;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Config-driven streaming CSV import engine supporting synchronous execution,
 * background queued jobs for large files, native chunked-upload tokens,
 * Cache-backed progress polling, downloadable failed-row CSV reports,
 * and automatic sample template generation.
 */
class CsvImportService
{
    public function __construct(protected ?ChunkUploadService $chunkUploads = null)
    {
        $this->chunkUploads = $chunkUploads ?? new ChunkUploadService();
    }

    /**
     * Cache key for a given import ID.
     */
    public function cacheKey(string $importId): string
    {
        return "crud:import:{$importId}";
    }

    /**
     * Resolve the native import status endpoint URL.
     */
    public function statusUrl(string $importId): string
    {
        $routeName = config('crud.import.route_name', 'crud.import') . '.status';

        if (Route::has($routeName)) {
            return route($routeName, ['importId' => $importId], false);
        }

        $base = '/' . ltrim((string) config('crud.import.route', '/crud/imports'), '/');

        return rtrim($base, '/') . "/{$importId}/status";
    }

    /**
     * Resolve the native import failed-rows CSV download URL.
     */
    public function errorReportUrl(string $importId): string
    {
        $routeName = config('crud.import.route_name', 'crud.import') . '.errors';

        if (Route::has($routeName)) {
            return route($routeName, ['importId' => $importId], false);
        }

        $base = '/' . ltrim((string) config('crud.import.route', '/crud/imports'), '/');

        return rtrim($base, '/') . "/{$importId}/errors";
    }

    /**
     * Fetch the current Cache-backed progress state for an import ID.
     */
    public function getStatus(string $importId, mixed $userId = null): ?array
    {
        $state = Cache::get($this->cacheKey($importId));

        if (!is_array($state)) {
            return null;
        }

        $currentUserId = $userId ?? Auth::id();
        if (
            array_key_exists('user_id', $state)
            && $state['user_id'] !== null
            && $currentUserId !== null
            && (string) $state['user_id'] !== (string) $currentUserId
        ) {
            return null;
        }

        return $state;
    }

    /**
     * Persist progress state in Cache.
     */
    public function saveStatus(string $importId, array $state): void
    {
        $ttlHours = max(1, (int) config('crud.import.status_ttl_hours', 24));
        Cache::put($this->cacheKey($importId), $state, now()->addHours($ttlHours));
    }

    /**
     * Entry point called by CrudController::import().
     * Accepts either a standard UploadedFile (`file`) or a ChunkUploadService `token` (`chunk_token` / `file`).
     * Decides between sync and queued execution based on `import.mode` and file size.
     *
     * @return array{queued: bool, import_id: string, status: array}
     *
     * @throws ValidationException
     */
    public function initiate(Request $request, array $resourceConfig, string $controllerClass): array
    {
        $importConfig = $resourceConfig['import'] ?? [];
        $diskName     = (string) ($importConfig['disk'] ?? config('crud.import.disk', 'local'));
        $tempDir      = trim((string) ($importConfig['temp_directory'] ?? config('crud.import.temp_directory', 'crud-imports')), '/');
        $importId     = (string) Str::uuid();
        $userId       = Auth::id();

        [$storedPath, $originalName, $fileSize] = $this->storeIncomingImportFile(
            $request,
            $diskName,
            $tempDir,
            $importId,
            $resourceConfig
        );

        $mode          = (string) ($importConfig['mode'] ?? config('crud.import.mode', 'auto'));
        $thresholdKb   = (int) ($importConfig['queue_threshold_kb'] ?? config('crud.import.queue_threshold_kb', 1024));
        $shouldQueue   = $mode === 'queued' || ($mode === 'auto' && $fileSize > ($thresholdKb * 1024));
        $resourceKey   = (string) ($resourceConfig['route_prefix'] ?? 'resource');

        $initialState = [
            'id'                => $importId,
            'resource'          => $resourceKey,
            'user_id'           => $userId,
            'file_name'         => $originalName,
            'status'            => $shouldQueue ? 'queued' : 'processing',
            'total_rows'        => 0,
            'processed_rows'    => 0,
            'successful_rows'   => 0,
            'failed_rows'       => 0,
            'progress'          => 0,
            'errors_sample'     => [],
            'error_report_path' => null,
            'error_report_url'  => null,
            'status_url'        => $this->statusUrl($importId),
            'error_message'     => null,
            'started_at'        => now()->toIso8601String(),
            'finished_at'       => null,
        ];

        $this->saveStatus($importId, $initialState);

        if ($shouldQueue) {
            $job = new ProcessCrudImportJob(
                importId: $importId,
                controllerClass: $controllerClass,
                disk: $diskName,
                filePath: $storedPath,
                userId: $userId,
                originalFilename: $originalName,
            );

            $connection = $importConfig['connection'] ?? config('crud.import.connection');
            $queue      = $importConfig['queue'] ?? config('crud.import.queue');

            if ($connection) {
                $job->onConnection($connection);
            }
            if ($queue) {
                $job->onQueue($queue);
            }

            dispatch($job);

            return [
                'queued'    => true,
                'import_id' => $importId,
                'status'    => $this->getStatus($importId, $userId) ?? $initialState,
            ];
        }

        // Run synchronously
        $finalState = $this->processStoredFile(
            importId: $importId,
            resourceConfig: $resourceConfig,
            diskName: $diskName,
            filePath: $storedPath,
            userId: $userId,
            originalFilename: $originalName,
        );

        return [
            'queued'    => false,
            'import_id' => $importId,
            'status'    => $finalState,
        ];
    }

    /**
     * Stream-process a stored CSV file line by line in O(1) memory.
     * Called directly in sync mode or inside ProcessCrudImportJob in queued mode.
     */
    public function processStoredFile(
        string $importId,
        array $resourceConfig,
        string $diskName,
        string $filePath,
        mixed $userId,
        string $originalFilename,
    ): array {
        $importConfig = $resourceConfig['import'] ?? [];
        $modelClass   = $resourceConfig['model'];
        $resourceKey  = (string) ($resourceConfig['route_prefix'] ?? 'resource');
        $delimiter    = (string) ($importConfig['delimiter'] ?? ',');
        $chunkSize    = max(1, (int) ($importConfig['chunk_size'] ?? config('crud.import.chunk_size', 500)));
        $strategy     = (string) ($importConfig['strategy'] ?? 'insert');
        $uniqueBy     = (array) ($importConfig['unique_by'] ?? []);
        $updateCols   = $importConfig['update_columns'] ?? null;
        $maxErrors    = max(1, (int) config('crud.import.max_errors_sample', 50));
        $tempDir      = trim((string) ($importConfig['temp_directory'] ?? config('crud.import.temp_directory', 'crud-imports')), '/');
        $disk         = Storage::disk($diskName);
        $fullPath     = $disk->path($filePath);

        $state = $this->getStatus($importId, $userId) ?? [
            'id'                => $importId,
            'resource'          => $resourceKey,
            'user_id'           => $userId,
            'file_name'         => $originalFilename,
            'status'            => 'processing',
            'total_rows'        => 0,
            'processed_rows'    => 0,
            'successful_rows'   => 0,
            'failed_rows'       => 0,
            'progress'          => 0,
            'errors_sample'     => [],
            'error_report_path' => null,
            'error_report_url'  => null,
            'status_url'        => $this->statusUrl($importId),
            'error_message'     => null,
            'started_at'        => now()->toIso8601String(),
            'finished_at'       => null,
        ];

        $state['status'] = 'processing';
        $this->saveStatus($importId, $state);

        $errorReportRelPath = "{$tempDir}/errors/{$importId}.csv";
        $errorHandle        = null;

        try {
            if (!file_exists($fullPath)) {
                throw new \RuntimeException('Import file could not be located on storage disk.');
            }

            // Count data rows quickly in streaming pass for accurate progress percentage
            $totalRows = $this->countCsvDataRows($fullPath, $delimiter);
            $state['total_rows'] = $totalRows;
            $this->saveStatus($importId, $state);

            $handle = fopen($fullPath, 'rb');
            if (!$handle) {
                throw new \RuntimeException('Unable to open import file for reading.');
            }

            $rawHeaders = fgetcsv($handle, 0, $delimiter, '"', '\\');
            if (!is_array($rawHeaders) || empty($rawHeaders)) {
                fclose($handle);
                throw new \RuntimeException('CSV file appears to be empty or missing a header row.');
            }

            // Strip UTF-8 BOM from first header cell & normalize
            $headers = array_map(fn ($h) => $this->cleanCell((string) $h), $rawHeaders);
            $columnMap = $this->resolveColumnMap($headers, $importConfig, $resourceConfig);

            $batch         = [];
            $processedRows = 0;
            $successRows   = 0;
            $failedRows    = 0;
            $errorsSample  = [];
            $lineNumber    = 1;

            while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                $lineNumber++;

                // Skip completely empty lines
                if (empty($row) || (count($row) === 1 && trim((string) ($row[0] ?? '')) === '')) {
                    continue;
                }

                $processedRows++;

                [$mapped, $rowError] = $this->mapAndValidateRow($row, $headers, $columnMap, $importConfig);

                if ($rowError !== null) {
                    $failedRows++;
                    if (count($errorsSample) < $maxErrors) {
                        $errorsSample[] = [
                            'row'     => $lineNumber,
                            'message' => $rowError,
                        ];
                    }
                    $this->writeFailedRow($errorHandle, $disk, $errorReportRelPath, $headers, $row, $rowError);
                } else {
                    $batch[] = $mapped;
                }

                if (count($batch) >= $chunkSize) {
                    $inserted = $this->flushBatch($modelClass, $batch, $strategy, $uniqueBy, $updateCols, $userId);
                    $successRows += $inserted;
                    $batch = [];

                    $state['processed_rows']  = $processedRows;
                    $state['successful_rows'] = $successRows;
                    $state['failed_rows']     = $failedRows;
                    $state['errors_sample']   = $errorsSample;
                    $state['progress']        = $totalRows > 0 ? min(99, (int) round(($processedRows / $totalRows) * 100)) : 99;
                    $this->saveStatus($importId, $state);
                }
            }

            fclose($handle);

            if (!empty($batch)) {
                $inserted = $this->flushBatch($modelClass, $batch, $strategy, $uniqueBy, $updateCols, $userId);
                $successRows += $inserted;
            }

            if (is_resource($errorHandle)) {
                fclose($errorHandle);
            }

            $state['status']            = 'completed';
            $state['total_rows']        = max($totalRows, $processedRows);
            $state['processed_rows']    = $processedRows;
            $state['successful_rows']   = $successRows;
            $state['failed_rows']       = $failedRows;
            $state['progress']          = 100;
            $state['errors_sample']     = $errorsSample;
            $state['error_report_path'] = $failedRows > 0 ? $errorReportRelPath : null;
            $state['error_report_disk'] = $failedRows > 0 ? $diskName : null;
            $state['error_report_url']  = $failedRows > 0 ? $this->errorReportUrl($importId) : null;
            $state['finished_at']       = now()->toIso8601String();
            $this->saveStatus($importId, $state);

            // Clean up uploaded source CSV (keep error CSV until TTL if failures occurred)
            if ($disk->exists($filePath)) {
                $disk->delete($filePath);
            }

            CrudImportCompleted::dispatch($importId, $resourceKey, $userId, $state);

            return $state;
        } catch (\Throwable $e) {
            if (is_resource($errorHandle)) {
                fclose($errorHandle);
            }

            $state['status']        = 'failed';
            $state['error_message'] = $e->getMessage();
            $state['finished_at']   = now()->toIso8601String();
            $this->saveStatus($importId, $state);

            CrudImportFailed::dispatch($importId, $resourceKey, $userId, $e->getMessage(), $state);

            throw $e;
        }
    }

    /**
     * Stream a downloadable sample CSV file derived from `import.columns` (or `fields`).
     */
    public function streamSampleCsv(array $resourceConfig): StreamedResponse
    {
        $importConfig = $resourceConfig['import'] ?? [];
        $columns      = $importConfig['columns'] ?? [];
        $headers      = [];

        if (!empty($columns)) {
            foreach ($columns as $csvHeader => $def) {
                $headers[] = is_int($csvHeader)
                    ? (is_array($def) ? ($def['header'] ?? $def['attribute'] ?? '') : (string) $def)
                    : (string) $csvHeader;
            }
        } else {
            foreach ($resourceConfig['fields'] ?? [] as $field) {
                if (!in_array($field['type'] ?? '', ['hidden', 'repeater', 'file', 'image', 'chunked-file'], true)) {
                    $headers[] = $field['key'];
                }
            }
        }

        $sampleRows = $importConfig['sample_rows'] ?? [];
        $filename   = ($resourceConfig['route_prefix'] ?? 'import') . '-sample.csv';

        return response()->stream(
            function () use ($headers, $sampleRows) {
                $out = fopen('php://output', 'w');
                fputs($out, "\xEF\xBB\xBF");
                fputcsv($out, $headers);
                foreach ($sampleRows as $row) {
                    fputcsv($out, array_values((array) $row));
                }
                fclose($out);
            },
            200,
            [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]
        );
    }

    // ── Internal Helpers ─────────────────────────────────────

    /**
     * @return array{0: string, 1: string, 2: int} [storedPath, originalName, fileSize]
     */
    protected function storeIncomingImportFile(
        Request $request,
        string $diskName,
        string $tempDir,
        string $importId,
        array $resourceConfig
    ): array {
        $disk = Storage::disk($diskName);

        // Option A: Chunked upload token (for very large CSVs bypassing proxy/PHP limits)
        $tokenInput = $request->input('chunk_token');
        if (!$tokenInput && !$request->hasFile('file')) {
            $rawFile = $request->input('file');
            $payload = $this->chunkUploads->normalizePayload($rawFile);
            if ($payload['type'] === 'token') {
                $tokenInput = $payload['token'];
            }
        }

        if (!empty($tokenInput)) {
            $resolved = $this->chunkUploads->resolveToken((string) $tokenInput);
            if (!$resolved) {
                throw ValidationException::withMessages([
                    'file' => ['The uploaded file token is invalid or has expired. Please upload again.'],
                ]);
            }

            $destPath = "{$tempDir}/{$importId}.csv";
            $stream   = fopen($resolved['path'], 'rb');
            try {
                $disk->put($destPath, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $size = (int) $resolved['size'];
            $name = (string) $resolved['name'];

            @unlink($resolved['path']);
            if (!empty($resolved['meta_path'])) {
                @unlink($resolved['meta_path']);
            }

            return [$destPath, $name, $size];
        }

        // Option B: Direct multipart upload
        $maxMb = max(1, (int) ($resourceConfig['import_max_size_mb'] ?? 100));
        $maxKb = $maxMb * 1024;

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', "max:{$maxKb}"],
        ]);

        /** @var UploadedFile $file */
        $file     = $request->file('file');
        $destPath = $file->storeAs($tempDir, "{$importId}.csv", $diskName);

        return [$destPath, $file->getClientOriginalName(), (int) $file->getSize()];
    }

    protected function countCsvDataRows(string $fullPath, string $delimiter): int
    {
        $handle = fopen($fullPath, 'rb');
        if (!$handle) {
            return 0;
        }

        $count = 0;
        // Skip header
        fgetcsv($handle, 0, $delimiter, '"', '\\');
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if (empty($row) || (count($row) === 1 && trim((string) ($row[0] ?? '')) === '')) {
                continue;
            }
            $count++;
        }
        fclose($handle);

        return $count;
    }

    protected function resolveColumnMap(array $headers, array $importConfig, array $resourceConfig): array
    {
        $configured = $importConfig['columns'] ?? [];
        $headerIndex = [];
        foreach ($headers as $idx => $name) {
            $headerIndex[strtolower(trim($name))] = $idx;
        }

        $map = [];

        if (!empty($configured)) {
            foreach ($configured as $csvHeader => $def) {
                if (is_string($def)) {
                    $def = ['attribute' => $def];
                }
                $lookupName = is_int($csvHeader) ? ($def['header'] ?? $def['attribute']) : $csvHeader;
                $normKey    = strtolower(trim((string) $lookupName));
                $attr       = (string) ($def['attribute'] ?? $lookupName);

                $map[] = [
                    'index'     => $headerIndex[$normKey] ?? null,
                    'header'    => (string) $lookupName,
                    'attribute' => $attr,
                    'rules'     => $def['rules'] ?? null,
                    'cast'      => $def['cast'] ?? null,
                    'default'   => $def['default'] ?? null,
                ];
            }

            return $map;
        }

        // Default fallback: match CSV headers directly against fillable / field keys
        $fieldMap = [];
        foreach ($resourceConfig['fields'] ?? [] as $field) {
            $fieldMap[strtolower($field['key'])] = $field;
        }

        foreach ($headers as $idx => $header) {
            $norm = strtolower($header);
            $field = $fieldMap[$norm] ?? null;
            $map[] = [
                'index'     => $idx,
                'header'    => $header,
                'attribute' => $field['key'] ?? $header,
                'rules'     => $field['rules'] ?? null,
                'cast'      => null,
                'default'   => $field['default'] ?? null,
            ];
        }

        return $map;
    }

    /**
     * @return array{0: array, 1: ?string}
     */
    protected function mapAndValidateRow(array $row, array $headers, array $columnMap, array $importConfig): array
    {
        $mapped = [];
        $rules  = [];

        foreach ($columnMap as $col) {
            $raw = ($col['index'] !== null && array_key_exists($col['index'], $row))
                ? $this->cleanCell((string) $row[$col['index']])
                : null;

            if ($raw === '' || $raw === null) {
                $val = $col['default'];
            } else {
                $val = $this->castValue($raw, $col['cast']);
            }

            $mapped[$col['attribute']] = $val;

            if (!empty($col['rules'])) {
                $rules[$col['attribute']] = $col['rules'];
            }
        }

        if (isset($importConfig['transform']) && is_callable($importConfig['transform'])) {
            $mapped = call_user_func($importConfig['transform'], $mapped, $row, $headers);
        }

        if (!empty($rules)) {
            $validator = Validator::make($mapped, $rules);
            if ($validator->fails()) {
                return [$mapped, $validator->errors()->first()];
            }
        }

        return [$mapped, null];
    }

    protected function flushBatch(
        string $modelClass,
        array $rows,
        string $strategy,
        array $uniqueBy,
        ?array $updateCols,
        mixed $userId
    ): int {
        $model     = new $modelClass();
        $fillable  = $model->getFillable();
        $usesTs    = $model->usesTimestamps();
        $now       = now();
        $prepared  = [];

        foreach ($rows as $row) {
            if (!empty($fillable)) {
                $row = array_intersect_key($row, array_flip($fillable));
            }
            if ($userId !== null && in_array('created_by', $fillable, true) && !array_key_exists('created_by', $row)) {
                $row['created_by'] = $userId;
            }
            if ($userId !== null && in_array('updated_by', $fillable, true) && !array_key_exists('updated_by', $row)) {
                $row['updated_by'] = $userId;
            }
            if ($usesTs && $strategy !== 'model') {
                $row[$model->getCreatedAtColumn()] ??= $now;
                $row[$model->getUpdatedAtColumn()] ??= $now;
            }
            $prepared[] = $row;
        }

        if ($strategy === 'model') {
            $count = 0;
            foreach ($prepared as $attrs) {
                $modelClass::create($attrs);
                $count++;
            }
            return $count;
        }

        if ($strategy === 'upsert' && !empty($uniqueBy)) {
            $cols = $updateCols ?? array_values(array_diff(array_keys($prepared[0]), $uniqueBy, [$model->getCreatedAtColumn()]));
            $modelClass::query()->upsert($prepared, $uniqueBy, $cols);
            return count($prepared);
        }

        if ($strategy === 'insert_or_ignore') {
            return (int) $modelClass::query()->insertOrIgnore($prepared);
        }

        $modelClass::query()->insert($prepared);

        return count($prepared);
    }

    protected function writeFailedRow(&$handle, $disk, string $relPath, array $headers, array $row, string $error): void
    {
        if (!is_resource($handle)) {
            $fullPath = $disk->path($relPath);
            $dir      = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $handle = fopen($fullPath, 'wb');
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [...$headers, '_import_error']);
        }

        fputcsv($handle, [...$row, $error]);
    }

    protected function cleanCell(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

        return trim($value);
    }

    protected function castValue(string $value, ?string $cast): mixed
    {
        return match ($cast) {
            'int', 'integer' => (int) $value,
            'float', 'double', 'decimal' => (float) $value,
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json', 'array' => json_decode($value, true),
            default => $value,
        };
    }
}
