<?php

namespace NagibMahfuj\Crud;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use NagibMahfuj\Crud\Rules\ChunkedFileRule;
use NagibMahfuj\Crud\Services\ChunkUploadService;

/**
 * Stateless CRUD service.
 *
 * Handles query building (search, filters, sorting), pagination,
 * validation rule generation, model filling, chunked-file processing,
 * CSV exporting, and building the normalized frontend meta array.
 */
class CrudService
{
    protected ChunkUploadService $chunkUploads;

    public function __construct(?ChunkUploadService $chunkUploads = null)
    {
        $this->chunkUploads = $chunkUploads ?? new ChunkUploadService();
    }

    /**
     * Build a query from the resource config and request parameters.
     * Handles search, filters, and sorting.
     */
    public function buildIndexQuery(Builder $query, Request $request, array $config): Builder
    {
        $separator = config('crud.default_separator', '--');

        // ── Search ────────────────────────────────────────────
        if ($request->filled('search') && !empty($config['searchable'] ?? [])) {
            $search = (string) $request->input('search');
            $query->where(function (Builder $q) use ($search, $config) {
                foreach ($config['searchable'] as $column) {
                    if (str_contains($column, '.')) {
                        // Relationship search (e.g., 'contestant.name')
                        [$relation, $field] = explode('.', $column, 2);
                        $q->orWhereHas($relation, function ($rq) use ($field, $search) {
                            $rq->where($field, 'like', "%{$search}%");
                        });
                    } else {
                        $q->orWhere($column, 'like', "%{$search}%");
                    }
                }
            });
        }

        // ── Filters ───────────────────────────────────────────
        foreach ($config['filters'] ?? [] as $filter) {
            $key      = $filter['key'];
            $type     = $filter['type'] ?? 'select';
            $column   = $filter['column'] ?? $key;
            $relation = $filter['relation'] ?? null;

            if ($type === 'boolean' && $request->filled("filter_{$key}")) {
                $value = $request->input("filter_{$key}");
                $bool  = $value === 'true' || $value === '1' || $value === true;
                $this->applyWhere($query, $relation, fn (Builder $q) => $q->where($column, $bool));
            } elseif ($type === 'text' && $request->filled("filter_{$key}")) {
                $value = (string) $request->input("filter_{$key}");
                $this->applyWhere($query, $relation, fn (Builder $q) => $q->where($column, 'like', "%{$value}%"));
            } elseif ($type === 'select' && $request->filled("filter_{$key}")) {
                $value           = $request->input("filter_{$key}");
                $filterSeparator = $filter['separator'] ?? $separator;

                if (is_string($value) && $filterSeparator !== '' && str_contains($value, $filterSeparator)) {
                    $value = array_values(array_filter(explode($filterSeparator, $value), fn ($v) => $v !== ''));
                }

                $this->applyWhere($query, $relation, function (Builder $q) use ($column, $value) {
                    if (is_array($value)) {
                        $q->whereIn($column, $value);
                    } else {
                        $q->where($column, $value);
                    }
                });
            } elseif ($type === 'date_range') {
                if ($request->filled("filter_{$key}_from")) {
                    $from = $request->input("filter_{$key}_from");
                    $this->applyWhere($query, $relation, fn (Builder $q) => $q->whereDate($column, '>=', $from));
                }
                if ($request->filled("filter_{$key}_to")) {
                    $to = $request->input("filter_{$key}_to");
                    $this->applyWhere($query, $relation, fn (Builder $q) => $q->whereDate($column, '<=', $to));
                }
            } elseif ($type === 'date' && $request->filled("filter_{$key}")) {
                $date = $request->input("filter_{$key}");
                $this->applyWhere($query, $relation, fn (Builder $q) => $q->whereDate($column, $date));
            }
        }

        // ── Sorting ───────────────────────────────────────────
        $requestedSort = $request->filled('sort') ? (string) $request->input('sort') : null;
        $sortKey       = $requestedSort ?? ($config['default_sort'] ?? null);
        $rawDirection  = strtolower((string) $request->input('direction', $config['default_direction'] ?? 'desc'));
        $sortDirection = $rawDirection === 'asc' ? 'asc' : 'desc';

        $sortableColumns = collect($config['columns'] ?? [])
            ->filter(fn ($col) => $col['sortable'] ?? false)
            ->pluck('key')
            ->toArray();

        if ($requestedSort !== null && in_array($requestedSort, $sortableColumns, true)) {
            $query->orderBy($requestedSort, $sortDirection);
        } elseif ($requestedSort === null && !empty($sortKey)) {
            $query->orderBy($sortKey, $sortDirection);
        } else {
            $model = $query->getModel();
            $fallbackColumn = $model->usesTimestamps() ? $model->getCreatedAtColumn() : $model->getKeyName();
            $query->orderBy($fallbackColumn, $sortDirection);
        }

        return $query;
    }

    /**
     * Get paginated results with query string appended and safe per_page bounds.
     */
    public function paginate(Builder $query, Request $request, int $defaultPerPage = 10): LengthAwarePaginator
    {
        $maxPerPage = max(1, (int) config('crud.max_per_page', 100));
        $requested  = $request->input('per_page', $defaultPerPage);
        $perPage    = is_numeric($requested) ? (int) $requested : $defaultPerPage;
        $perPage    = max(1, min($perPage, $maxPerPage));

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Build validation rules from the resource config fields.
     *
     * @param  array       $fields      Resource field definitions
     * @param  Model|null  $model       Existing model (for update rules)
     * @param  string      $action      'create' or 'update'
     * @param  string      $prefix      Key prefix for nested fields
     */
    public function buildValidationRules(array $fields, ?Model $model = null, string $action = 'create', string $prefix = ''): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $key        = $prefix ? "{$prefix}.{$field['key']}" : $field['key'];
            $type       = $field['type'] ?? 'text';
            $fieldRules = $field['rules'] ?? '';

            if ($action === 'update' && isset($field['rules_update'])) {
                $fieldRules = $field['rules_update'];
            }

            if (is_string($fieldRules)) {
                $fieldRules = array_values(array_filter(explode('|', $fieldRules), fn ($r) => $r !== ''));
            } else {
                $fieldRules = array_values((array) $fieldRules);
            }

            // On update, replace 'required' with 'sometimes' if the field is optional
            if ($action === 'update' && ($field['optional_on_update'] ?? false)) {
                $fieldRules = array_map(fn ($r) => is_string($r) && $r === 'required' ? 'sometimes' : $r, $fieldRules);
            }

            // Dedicated validation handling for chunked-file fields
            if ($type === 'chunked-file') {
                $isRequired = in_array('required', $fieldRules, true)
                    || (($field['required'] ?? false) && !($action === 'update' && ($field['optional_on_update'] ?? false)));

                // Strip raw string presence rules handled by ChunkedFileRule
                $remainingRules = array_values(array_filter(
                    $fieldRules,
                    fn ($r) => !is_string($r) || !in_array($r, ['required', 'nullable', 'sometimes'], true)
                ));

                $pathColumn      = $field['path_column'] ?? $field['key'];
                $hasExistingFile = $action === 'update' && $model !== null && !empty($model->{$pathColumn});

                array_unshift($remainingRules, new ChunkedFileRule(
                    chunkUploads: $this->chunkUploads,
                    label: $field['label'] ?? $field['key'],
                    required: $isRequired,
                    hasExistingFile: $hasExistingFile,
                ));

                $rules[$key] = $remainingRules;
                continue;
            }

            // Handle unique rules with model exclusion on update
            if ($model) {
                $fieldRules = array_map(function ($rule) use ($model, $field) {
                    if (is_string($rule) && str_contains($rule, '{id}')) {
                        return str_replace('{id}', (string) $model->getKey(), $rule);
                    }
                    if (is_string($rule) && str_starts_with($rule, 'unique:')) {
                        $parts  = explode(',', substr($rule, 7));
                        $table  = $parts[0] ?? '';
                        $column = $parts[1] ?? $field['key'];
                        if (count($parts) <= 2) {
                            return "unique:{$table},{$column},{$model->getKey()},{$model->getKeyName()}";
                        }
                    }
                    return $rule;
                }, $fieldRules);
            } else {
                $fieldRules = array_map(function ($rule) {
                    if (is_string($rule) && str_contains($rule, ',{id}')) {
                        return str_replace(',{id}', '', $rule);
                    }
                    if (is_string($rule) && str_contains($rule, '{id}')) {
                        return str_replace('{id}', 'NULL', $rule);
                    }
                    return $rule;
                }, $fieldRules);
            }

            $rules[$key] = $fieldRules;

            // Recursively add rules for repeater fields
            if ($type === 'repeater' && !empty($field['fields'])) {
                $subRules = $this->buildValidationRules($field['fields'], $model, $action, "{$key}.*");
                $rules    = array_merge($rules, $subRules);
            }
        }

        return $rules;
    }

    /**
     * Fill model attributes from validated data based on field config.
     */
    public function fillModel(Model $model, array $data, array $fields): Model
    {
        foreach ($fields as $field) {
            $key  = $field['key'];
            $type = $field['type'] ?? 'text';

            if (
                !array_key_exists($key, $data)
                || $type === 'repeater'
                || ($field['ignore_on_save'] ?? false)
            ) {
                continue;
            }

            // Chunked files with auto_handle enabled are persisted via processChunkedFiles()
            if ($type === 'chunked-file' && ($field['auto_handle'] ?? true) !== false) {
                continue;
            }

            if ($type === 'password' && empty($data[$key])) {
                continue;
            }

            if (in_array($type, ['toggle', 'checkbox'], true)) {
                $model->{$key} = filter_var($data[$key], FILTER_VALIDATE_BOOLEAN);
                continue;
            }

            $model->{$key} = $data[$key];
        }

        return $model;
    }

    /**
     * Persist all auto-handled `chunked-file` fields onto disk and map model attributes.
     */
    public function processChunkedFiles(Model $model, Request $request, array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (($field['type'] ?? '') !== 'chunked-file') {
                continue;
            }

            $this->chunkUploads->processField($field, $model, $request, $data);
        }
    }

    /**
     * Clean up stored `chunked-file` files for a model being deleted.
     */
    public function deleteChunkedFiles(Model $model, array $fields): void
    {
        $this->chunkUploads->deleteModelFiles($model, $fields);
    }

    /**
     * Prepare fields for Inertia frontend rendering:
     * - Filters hidden / hide_on_create / hide_on_edit
     * - Strips non-serializable Closures
     * - Enriches `chunked-file` fields with endpoint, limits, signed upload_config, and existing_file
     */
    public function prepareFieldsForFrontend(array $fields, string $mode = 'create', ?Model $record = null): array
    {
        $prepared = [];

        foreach ($fields as $field) {
            if (($field['hidden'] ?? false) === true) {
                continue;
            }
            if ($mode === 'create' && ($field['hide_on_create'] ?? false) === true) {
                continue;
            }
            if ($mode === 'edit' && ($field['hide_on_edit'] ?? false) === true) {
                continue;
            }

            $sanitized = $this->sanitizeFieldForFrontend($field, $mode, $record);

            if (($sanitized['type'] ?? '') === 'repeater' && !empty($field['fields'])) {
                $sanitized['fields'] = $this->prepareFieldsForFrontend($field['fields'], $mode, null);
            }

            $prepared[] = $sanitized;
        }

        return $prepared;
    }

    /**
     * Export data as CSV using column definitions from config.
     *
     * @param  Builder  $query
     * @param  array    $columns   Column definitions from config
     * @param  string   $filename  Export filename
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportCsv(Builder $query, array $columns, string $filename)
    {
        $headers = collect($columns)->pluck('label')->toArray();

        return response()->stream(
            function () use ($query, $headers, $columns) {
                $handle = fopen('php://output', 'w');
                // BOM for Excel UTF-8 compatibility
                fputs($handle, "\xEF\xBB\xBF");
                fputcsv($handle, $headers);

                $query->chunk(1000, function ($records) use ($handle, $columns) {
                    foreach ($records as $record) {
                        $row = [];
                        foreach ($columns as $column) {
                            if (isset($column['export_format']) && is_callable($column['export_format'])) {
                                $row[] = call_user_func($column['export_format'], $record);
                            } else {
                                $val = data_get($record, $column['key'], '');
                                if (is_array($val) || is_object($val)) {
                                    $val = json_encode($val);
                                }
                                $row[] = $val;
                            }
                        }
                        fputcsv($handle, $row);
                    }
                });

                fclose($handle);
            },
            200,
            [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]
        );
    }

    /**
     * Build the normalized metadata array passed to Inertia pages (`Index`, `Form`, `Show`, `AppLayout`).
     *
     * @param  array       $config   Resource configuration from controller
     * @param  string      $context  One of 'index', 'create', 'edit', 'show'
     * @param  Model|null  $record   Current record (for 'edit' / 'show')
     */
    public function buildMeta(array $config, string $context = 'index', ?Model $record = null): array
    {
        // Strip any closures from columns before sending to frontend
        $columns = array_map(function ($col) {
            unset($col['export_format'], $col['display_format']);

            return $col;
        }, $config['columns'] ?? []);

        $actions = array_values(array_unique($config['actions'] ?? []));

        // Filter actions based on policy permissions
        if (isset($config['model'])) {
            $user       = Auth::user();
            $modelClass = $config['model'];

            if ($user) {
                $actions = array_values(array_filter($actions, function ($action) use ($user, $modelClass, $record) {
                    $ability = match ($action) {
                        'create' => 'create',
                        'edit'   => 'update',
                        'delete' => 'delete',
                        'show'   => 'view',
                        default  => $action,
                    };

                    // Class-level abilities do not need a model instance
                    if (in_array($ability, ['viewAny', 'create', 'export', 'import'], true)) {
                        return $user->can($ability, $modelClass);
                    }

                    return $user->can($ability, $record ?? new $modelClass());
                }));
            }
        }

        // Resolve names & titles
        $modelBaseName  = isset($config['model']) ? class_basename($config['model']) : 'Record';
        $resourceName   = $config['resource_name'] ?? Str::title(Str::snake($modelBaseName, ' '));
        $resourcePlural = $config['resource_plural'] ?? Str::plural($resourceName);
        $routePrefix    = $config['route_prefix'] ?? Str::kebab(Str::plural($modelBaseName));

        $indexTitle = $config['title'] ?? $resourcePlural;
        $title = match ($context) {
            'create' => $config['create_title'] ?? "Create {$resourceName}",
            'edit'   => $config['edit_title'] ?? "Edit {$resourceName}",
            'show'   => $config['show_title'] ?? "{$resourceName} Details",
            default  => $indexTitle,
        };

        // Resolve URLs
        $baseRoute   = $this->resolveBaseRoute($config, $routePrefix);
        $canCreate   = in_array('create', $actions, true);
        $canDelete   = in_array('delete', $actions, true);
        $canExport   = in_array('export', $actions, true);
        $canImport   = in_array('import', $actions, true);

        $exportRoute = $canExport ? $this->resolveActionRoute($config, 'export_route', "{$routePrefix}.export", "{$baseRoute}/export") : null;
        $importRoute = $canImport ? $this->resolveActionRoute($config, 'import_route', "{$routePrefix}.import", "{$baseRoute}/import") : null;
        $bulkDestroy = $canDelete ? $this->resolveActionRoute($config, 'bulk_destroy_route', "{$routePrefix}.bulk-destroy", "{$baseRoute}/bulk-destroy") : null;
        $createRoute = $canCreate ? $this->resolveActionRoute($config, 'create_route', "{$routePrefix}.create", "{$baseRoute}/create") : null;

        $updateRoute = null;
        if ($record !== null && $record->getKey() !== null) {
            $updateRoute = Route::has("{$routePrefix}.update")
                ? route("{$routePrefix}.update", $record->getKey(), false)
                : "{$baseRoute}/{$record->getKey()}";
        }

        // Resolve breadcrumbs
        $breadcrumbs = $this->resolveBreadcrumbs($config, $indexTitle, $baseRoute, $context);

        return [
            'resource_name'      => $resourceName,
            'resource_plural'    => $resourcePlural,
            'title'              => $title,
            'description'        => $config['description'] ?? null,
            'route_prefix'       => $routePrefix,
            'base_route'         => $baseRoute,
            'routes'             => [
                'index'        => $baseRoute,
                'create'       => $createRoute,
                'store'        => $baseRoute,
                'update'       => $updateRoute,
                'export'       => $exportRoute,
                'import'       => $importRoute,
                'bulk_destroy' => $bulkDestroy,
                'chunk_upload' => config('crud.chunk_upload.enabled', true) ? $this->chunkUploads->endpoint() : null,
            ],
            'breadcrumbs'        => $breadcrumbs,
            'columns'            => $columns,
            'filters'            => $config['filters'] ?? [],
            'actions'            => $actions,
            'row_selection'      => (bool) ($config['row_selection'] ?? false),
            'per_page'           => (int) ($config['per_page'] ?? 10),
            'default_separator'  => (string) config('crud.default_separator', '--'),
            'import_route'       => $importRoute,
            'import_format_url'  => $config['import_format_url'] ?? null,
            'import_accept'      => $config['import_accept'] ?? '.csv,.xlsx,.xls',
            'import_max_size_mb' => (int) ($config['import_max_size_mb'] ?? 100),
        ];
    }

    // ── Internal Helpers ─────────────────────────────────────

    protected function applyWhere(Builder $query, ?string $relation, Closure $callback): void
    {
        if ($relation) {
            $query->whereHas($relation, $callback);
        } else {
            $callback($query);
        }
    }

    protected function sanitizeFieldForFrontend(array $field, string $mode, ?Model $record): array
    {
        $sanitized = [];

        foreach ($field as $k => $v) {
            // Strip Closures or backend-only persistence keys
            if ($v instanceof Closure) {
                continue;
            }
            if (in_array($k, ['rules', 'rules_update', 'ignore_on_save', 'disk', 'directory', 'filename', 'visibility', 'delete_old', 'preserve_filename', 'auto_handle'], true)) {
                continue;
            }
            $sanitized[$k] = $v;
        }

        if (($field['type'] ?? '') === 'chunked-file') {
            $sanitized['endpoint']      = $this->chunkUploads->endpoint($field['endpoint'] ?? null);
            $sanitized['chunk_size_mb'] = (int) ($field['chunk_size_mb'] ?? config('crud.chunk_upload.chunk_size_mb', 5));
            $sanitized['max_size_mb']   = (int) ($field['max_size_mb'] ?? config('crud.chunk_upload.max_file_size_mb', 512));
            $sanitized['max_retries']   = (int) ($field['max_retries'] ?? config('crud.chunk_upload.max_retries', 3));
            $sanitized['upload_config'] = $this->chunkUploads->signFieldConfig($field);

            if ($mode === 'edit' && $record !== null) {
                $sanitized['existing_file'] = $this->chunkUploads->buildExistingFile($field, $record);
            }
        }

        return $sanitized;
    }

    protected function resolveBaseRoute(array $config, string $routePrefix): string
    {
        if (!empty($config['base_route'])) {
            return rtrim((string) $config['base_route'], '/');
        }

        if (Route::has("{$routePrefix}.index")) {
            return rtrim(route("{$routePrefix}.index", [], false), '/') ?: '/';
        }

        $urlPrefix = rtrim((string) ($config['url_prefix'] ?? config('crud.url_prefix', '/dashboard')), '/');

        return ($urlPrefix !== '' ? $urlPrefix : '') . '/' . ltrim($routePrefix, '/');
    }

    protected function resolveActionRoute(array $config, string $configKey, string $routeName, string $fallback): string
    {
        if (!empty($config[$configKey])) {
            return (string) $config[$configKey];
        }

        if (Route::has($routeName)) {
            return route($routeName, [], false);
        }

        return $fallback;
    }

    protected function resolveBreadcrumbs(array $config, string $indexTitle, string $baseRoute, string $context): array
    {
        if (isset($config['breadcrumbs']) && is_array($config['breadcrumbs'])) {
            $crumbs = $config['breadcrumbs'];
        } else {
            $crumbs = [];
            $root   = config('crud.breadcrumbs.root');

            if (is_array($root) && !empty($root['title'])) {
                $crumbs[] = [
                    'title' => (string) $root['title'],
                    'href'  => (string) ($root['href'] ?? '/'),
                ];
            }

            $crumbs[] = [
                'title' => $indexTitle,
                'href'  => $baseRoute,
            ];
        }

        if ($context === 'create') {
            $crumbs[] = ['title' => 'Create', 'href' => ''];
        } elseif ($context === 'edit') {
            $crumbs[] = ['title' => 'Edit', 'href' => ''];
        } elseif ($context === 'show') {
            $crumbs[] = ['title' => 'Details', 'href' => ''];
        }

        return $crumbs;
    }
}
