<?php

namespace NagibMahfuj\Crud;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Stateless CRUD service.
 *
 * Handles query building (search, filters, sorting), pagination,
 * validation rule generation, model filling, CSV exporting, and
 * building the frontend meta array.
 */
class CrudService
{
	/**
	 * Build a query from the resource config and request parameters.
	 * Handles search, filters, sorting, and pagination.
	 */
	public function buildIndexQuery(Builder $query, Request $request, array $config): Builder
	{
		$separator = config('crud.default_separator', '--');

		// ── Search ────────────────────────────────────────────
		if ($request->filled('search') && !empty($config['searchable'] ?? [])) {
			$search = $request->input('search');
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
				$query->where($column, $value === 'true' || $value === '1');
			} elseif ($type === 'select' && $request->filled("filter_{$key}")) {
				$value         = $request->input("filter_{$key}");
				$filterSeparator = $filter['separator'] ?? $separator;

				// Handle multi-select (array or dynamic separator)
				if (is_string($value) && str_contains($value, $filterSeparator)) {
					$value = explode($filterSeparator, $value);
				}

				if ($relation) {
					$query->whereHas($relation, function (Builder $q) use ($column, $value) {
						if (is_array($value)) {
							$q->whereIn($column, $value);
						} else {
							$q->where($column, $value);
						}
					});
				} else {
					if (is_array($value)) {
						$query->whereIn($column, $value);
					} else {
						$query->where($column, $value);
					}
				}
			} elseif ($type === 'date_range') {
				if ($request->filled("filter_{$key}_from")) {
					$query->whereDate($column, '>=', $request->input("filter_{$key}_from"));
				}
				if ($request->filled("filter_{$key}_to")) {
					$query->whereDate($column, '<=', $request->input("filter_{$key}_to"));
				}
			} elseif ($type === 'date' && $request->filled("filter_{$key}")) {
				$query->whereDate($column, $request->input("filter_{$key}"));
			}
		}

		// ── Sorting ───────────────────────────────────────────
		$sortKey       = $request->input('sort', $config['default_sort'] ?? 'created_at');
		$sortDirection = $request->input('direction', $config['default_direction'] ?? 'desc');

		// Validate that the sort column is actually sortable
		$sortableColumns = collect($config['columns'] ?? [])
			->filter(fn ($col) => $col['sortable'] ?? false)
			->pluck('key')
			->toArray();

		if (in_array($sortKey, $sortableColumns)) {
			$query->orderBy($sortKey, $sortDirection);
		} else {
			$query->latest();
		}

		return $query;
	}

	/**
	 * Get paginated results with query string appended.
	 */
	public function paginate(Builder $query, Request $request, int $defaultPerPage = 10)
	{
		$perPage = $request->input('per_page', $defaultPerPage);

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
			$fieldRules = $field['rules'] ?? '';

			if ($action === 'update' && isset($field['rules_update'])) {
				$fieldRules = $field['rules_update'];
			}

			if (is_string($fieldRules)) {
				$fieldRules = explode('|', $fieldRules);
			}

			// On update, replace 'required' with 'sometimes' if the field is optional
			if ($action === 'update' && ($field['optional_on_update'] ?? false)) {
				$fieldRules = array_map(fn ($r) => is_string($r) && $r === 'required' ? 'sometimes' : $r, $fieldRules);
			}

			// Handle unique rules with model exclusion on update
			if ($model) {
				$fieldRules = array_map(function ($rule) use ($model, $field) {
					if (is_string($rule) && str_contains($rule, '{id}')) {
						return str_replace('{id}', $model->getKey(), $rule);
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
			if (($field['type'] ?? '') === 'repeater' && !empty($field['fields'])) {
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
			$key = $field['key'];

			if (!array_key_exists($key, $data) || ($field['type'] ?? '') === 'repeater' || ($field['ignore_on_save'] ?? false)) {
				continue;
			}

			if (($field['type'] ?? '') === 'password' && empty($data[$key])) {
				continue;
			}

			$model->{$key} = $data[$key];
		}

		return $model;
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
	 * Build the metadata array to pass to the frontend for rendering.
	 */
	public function buildMeta(array $config): array
	{
		// Strip any closures from columns before sending to frontend
		$columns = array_map(function ($col) {
			unset($col['export_format'], $col['display_format']);

			return $col;
		}, $config['columns'] ?? []);

		$actions = $config['actions'] ?? [];

		// Filter actions based on permissions
		if (isset($config['model'])) {
			$user       = Auth::user();
			$modelClass = $config['model'];

			if ($user) {
				$actions = array_filter($actions, function ($action) use ($user, $modelClass) {
					$ability = match ($action) {
						'create' => 'create',
						'edit'   => 'update',
						'delete' => 'delete',
						'show'   => 'view',
						default  => $action,
					};

					// Attempt to authorize on a new model instance
					return $user->can($ability, new $modelClass);
				});
				// Reindex array
				$actions = array_values($actions);
			}
		}

		return [
			'columns'       => $columns,
			'filters'       => $config['filters'] ?? [],
			'actions'       => $actions,
			'row_selection' => $config['row_selection'] ?? false,
			'per_page'      => $config['per_page'] ?? 10,
			'resource_name' => $config['resource_name'] ?? '',
			'import_route'  => $config['import_route'] ?? null,
		];
	}
}
