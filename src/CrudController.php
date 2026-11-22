<?php

namespace NagibMahfuj\Crud;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Base controller for config-driven CRUD operations.
 *
 * Extend this controller and implement resourceConfig() to get full
 * index/create/store/show/edit/update/destroy/bulkDestroy/restore/forceDestroy/
 * export/import/importSample/handleAction functionality out of the box.
 */
abstract class CrudController extends Controller
{
    use AuthorizesRequests;

    protected CrudService $crud;

    public function __construct(CrudService $crud)
    {
        $this->crud = $crud;
    }

    /**
     * Define the resource configuration.
     * Sub-controllers MUST implement this.
     */
    abstract protected function resourceConfig(): array;

    /**
     * Public accessor used by background jobs (e.g. ProcessCrudImportJob)
     * to resolve fresh resource config without serializing closures.
     */
    public function getResourceConfigForImport(): array
    {
        return $this->resourceConfig();
    }

    /**
     * Build a base query (optionally with eager-loading).
     * Override in sub-controllers to add scopes, eager loads, etc.
     */
    protected function baseQuery()
    {
        $config = $this->resourceConfig();
        $model  = new $config['model'];

        $query = $model->newQuery();

        if (!empty($config['with'])) {
            $query->with($config['with']);
        }

        if (!empty($config['with_count'])) {
            $query->withCount($config['with_count']);
        }

        return $query;
    }

    /**
     * Optional KPI stat cards rendered above the index table.
     * Override in sub-controllers or define `'stats' => fn ($query) => [...]` in resourceConfig().
     *
     * @return array<int, array{title: string, value: string|int|float, change?: int|float, description?: string, icon?: string}>
     */
    protected function stats($baseQuery): array
    {
        $config = $this->resourceConfig();

        if (isset($config['stats'])) {
            if (is_callable($config['stats'])) {
                return (array) call_user_func($config['stats'], clone $baseQuery);
            }

            if (is_array($config['stats'])) {
                return $config['stats'];
            }
        }

        return [];
    }

    /**
     * Return the Inertia page path prefix (e.g., 'Dashboard/Products').
     */
    protected function pagePrefix(): string
    {
        $config = $this->resourceConfig();

        return $config['page_prefix'] ?? 'Dashboard/' . class_basename($config['model']) . 's';
    }

    /**
     * Resolve the route name prefix for redirects.
     */
    protected function routePrefix(array $config): string
    {
        return $config['route_prefix'] ?? strtolower(class_basename($config['model'])) . 's';
    }

    /**
     * Hook called before saving a model.
     */
    protected function beforeSave($model, array $data, string $action): void
    {
        // Override in sub-controllers
    }

    /**
     * Hook called after saving a model.
     */
    protected function afterSave($model, array $data, string $action): void
    {
        // Override in sub-controllers
    }

    // ── CRUD Methods ──────────────────────────────────────────

    public function index(Request $request)
    {
        $config = $this->resourceConfig();

        $this->authorize('viewAny', $config['model']);

        $baseQuery = $this->baseQuery();
        $stats     = $this->stats($baseQuery);
        $query     = $this->crud->buildIndexQuery($baseQuery, $request, $config);
        $data      = $this->crud->paginate($query, $request, $config['per_page'] ?? 10);

        // Apply display_format if defined in config columns
        $columns = $config['columns'] ?? [];
        if (!empty($columns)) {
            $data->getCollection()->transform(function ($record) use ($columns) {
                foreach ($columns as $column) {
                    if (isset($column['display_format']) && is_callable($column['display_format'])) {
                        $key = $column['key'];
                        $record->setAttribute($key, call_user_func($column['display_format'], $record));
                    }
                }
                return $record;
            });
        }

        return Inertia::render($this->pagePrefix() . '/Index', [
            'data'    => $data,
            'meta'    => $this->crud->buildMeta($config, 'index', null, $request),
            'stats'   => $stats,
            'filters' => $request->only([
                'search', 'sort', 'direction', 'per_page', 'trashed',
                ...collect($config['filters'] ?? [])->map(fn ($f) => "filter_{$f['key']}")->toArray(),
                ...collect($config['filters'] ?? [])->where('type', 'date_range')->flatMap(fn ($f) => ["filter_{$f['key']}_from", "filter_{$f['key']}_to"])->toArray(),
            ]),
        ]);
    }

    public function create()
    {
        $config = $this->resourceConfig();

        $this->authorize('create', $config['model']);

        $fields  = $this->crud->prepareFieldsForFrontend($config['fields'] ?? [], 'create');
        $payload = [
            'meta'   => $this->crud->buildMeta($config, 'create'),
            'fields' => $fields,
            'mode'   => 'create',
        ];

        // Support embedded modal/drawer form loading
        if (request()->header('X-Crud-Form') === '1') {
            return response()->json($payload);
        }

        return Inertia::render($this->pagePrefix() . '/Form', $payload);
    }

    public function store(Request $request)
    {
        $config = $this->resourceConfig();

        $this->authorize('create', $config['model']);

        $fields = $config['fields'] ?? [];
        $rules  = $this->crud->buildValidationRules($fields, null, 'create', '', $request);
        $data   = $request->validate($rules);

        $model = new $config['model'];
        $this->crud->fillModel($model, $data, $fields);
        $this->crud->processUploadedFiles($model, $request, $data, $fields);

        if (in_array('created_by', $model->getFillable(), true)) {
            $model->created_by = Auth::id();
        }
        if (in_array('updated_by', $model->getFillable(), true)) {
            $model->updated_by = Auth::id();
        }

        $this->beforeSave($model, $data, 'create');

        $model->save();

        $this->afterSave($model, $data, 'create');

        $routePrefix = $this->routePrefix($config);

        return redirect()->route("{$routePrefix}.index")
            ->with('success', ($config['resource_name'] ?? 'Record') . ' created successfully.');
    }

    public function show(int|string $id)
    {
        $config = $this->resourceConfig();
        $query  = $this->baseQuery();

        if ($this->crud->usesSoftDeletes($config) && method_exists($query->getModel(), 'bootSoftDeletes')) {
            $query->withTrashed();
        }

        $model = $query->findOrFail($id);

        $this->authorize('view', $model);

        return Inertia::render($this->pagePrefix() . '/Show', [
            'meta'       => $this->crud->buildMeta($config, 'show', $model),
            'record'     => $model,
            'showFields' => $this->crud->prepareShowFields($config, $model),
        ]);
    }

    public function edit(int|string $id)
    {
        $config = $this->resourceConfig();
        $model  = $this->baseQuery()->findOrFail($id);

        $this->authorize('update', $model);

        $fields  = $this->crud->prepareFieldsForFrontend($config['fields'] ?? [], 'edit', $model);
        $payload = [
            'meta'   => $this->crud->buildMeta($config, 'edit', $model),
            'fields' => $fields,
            'record' => $model,
            'mode'   => 'edit',
        ];

        // Support embedded modal/drawer form loading
        if (request()->header('X-Crud-Form') === '1') {
            return response()->json($payload);
        }

        return Inertia::render($this->pagePrefix() . '/Form', $payload);
    }

    public function update(Request $request, int|string $id)
    {
        $config = $this->resourceConfig();
        $model  = (new $config['model'])->findOrFail($id);

        $this->authorize('update', $model);

        $fields = $config['fields'] ?? [];
        $rules  = $this->crud->buildValidationRules($fields, $model, 'update', '', $request);

        if ($request->isMethod('patch')) {
            $rules = collect($rules)->map(function ($fieldRules) {
                return array_map(
                    fn ($r) => is_string($r) && str_starts_with($r, 'required') ? 'sometimes' : $r,
                    $fieldRules
                );
            })->toArray();
        }

        $data = $request->validate($rules);

        $this->crud->fillModel($model, $data, $fields);
        $this->crud->processUploadedFiles($model, $request, $data, $fields);

        if (in_array('updated_by', $model->getFillable(), true)) {
            $model->updated_by = Auth::id();
        }

        $this->beforeSave($model, $data, 'update');

        $model->save();

        $this->afterSave($model, $data, 'update');

        $routePrefix = $this->routePrefix($config);

        return redirect()->route("{$routePrefix}.index")
            ->with('success', ($config['resource_name'] ?? 'Record') . ' updated successfully.');
    }

    public function destroy(int|string $id)
    {
        $config = $this->resourceConfig();
        $model  = (new $config['model'])->findOrFail($id);

        $this->authorize('delete', $model);

        $this->crud->deleteUploadedFiles($model, $config['fields'] ?? [], false);
        $model->delete();

        $routePrefix = $this->routePrefix($config);

        return redirect()->route("{$routePrefix}.index")
            ->with('success', ($config['resource_name'] ?? 'Record') . ' deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $config = $this->resourceConfig();

        $validated = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['required'],
        ]);

        $modelClass = $config['model'];
        $records    = (new $modelClass)->newQuery()->whereKey($validated['ids'])->get();

        foreach ($records as $record) {
            $this->authorize('delete', $record);
        }

        $fields = $config['fields'] ?? [];
        foreach ($records as $record) {
            $this->crud->deleteUploadedFiles($record, $fields, false);
            $record->delete();
        }

        $count       = $records->count();
        $routePrefix = $this->routePrefix($config);
        $label       = $config['resource_name'] ?? 'Record';

        return redirect()->route("{$routePrefix}.index")
            ->with('success', "{$count} {$label}(s) deleted successfully.");
    }

    // ── SoftDeletes Trash Management ──────────────────────────

    public function restore(int|string $id)
    {
        $config = $this->resourceConfig();
        if (!$this->crud->usesSoftDeletes($config)) {
            abort(404);
        }

        $modelClass = $config['model'];
        $model      = $modelClass::withTrashed()->findOrFail($id);

        $this->authorize('restore', $model);

        $model->restore();

        $routePrefix = $this->routePrefix($config);

        return redirect()->route("{$routePrefix}.index")
            ->with('success', ($config['resource_name'] ?? 'Record') . ' restored successfully.');
    }

    public function forceDestroy(int|string $id)
    {
        $config = $this->resourceConfig();
        if (!$this->crud->usesSoftDeletes($config)) {
            abort(404);
        }

        $modelClass = $config['model'];
        $model      = $modelClass::withTrashed()->findOrFail($id);

        $this->authorize('forceDelete', $model);

        $this->crud->deleteUploadedFiles($model, $config['fields'] ?? [], true);
        $model->forceDelete();

        $routePrefix = $this->routePrefix($config);

        return redirect()->route("{$routePrefix}.index")
            ->with('success', ($config['resource_name'] ?? 'Record') . ' permanently deleted.');
    }

    public function bulkRestore(Request $request)
    {
        $config = $this->resourceConfig();
        if (!$this->crud->usesSoftDeletes($config)) {
            abort(404);
        }

        $validated = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['required'],
        ]);

        $modelClass = $config['model'];
        $records    = $modelClass::withTrashed()->whereKey($validated['ids'])->get();

        foreach ($records as $record) {
            $this->authorize('restore', $record);
        }

        foreach ($records as $record) {
            $record->restore();
        }

        $count       = $records->count();
        $routePrefix = $this->routePrefix($config);
        $label       = $config['resource_name'] ?? 'Record';

        return redirect()->route("{$routePrefix}.index")
            ->with('success', "{$count} {$label}(s) restored successfully.");
    }

    public function bulkForceDestroy(Request $request)
    {
        $config = $this->resourceConfig();
        if (!$this->crud->usesSoftDeletes($config)) {
            abort(404);
        }

        $validated = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['required'],
        ]);

        $modelClass = $config['model'];
        $records    = $modelClass::withTrashed()->whereKey($validated['ids'])->get();

        foreach ($records as $record) {
            $this->authorize('forceDelete', $record);
        }

        $fields = $config['fields'] ?? [];
        foreach ($records as $record) {
            $this->crud->deleteUploadedFiles($record, $fields, true);
            $record->forceDelete();
        }

        $count       = $records->count();
        $routePrefix = $this->routePrefix($config);
        $label       = $config['resource_name'] ?? 'Record';

        return redirect()->route("{$routePrefix}.index")
            ->with('success', "{$count} {$label}(s) permanently deleted.");
    }

    // ── Declarative Custom Actions ────────────────────────────

    public function handleAction(Request $request, string $action)
    {
        $config    = $this->resourceConfig();
        $actionDef = collect($config['custom_actions'] ?? [])->firstWhere('key', $action);

        if (!$actionDef || !isset($actionDef['handler']) || !is_callable($actionDef['handler'])) {
            abort(404, "Action '{$action}' is not defined.");
        }

        $scope      = (string) ($actionDef['scope'] ?? 'row');
        $modelClass = $config['model'];
        $permission = $actionDef['permission'] ?? null;

        if ($scope === 'bulk') {
            $validated = $request->validate([
                'ids'   => ['required', 'array', 'min:1'],
                'ids.*' => ['required'],
            ]);
            $records = (new $modelClass)->newQuery()->whereKey($validated['ids'])->get();
        } else {
            $validated = $request->validate([
                'id' => ['required'],
            ]);
            $records = collect([(new $modelClass)->newQuery()->findOrFail($validated['id'])]);
        }

        if ($permission) {
            foreach ($records as $record) {
                $this->authorize((string) $permission, $record);
            }
        }

        $target   = $scope === 'bulk' ? $records : $records->first();
        $response = call_user_func($actionDef['handler'], $target, $request);

        if ($response !== null) {
            return $response;
        }

        return back()->with('success', ($actionDef['label'] ?? 'Action') . ' completed successfully.');
    }

    // ── Export & Import ───────────────────────────────────────

    public function export(Request $request)
    {
        $config = $this->resourceConfig();

        if (!in_array('export', $config['actions'] ?? [], true)) {
            abort(403, 'Export is not enabled for this resource.');
        }

        $this->authorize('export', $config['model']);

        $query    = $this->baseQuery();
        $query    = $this->crud->buildIndexQuery($query, $request, $config);
        $filename = ($config['route_prefix'] ?? 'export') . '-' . date('Y-m-d') . '.csv';

        return $this->crud->exportCsv($query, $config['columns'] ?? [], $filename);
    }

    public function import(Request $request)
    {
        $config = $this->resourceConfig();

        if (!in_array('import', $config['actions'] ?? [], true)) {
            abort(403, 'Import is not enabled for this resource.');
        }

        $this->authorize('import', $config['model']);

        $result = $this->crud->csvImports()->initiate($request, $config, static::class);

        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json($result, $result['queued'] ? 202 : 200);
        }

        $status  = $result['status'];
        $message = $result['queued']
            ? 'Import queued for background processing.'
            : "Imported {$status['successful_rows']} record(s) successfully" . ($status['failed_rows'] > 0 ? " ({$status['failed_rows']} failed)." : '.');

        return back()
            ->with('success', $message)
            ->with('data', [
                'import_id'     => $result['import_id'],
                'import_queued' => $result['queued'],
                'import_status' => $status,
            ]);
    }

    public function importSample()
    {
        $config = $this->resourceConfig();

        if (!in_array('import', $config['actions'] ?? [], true)) {
            abort(403, 'Import is not enabled for this resource.');
        }

        $this->authorize('import', $config['model']);

        return $this->crud->csvImports()->streamSampleCsv($config);
    }
}
