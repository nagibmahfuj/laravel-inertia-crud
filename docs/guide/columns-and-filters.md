# Columns, Search & Filters

Configure table presentation, CSV export formatting, relationship search, sorting, and filters from `resourceConfig()`.

## Column Configuration

```php
'columns' => [
    ['key' => 'id',            'label' => 'ID',       'sortable' => true,  'type' => 'number'],
    ['key' => 'logo_path',     'label' => 'Logo',     'sortable' => false, 'type' => 'image'],
    ['key' => 'name',          'label' => 'Name',     'sortable' => true,  'type' => 'text'],
    ['key' => 'tier',          'label' => 'Tier',     'sortable' => true,  'type' => 'badge'],
    ['key' => 'status',        'label' => 'Status',   'sortable' => true,  'type' => 'status'],
    ['key' => 'is_active',     'label' => 'Active',   'sortable' => true,  'type' => 'boolean'],
    [
        'key'            => 'category.name',
        'label'          => 'Category',
        'sortable'       => false,
        'display_format' => fn ($record) => $record->category?->name ?? 'Uncategorized',
        'export_format'  => fn ($record) => $record->category?->name ?? 'Uncategorized',
    ],
    ['key' => 'published_at',  'label' => 'Date',     'sortable' => true,  'type' => 'date'],
    ['key' => 'created_at',    'label' => 'Created',  'sortable' => true,  'type' => 'datetime'],
],
```

### Column Keys

| Key | Type | Description |
| --- | --- | --- |
| `key` | `string` | Model attribute or dot-notated relation path (e.g. `category.name`) |
| `label` | `string` | Column header label (also used as CSV header) |
| `sortable` | `bool` | Enables server-side sorting toggle on this column |
| `type` | `string` | Renderer type (see table below) |
| `className` | `string` | Optional Tailwind classes applied to `<th>` and `<td>` |
| `display_format` | `callable` | Formats cell value server-side before sending JSON to Inertia (supports dot-notated keys) |
| `export_format` | `callable` | Formats cell value exclusively for CSV export |

### Supported Column Types

| Type | Rendered UI |
| --- | --- |
| `text` (default) | Plain value via direct or dot-path lookup |
| `number` | Numeric value |
| `serial` | Paginated row index `(current_page - 1) * per_page + index + 1` |
| `boolean` | Green `Active` or red `Inactive` `StatusBadge` |
| `badge` | Color-coded `StatusBadge` (auto-styled from common status strings) |
| `status` | Inline `PATCH` status dropdown when matching filter options exist and user has `edit` permission; otherwise `StatusBadge` |
| `date` | Localized date string (`toLocaleDateString()`) |
| `datetime` | Localized date & time string (`toLocaleString()`) |
| `image` | Thumbnail preview — supports both full URLs (`http://`, `https://`, `/`) and relative `/storage/{path}` paths |

---

## Search

Add attribute names or `relation.column` paths to `searchable`:

```php
'searchable' => ['name', 'sku', 'category.name'],
```

`FilterBar` debounces search queries (300ms) and appends `?search=...` to the URL while resetting to page 1.

---

## Filters

```php
'filters' => [
    [
        'key'      => 'status',
        'label'    => 'Status',
        'type'     => 'select',
        'multiple' => true,
        'options'  => [
            ['label' => 'Active',   'value' => 'active'],
            ['label' => 'Draft',    'value' => 'draft'],
            ['label' => 'Archived', 'value' => 'archived'],
        ],
    ],
    [
        'key'      => 'category_name',
        'label'    => 'Category',
        'type'     => 'text',
        'relation' => 'category',
        'column'   => 'name',
    ],
    ['key' => 'is_active',  'label' => 'Active',     'type' => 'boolean'],
    ['key' => 'launch_at',  'label' => 'Launch Day', 'type' => 'date'],
    ['key' => 'created_at', 'label' => 'Created',    'type' => 'date_range'],
],
```

### Filter Keys

| Key | Type | Description |
| --- | --- | --- |
| `key` | `string` | Query parameter suffix (`filter_{key}`) |
| `label` | `string` | Filter trigger label / placeholder |
| `type` | `string` | `select`, `boolean`, `text`, `date`, or `date_range` |
| `column` | `string` | Database column name (defaults to `key`) |
| `relation` | `string` | Filter via an Eloquent relationship (`whereHas`) — supported on all filter types |
| `options` | `array` | `[['label' => '...', 'value' => '...']]` for `select` filters |
| `multiple` | `bool` | Renders searchable `DataTableFacetedFilter` multi-select popover |
| `separator` | `string` | Custom delimiter for multi-select query values (defaults to `config('crud.default_separator')`) |
| `placeholder` | `string` | Optional placeholder for `text` filters |

---

## Sorting & Pagination Safety

- Sort directions are strictly whitelisted to `asc` or `desc`.
- User-supplied `?sort=` values are validated against columns marked `'sortable' => true`.
- `default_sort` in `resourceConfig()` works even when that column isn't displayed in `columns`.
- Models with `$timestamps = false` automatically fall back to sorting by primary key instead of `created_at`.
- `?per_page=` is cast to integer and clamped between `1` and `config('crud.max_per_page', 100)`.

