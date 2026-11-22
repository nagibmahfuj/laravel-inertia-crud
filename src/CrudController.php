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
 * index/create/store/edit/update/destroy/bulkDestroy/export functionality for free.
 *
 * Override any method for custom behaviour.
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
     *
     * Expected keys:
     *  - model: string (FQ class)
     *  - resource_name: string
     *  - resource_plural: string (optional)
     *  - title: string|null (optional index title override)
     *  - description: string|null (optional header subtitle)
     *  - route_prefix: string
     *  - base_route: string|null (optional URL override)
     *  - page_prefix: string
     *  - per_page: int
     *  - searchable: string[]
     *  - default_sort: string
     *  - default_direction: 'asc'|'desc'
     *  - actions: string[] ('create', 'edit', 'delete', 'show', 'export', 'import')
     *  - columns: array[]
     *  - filters: array[]
     *  - fields: array[]
     *  - with: string[] (eager loads)
     *  - with_count: string[] (count relations)
     *  - export_route: string|null
     *  - import_route: string|null
     *  - import_format_url: string|null
     *  - import_accept: string
     *  - import_max_size_mb: int
     *  - breadcrumbs: array[]|null
     *  - row_selection: bool
     */
    abstract protected function resourceConfig(): array;

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
     * Override in sub-controllers for custom behaviour.
     *
     * @param  mixed  $model   The Eloquent model instance
     * @param  array  $data    Validated request data
     * @param  string $action  'create' or 'update'
     */
    protected function beforeSave($model, array $data, string $action): void
    {
        // Override in sub-controllers
    }

    /**
     * Hook called after saving a model.
     * Override in sub-controllers for custom behaviour.
     *
     * @param  mixed  $model   The Eloquent model instance
     * @param  array  $data    Validated request data
     * @param  string $action  'create' or 'update'
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

        $query = $this->baseQuery();
        $query = $this->crud->buildIndexQuery($query, $request, $config);
        $data  = $this->crud->paginate($query, $request, $config['per_page'] ?? 10);

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
            'meta'    => $this->crud->buildMeta($config, 'index'),
            'filters' => $request->only([
                'search', 'sort', 'direction', 'per_page',
                ...collect($config['filters'] ?? [])->map(fn ($f) => "filter_{$f['key']}")->toArray(),
                ...collect($config['filters'] ?? [])->where('type', 'date_range')->flatMap(fn ($f) => ["filter_{$f['key']}_from", "filter_{$f['key']}_to"])->toArray(),
            ]),
        ]);
    }

    public function create()
    {
        $config = $this->resourceConfig();

        $this->authorize('create', $config['model']);

        $fields = $this->crud->prepareFieldsForFrontend($config['fields'] ?? [], 'create');

        return Inertia::render($this->pagePrefix() . '/Form', [
            'meta'   => $this->crud->buildMeta($config, 'create'),
            'fields' => $fields,
            'mode'   => 'create',
        ]);
    }

    public function store(Request $request)
    {
        $config = $this->resourceConfig();

        $this->authorize('create', $config['model']);

        $fields = $config['fields'] ?? [];
        $rules  = $this->crud->buildValidationRules($fields, null, 'create');
        $data   = $request->validate($rules);

        $model = new $config['model'];
        $this->crud->fillModel($model, $data, $fields);
        $this->crud->processChunkedFiles($model, $request, $data, $fields);

        // Auto-set audit columns if they exist
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
        $model  = $this->baseQuery()->findOrFail($id);

        $this->authorize('view', $model);

        return Inertia::render($this->pagePrefix() . '/Show', [
            'meta'   => $this->crud->buildMeta($config, 'show', $model),
            'record' => $model,
        ]);
    }

    public function edit(int|string $id)
    {
        $config = $this->resourceConfig();
        $model  = $this->baseQuery()->findOrFail($id);

        $this->authorize('update', $model);

        $fields = $this->crud->prepareFieldsForFrontend($config['fields'] ?? [], 'edit', $model);

        return Inertia::render($this->pagePrefix() . '/Form', [
            'meta'   => $this->crud->buildMeta($config, 'edit', $model),
            'fields' => $fields,
            'record' => $model,
            'mode'   => 'edit',
        ]);
    }

    public function update(Request $request, int|string $id)
    {
        $config = $this->resourceConfig();
        $model  = (new $config['model'])->findOrFail($id);

        $this->authorize('update', $model);

        $fields = $config['fields'] ?? [];
        $rules  = $this->crud->buildValidationRules($fields, $model, 'update');

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
        $this->crud->processChunkedFiles($model, $request, $data, $fields);

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

        $this->crud->deleteChunkedFiles($model, $config['fields'] ?? []);
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
            $this->crud->deleteChunkedFiles($record, $fields);
            $record->delete();
        }

        $count       = $records->count();
        $routePrefix = $this->routePrefix($config);
        $label       = $config['resource_name'] ?? 'Record';

        return redirect()->route("{$routePrefix}.index")
            ->with('success', "{$count} {$label}(s) deleted successfully.");
    }

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
}
