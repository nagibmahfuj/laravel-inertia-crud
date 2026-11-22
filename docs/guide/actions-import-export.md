# Actions, Export & Import

In v2.0, the `actions` array inside `resourceConfig()` is the single source of truth for **both header actions and row actions**, filtered automatically through your resource policy.

```php
'actions' => ['create', 'edit', 'delete', 'show', 'export', 'import'],
```

## Available Actions

| Action | Placement | Policy Ability | Behavior |
| --- | --- | --- | --- |
| `create` | PageHeader (`Add {Resource}`) | `create` | Navigates to `{base_route}/create` |
| `export` | PageHeader (`Export`) | `export` | Streams filtered CSV from `GET {base_route}/export` |
| `import` | PageHeader (`Import`) | `import` | Opens `FileImport` dialog posting to `POST {base_route}/import` |
| `show` | Row action (`Eye`) | `view` | Navigates to `{base_route}/{id}` |
| `edit` | Row action (`Pencil`) | `update` | Navigates to `{base_route}/{id}/edit` (also enables inline `status` dropdowns) |
| `delete` | Row action (`Trash2`) & BulkActions | `delete` | Confirms and calls `DELETE {base_route}/{id}` or `POST {base_route}/bulk-destroy` |

::: info Smart Actions Column Visibility
The table's **Actions** column is only rendered when at least one row-level action (`show`, `edit`, `delete`) is permitted or a custom `rowActions` prop is passed — preventing empty Actions columns when a resource only enables header actions like `create`, `export`, or `import`.
:::

---

## CSV Export

1. Add `'export'` to `actions`:
   ```php
   'actions' => ['create', 'edit', 'delete', 'export'],
   ```
2. `CrudController::export()` streams a UTF-8 BOM CSV with all current search, filter, and sort query parameters applied, using each column's `export_format` closure if defined.
3. If `'export'` is omitted from `actions`, `ResourceIndex` hides the Export button **and** `GET /{resource}/export` returns `403 Forbidden`.

---

## File Import

1. Implement an `import(Request $request)` method on your controller:
   ```php
   public function import(Request $request)
   {
       $this->authorize('import', \App\Models\Product::class);

       $request->validate([
           'file' => ['required', 'file', 'mimes:csv,xlsx', 'max:51200'],
       ]);

       // Process the uploaded file...

       return back()->with('success', 'Import completed successfully.');
   }
   ```
2. Add `'import'` to `actions` in `resourceConfig()` and optionally configure modal options:
   ```php
   'actions'            => ['create', 'edit', 'delete', 'export', 'import'],
   'import_format_url'  => '/samples/products-template.csv',
   'import_accept'      => '.csv,.xlsx',
   'import_max_size_mb' => 50,
   ```

`Route::crudResource()` automatically registers `POST {uri}/import`, and `CrudService::buildMeta()` only exposes `meta.routes.import` when the current user passes `Policy::import()`.

---

## Bulk Destroy (`row_selection`)

Enable multi-row checkboxes and the `BulkActions` bar in `resourceConfig()`:

```php
'row_selection' => true,
'actions'       => ['create', 'edit', 'delete', 'export'],
```

- `Route::crudResource()` automatically registers `POST {uri}/bulk-destroy`.
- `CrudController::bulkDestroy()` validates `ids`, authorizes `'delete'` on every matched model individually, cleans up any non-soft-delete `chunked-file` assets, deletes the records, and redirects back with a success flash message.
- Users without `'delete'` permission never see the Bulk Delete button and receive `403 Forbidden` if calling the endpoint directly.

