# Columns, Search & Filters

Configure table presentation, column visibility, CSV export formatting, relationship search, SoftDeletes filtering, sorting, and lazy/async filters from `resourceConfig()`.

## Column Configuration

```php
'columns' => [
    ['key' => 'id',           'label' => 'ID',      'sortable' => true,  'type' => 'number'],
    ['key' => 'cover_path',   'label' => 'Cover',   'sortable' => false, 'type' => 'image'],
    ['key' => 'sku',          'label' => 'SKU',     'sortable' => true,  'type' => 'copyable'],
    ['key' => 'name',         'label' => 'Name',    'sortable' => true,  'type' => 'truncate', 'max_length' => 35],
    ['key' => 'price',        'label' => 'Price',   'sortable' => true,  'type' => 'currency', 'currency' => 'USD'],
    ['key' => 'tags',         'label' => 'Tags',    'sortable' => false, 'type' => 'tags'],
    ['key' => 'website',      'label' => 'URL',     'sortable' => false, 'type' => 'link', 'default_hidden' => true],
    ['key' => 'status',       'label' => 'Status',  'sortable' => true,  'type' => 'status'],
    ['key' => 'is_featured',  'label' => 'Featured','sortable' => true,  'type' => 'toggle'],
    ['key' => 'is_active',    'label' => 'Active',  'sortable' => true,  'type' => 'boolean'],
    ['key' => 'created_at',   'label' => 'Created', 'sortable' => true,  'type' => 'datetime'],
],
```

### Supported Column Types

| Type | Rendered UI | Extra Options |
| --- | --- | --- |
| `text` (default) | Direct or dot-notated attribute value | — |
| `number` | Numeric value | — |
| `serial` | Paginated row number | — |
| `boolean` | Green `Active` / red `Inactive` badge | — |
| `toggle` | Inline `<Checkbox>` calling `PATCH {baseRoute}/{id}` (falls back to badge if user lacks `edit`) | — |
| `badge` | Auto-colored `StatusBadge` | — |
| `status` | Inline `PATCH` status dropdown (when matching filter options exist + user has `edit`) | — |
| `copyable` | Monospace text + one-click copy button with checkmark feedback | — |
| `currency` | `Intl.NumberFormat` formatted currency | `currency` (`'USD'`), `locale` |
| `link` | Clickable anchor with external link icon | — |
| `tags` | Chip badges (shows first 3 + `+N` overflow badge) | — |
| `truncate` | Clamped text with full hover tooltip | `max_length` (default `40`) |
| `date` | Localized date string | — |
| `datetime` | Localized date & time string | — |
| `image` | Thumbnail preview (supports full `http(s)://`, `/`, and `/storage/` paths) | — |

### Column Visibility Toggle

`DataTable` automatically renders a **Columns** visibility menu next to "Rows per page", persisted per resource in `localStorage` (`crud:cols:{route_prefix}`):
- Set `'default_hidden' => true` on secondary columns to hide them by default on wide tables.
- Set `'hideable' => false` on critical columns that should always remain visible.

---

## Search & Filters

### Lazy Closure & Relationship Filter Options

Define `'options'` as a `Closure` so dropdown queries execute **only** on `index()` (never on `store`, `update`, `destroy`, or `export`):

```php
'filters' => [
    [
        'key'          => 'university_id',
        'label'        => 'University',
        'type'         => 'select',
        'multiple'     => true,
        'options'      => fn () => University::orderBy('name')->pluck('name', 'id')->toArray(),
    ],
    [
        'key'          => 'category_id',
        'label'        => 'Category',
        'type'         => 'select',
        'relationship' => [
            'model' => Category::class,
            'label' => 'name',
            'value' => 'id',
        ],
    ],
    ['key' => 'is_active',  'label' => 'Active',  'type' => 'boolean'],
    ['key' => 'sku',        'label' => 'SKU',     'type' => 'text'],
    ['key' => 'created_at', 'label' => 'Created', 'type' => 'date_range'],
],
```

### Filter Keys

| Key | Type | Description |
| --- | --- | --- |
| `key` | `string` | Query parameter suffix (`filter_{key}`) |
| `label` | `string` | Filter label / placeholder |
| `type` | `string` | `select`, `boolean`, `text`, `date`, or `date_range` |
| `column` | `string` | Database column (defaults to `key`) |
| `relation` | `string` | Filter via relationship (`whereHas`) — works on all filter types |
| `options` | `array\|Closure` | Static array (`[['label' => ..., 'value' => ...]]` or `['val' => 'Label']`) or lazy `Closure` |
| `relationship` | `array` | Declarative model options (`model`, `value`, `label`, `limit`) |
| `multiple` | `bool` | Searchable `DataTableFacetedFilter` multi-select |
| `separator` | `string` | Multi-select URL delimiter (defaults to `config('crud.default_separator')`) |

### SoftDeletes Trashed Filter

If your model uses `Illuminate\Database\Eloquent\SoftDeletes`, `FilterBar` automatically displays an **Active Only / With Trashed / Only Trashed** selector (`?trashed=with|only`).
