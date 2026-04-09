<?php

namespace NagibMahfuj\Crud;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Base controller for config-driven CRUD operations.
 *
 * Extend this controller and implement resourceConfig() to get full
 * index/create/store/edit/update/destroy/export functionality for free.
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
	 *  - route_prefix: string
	 *  - page_prefix: string
	 *  - per_page: int
	 *  - searchable: string[]
	 *  - actions: string[] ('create', 'edit', 'delete', 'show')
	 *  - columns: array[]
	 *  - filters: array[]
	 *  - fields: array[]
	 *  - with: string[] (eager loads)
	 *  - with_count: string[] (count relations)
	 *  - import_route: string|null
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
	 * Return the Inertia page path prefix (e.g., 'Dashboard/CampaignWeeks').
	 */
	protected function pagePrefix(): string
	{
		$config = $this->resourceConfig();
		return $config['page_prefix'] ?? 'Dashboard/' . class_basename($config['model']) . 's';
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

		$this->authorize('viewAny', new $config['model']);

		$query = $this->baseQuery();
		$query = $this->crud->buildIndexQuery($query, $request, $config);
		$data  = $this->crud->paginate($query, $request, $config['per_page'] ?? 10);

		// Apply display_format if defined in config columns
		$data->getCollection()->transform(function ($record) use ($config) {
			foreach ($config['columns'] as $column) {
				if (isset($column['display_format']) && is_callable($column['display_format'])) {
					$key = $column['key'];
					$record->{$key} = call_user_func($column['display_format'], $record);
				}
			}
			return $record;
		});

		return Inertia::render($this->pagePrefix() . '/Index', [
			'data'    => $data,
			'meta'    => $this->crud->buildMeta($config),
			'filters' => $request->only(['search', 'sort', 'direction', 'per_page',
				...collect($config['filters'] ?? [])->map(fn ($f) => "filter_{$f['key']}")->toArray(),
				...collect($config['filters'] ?? [])->where('type', 'date_range')->flatMap(fn ($f) => ["filter_{$f['key']}_from", "filter_{$f['key']}_to"])->toArray(),
			]),
		]);
	}

	public function create()
	{
		$config = $this->resourceConfig();

		$this->authorize('create', new $config['model']);

		$fields = collect($config['fields'] ?? [])
			->filter(fn ($f) => !($f['hide_on_create'] ?? false) && !($f['hidden'] ?? false))
			->values()
			->toArray();

		return Inertia::render($this->pagePrefix() . '/Form', [
			'meta'   => $this->crud->buildMeta($config),
			'fields' => $fields,
			'mode'   => 'create',
		]);
	}

	public function store(Request $request)
	{
		$config = $this->resourceConfig();

		$this->authorize('create', new $config['model']);

		$rules  = $this->crud->buildValidationRules($config['fields'] ?? []);
		$data   = $request->validate($rules);

		$model = new $config['model'];
		$this->crud->fillModel($model, $data, $config['fields'] ?? []);

		// Auto-set audit columns if they exist
		if (in_array('created_by', $model->getFillable())) {
			$model->created_by = Auth::id();
		}
		if (in_array('updated_by', $model->getFillable())) {
			$model->updated_by = Auth::id();
		}
		$this->beforeSave($model, $data, 'create');

		$model->save();

		$this->afterSave($model, $data, 'create');

		$routePrefix = $config['route_prefix'] ?? strtolower(class_basename($config['model'])) . 's';

		return redirect()->route("{$routePrefix}.index")
			->with('success', ($config['resource_name'] ?? 'Record') . ' created successfully.');
	}

	public function show(int $id)
	{
		$config = $this->resourceConfig();
		$model  = $this->baseQuery()->findOrFail($id);

		$this->authorize('view', $model);

		return Inertia::render($this->pagePrefix() . '/Show', [
			'meta'   => $this->crud->buildMeta($config),
			'record' => $model,
		]);
	}

	public function edit(int $id)
	{
		$config = $this->resourceConfig();
		$model  = $this->baseQuery()->findOrFail($id);

		$this->authorize('update', $model);

		$fields = collect($config['fields'] ?? [])
			->filter(fn ($f) => !($f['hide_on_edit'] ?? false) && !($f['hidden'] ?? false))
			->values()
			->toArray();

		return Inertia::render($this->pagePrefix() . '/Form', [
			'meta'   => $this->crud->buildMeta($config),
			'fields' => $fields,
			'record' => $model,
			'mode'   => 'edit',
		]);
	}


	public function update(Request $request, int $id)
	{
		$config = $this->resourceConfig();
		$model  = (new $config['model'])->findOrFail($id);

		$this->authorize('update', $model);

		$rules  = $this->crud->buildValidationRules($config['fields'] ?? [], $model, 'update');

		if ($request->isMethod('patch')) {
			$rules = collect($rules)->map(function ($fieldRules) {
				return array_map(fn ($r) => str_starts_with($r, 'required') ? 'sometimes' : $r, $fieldRules);
			})->toArray();
		}

		$data   = $request->validate($rules);

		$this->crud->fillModel($model, $data, $config['fields'] ?? []);

		if (in_array('updated_by', $model->getFillable())) {
			$model->updated_by = Auth::id();
		}
		$this->beforeSave($model, $data, 'update');

		$model->save();

		$this->afterSave($model, $data, 'update');

		$routePrefix = $config['route_prefix'] ?? strtolower(class_basename($config['model'])) . 's';

		return redirect()->route("{$routePrefix}.index")
			->with('success', ($config['resource_name'] ?? 'Record') . ' updated successfully.');
	}

	public function destroy(int $id)
	{
		$config = $this->resourceConfig();
		$model  = (new $config['model'])->findOrFail($id);

		$this->authorize('delete', $model);

		$model->delete();

		$routePrefix = $config['route_prefix'] ?? strtolower(class_basename($config['model'])) . 's';

		return redirect()->route("{$routePrefix}.index")
			->with('success', ($config['resource_name'] ?? 'Record') . ' deleted successfully.');
	}

	public function export(Request $request)
	{
		$config = $this->resourceConfig();

		$this->authorize('export', new $config['model']);

		$query    = $this->baseQuery();
		$query    = $this->crud->buildIndexQuery($query, $request, $config);
		$filename = ($config['route_prefix'] ?? 'export') . '-' . date('Y-m-d') . '.csv';

		return $this->crud->exportCsv($query, $config['columns'] ?? [], $filename);
	}
}
