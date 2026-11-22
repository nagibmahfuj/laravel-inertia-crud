# `resourceConfig()` Reference (v2)

## Top-Level Keys

| Key | Type | Notes |
| --- | --- | --- |
| `model` | `class-string<Model>` | Required |
| `resource_name` / `resource_plural` | `string` | Auto-derived from model class if omitted |
| `title` / `create_title` / `edit_title` / `show_title` | `string\|null` | Context-aware page titles |
| `description` | `string\|null` | Rendered in `PageHeader` & `ResourceShow` |
| `route_prefix` / `base_route` / `url_prefix` | `string` | Auto-resolves via `route("{$route_prefix}.index", [], false)` |
| `page_prefix` | `string` | Inertia folder prefix (e.g. `Dashboard/Products`) |
| `form_mode` | `'page'\|'modal'\|'drawer'` | Default `'page'` |
| `form_columns` | `1\|2` | Responsive grid column count |
| `form_sections` | `array<string, array{title?: string, description?: string, columns?: 1\|2}>` | Card section metadata |
| `soft_deletes` | `bool` | Auto-detected from `SoftDeletes` trait |
| `per_page` | `int` | Clamped by `config('crud.max_per_page', 100)` |
| `searchable` | `string[]` | Attributes or `relation.column` paths |
| `default_sort` / `default_direction` | `string` | Direction whitelisted to `asc`/`desc` |
| `with` / `with_count` | `string[]` | Eloquent eager loads / counts |
| `stats` | `array\|Closure` | `fn ($baseQuery) => [['title', 'value', 'change?', 'description?', 'icon?']]` |
| `actions` | `string[]` | `'create'`, `'edit'`, `'delete'`, `'show'`, `'export'`, `'import'` |
| `custom_actions` | `array[]` | Declarative row & bulk handlers (`POST {uri}/action/{key}`) |
| `row_selection` | `bool` | Enables checkboxes & `BulkActions` |
| `breadcrumbs` | `array\|null` | Base trail; Create/Edit/Details auto-appended |
| `columns` / `show_fields` / `filters` / `fields` | `array[]` | UI schemas |
| `import` | `array` | Built-in CSV import config |

## Column Types (15)

`text`, `number`, `serial`, `boolean`, `toggle` (inline PATCH), `badge`, `status` (inline PATCH dropdown), `copyable`, `currency` (`currency`, `locale`), `link`, `tags`, `truncate` (`max_length`), `date`, `datetime`, `image`.

Common column keys: `key`, `label`, `sortable`, `type`, `className`, `default_hidden`, `hideable`, `display_format` (`Closure`), `export_format` (`Closure`).

## Field Types (22)

`text`, `email`, `password`, `number`, `textarea`, `rich-text` (Markdown + toolbar + preview), `select` (`multiple`, lazy `options` Closure, `relationship`), `radio`, `date`, `datetime`, `toggle`, `checkbox`, `file`, `image`, `chunked-file`, `slug` (`slug_from`), `tags`, `color`, `key-value`, `options-builder`, `repeater` (`fields`), `hidden`.

Layout & UX field keys: `help`, `section`, `col_span` (`1`|`2`), `visible_when` (`['field' => '...', 'equals'|'not_equals'|'in'|'not_in'|'truthy'|'falsy'|'empty'|'not_empty' => ...]`), `rules`, `rules_update`, `required`, `optional_on_update`, `default`, `disabled`, `hide_on_create`, `hide_on_edit`, `hidden`, `ignore_on_save`.

## Filter Types (5)

`select` (`multiple`, `separator`, lazy `options`, `relationship`), `boolean`, `text`, `date`, `date_range`. All support `column` and `relation` (`whereHas`).

## Declarative `custom_actions`

```php
'custom_actions' => [
    [
        'key'        => 'approve',
        'label'      => 'Approve',
        'scope'      => 'row', // or 'bulk'
        'icon'       => 'CheckCircle',
        'variant'    => 'ghost',
        'permission' => 'update',
        'confirm'    => ['title' => 'Approve record?', 'description' => 'This marks it approved.'],
        'handler'    => fn ($recordOrCollection, \Illuminate\Http\Request $request) => ...,
    ],
],
```

## Permissions (`config/crud.php`)

```php
'resources' => [
    'products' => [
        'roles'       => ['super_admin', 'admin', 'manager', 'viewer'],
        'read_only'   => ['viewer'],   // alias: read_only_roles
        'create_only' => ['manager'],  // alias: create_only_roles
        'no_delete'   => ['admin'],    // bool true or role[]; alias: no_delete_roles
    ],
],
```
