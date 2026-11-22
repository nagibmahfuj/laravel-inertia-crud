# Form Fields, Layouts & Validation

Define rich create/edit forms — including 2-column layouts, grouped card sections, conditional visibility, helper text, auto-handled images/files, and async searchable selects — directly in `resourceConfig()['fields']`.

## All 22 Supported Field Types

| Type | Rendered Input | Highlights |
| --- | --- | --- |
| `text`, `email`, `password`, `number` | Standard `<Input />` | `password` ignored when empty on update |
| `textarea` | `<Textarea rows={4} />` | Plain multi-line input |
| `rich-text` | Zero-dependency Markdown toolbar + Live Preview | Bold, Italic, Heading, Lists, Link, Code + safe preview toggle |
| `select` | Searchable Combobox (`Command`) | Supports `multiple`, lazy `Closure` options, and signed async `relationship` search |
| `radio` | Responsive 2-col radio cards | Uses `options` |
| `checkbox` / `toggle` | Card-styled `<Checkbox />` | Safe boolean coercion + `placeholder` subtitle |
| `date` / `datetime` | Native date / datetime-local inputs | Timezone-safe formatting & `formatDateTimeLocal()` |
| `file` | Auto-handled file input with existing file card | Supports `disk`, `directory`, `path_column`, `delete_old`, Download, Replace, Remove |
| `image` | Auto-handled image uploader with live thumbnail preview | Defaults to `accept="image/*"`, live `URL.createObjectURL` preview, Replace, Remove |
| `chunked-file` | Drag-and-drop chunked uploader (`/crud/chunk-upload`) | [See Chunked File Uploads](/guide/chunked-uploads) |
| `slug` | Monospace slug input with auto-sync & Regenerate button | Set `'slug_from' => 'title'` to auto-slugify while typing |
| `tags` | Interactive chip/tag input | Press Enter or comma to add; Backspace / `×` to remove |
| `color` | Synchronized color swatch + hex input | Stores `#rrggbb` |
| `key-value` | Dynamic key/value row editor | Stores associative JSON object |
| `options-builder` | Dynamic choice list + correct-index toggle | Ideal for quizzes & polls |
| `repeater` | Nested sub-form rows | Recursive validation rules (`key.*.subkey`) |
| `hidden` | Not rendered | Preserved in form payload |

---

## Sections, 2-Column Grid, Help Text & Conditional Visibility

```php
'form_columns'  => 2,
'form_sections' => [
    'Basic'   => ['title' => 'Basic Information', 'description' => 'Core identity and classification.', 'columns' => 2],
    'Media'   => ['title' => 'Media & Files', 'columns' => 1],
    'Content' => ['title' => 'Article Content', 'columns' => 1],
],
'fields' => [
    [
        'key'      => 'title',
        'label'    => 'Title',
        'type'     => 'text',
        'rules'    => 'required|string|max:255',
        'required' => true,
        'section'  => 'Basic',
        'help'     => 'Displayed on the public landing page.',
    ],
    [
        'key'       => 'slug',
        'label'     => 'URL Slug',
        'type'      => 'slug',
        'slug_from' => 'title',
        'rules'     => 'required|string|unique:articles,slug,{id}',
        'section'   => 'Basic',
    ],
    [
        'key'          => 'external_url',
        'label'        => 'External Link',
        'type'         => 'text',
        'rules'        => 'nullable|url',
        'section'      => 'Basic',
        'col_span'     => 2,
        'visible_when' => ['field' => 'source_type', 'equals' => 'external'],
    ],
    [
        'key'      => 'body',
        'label'    => 'Article Body',
        'type'     => 'rich-text',
        'rules'    => 'nullable|string',
        'section'  => 'Content',
        'col_span' => 2,
    ],
],
```

### Supported `visible_when` Operators

`equals`, `not_equals`, `in`, `not_in`, `truthy`, `falsy`, `empty`, `not_empty`.

---

## Auto-Handled `file` & `image` Fields

Standard `file` and `image` fields support the same zero-boilerplate storage options as `chunked-file`:

```php
[
    'key'               => 'logo',
    'label'             => 'University Logo',
    'type'              => 'image',
    'rules'             => 'nullable|image|max:3072',
    'disk'              => 'public',
    'directory'         => 'universities',
    'path_column'       => 'logo_path',
    'delete_old'        => true,
]
```

- On create/update, `FileUploadService` stores the file, deletes replaced files, and populates `path_column` (plus optional `name_column` & `size_column`).
- Supports the same storage options as `chunked-file`: `disk`, `directory` (`string|Closure`), `filename` (`Closure`), `preserve_filename`, `visibility`, `path_column`, `name_column`, `size_column`, `url_column`, `delete_old`, and `auto_handle`.
- On edit, `FormField` shows the existing image thumbnail (or file download card) with **Replace** and **Remove** (`{ removed: true }`).
- Legacy controllers using `'ignore_on_save' => true` (or `'auto_handle' => false`) keep full manual control in `beforeSave()`.

---

## Repeater & Options-Builder Fields

::: code-group

```php [Repeater (Nested Sub-Forms)]
[
    'key'                => 'variants',
    'label'              => 'Product Variants',
    'type'               => 'repeater',
    'rules'              => 'nullable|array',
    'hide_add_button'    => false,
    'hide_remove_button' => false,
    'fields'             => [
        ['key' => 'sku',   'label' => 'SKU',   'type' => 'text',   'rules' => 'required|string'],
        ['key' => 'price', 'label' => 'Price', 'type' => 'number', 'rules' => 'required|numeric|min:0'],
    ],
]
```

```php [Options-Builder (Quizzes & Polls)]
[
    'key'   => 'choices',
    'label' => 'Answer Options',
    'type'  => 'options-builder',
    'rules' => 'required|array|min:2',
]
```

:::

Repeater fields are validated recursively (`variants.*.sku`, `variants.*.price`) and skipped in `fillModel()` so you can sync them cleanly inside `afterSave()`.

---

## Field Options Reference

| Key | Type | Description |
| --- | --- | --- |
| `key` / `label` / `type` | `string` | Attribute name, display label, and field renderer type |
| `rules` / `rules_update` | `string\|array` | Laravel validation rules (supports `Rule` objects and `{id}` unique exclusion) |
| `required` / `optional_on_update` | `bool` | Marks field required in UI; `optional_on_update` converts `required` → `sometimes` on edit |
| `placeholder` / `help` | `string` | Input placeholder and helper text shown below the input |
| `section` / `col_span` | `string` / `1\|2` | Groups field into a `form_sections` card and spans 1 or 2 grid columns |
| `visible_when` | `array` | Conditional display rule (`field` + operator: `equals`, `not_equals`, `in`, `not_in`, `truthy`, `falsy`, `empty`, `not_empty`) |
| `options` | `array\|Closure` | Static or lazy Closure options for `select` and `radio` (`[['label' => ..., 'value' => ...]]` or `['val' => 'Label']`) |
| `relationship` | `array` | Declarative Eloquent options (`model`, `value`, `label`, `search_columns`, `order_by`, `order_dir`, `limit`, `query` Closure, `async`, `endpoint`) |
| `multiple` | `bool` | Multi-select combobox for `select` fields |
| `slug_from` | `string` | Source field key auto-slugified into a `slug` input |
| `default` / `disabled` | `mixed` / `bool` | Initial create value and disabled input flag |
| `hide_on_create` / `hide_on_edit` / `hidden` | `bool` | Visibility control by form mode |
| `ignore_on_save` | `bool` | Skip assigning this key in `CrudService::fillModel()` |
| `accept`, `disk`, `directory`, `filename`, `preserve_filename`, `visibility`, `delete_old`, `path_column`, `name_column`, `size_column`, `url_column`, `auto_handle` | various | Storage and column-mapping options for `file`, `image`, and `chunked-file` fields |
| `fields` / `hide_add_button` / `hide_remove_button` | `array` / `bool` | Nested field schema and controls for `repeater` fields |

---

## Lazy Options & Async Relationship Selects

Avoid running heavy queries on every controller request by using lazy closures or declarative async relationships:

::: code-group

```php [Lazy Closure Options]
[
    'key'     => 'category_id',
    'label'   => 'Category',
    'type'    => 'select',
    'rules'   => 'required|exists:categories,id',
    'options' => fn () => \App\Models\Category::orderBy('name')->pluck('name', 'id')->toArray(),
]
```

```php [Async Searchable Relationship (/crud/options)]
[
    'key'          => 'university_id',
    'label'        => 'University',
    'type'         => 'select',
    'rules'        => 'required|exists:universities,id',
    'relationship' => [
        'model'          => \App\Models\University::class,
        'value'          => 'id',
        'label'          => 'name',
        'search_columns' => ['name', 'short_code'],
        'limit'          => 25,
        'async'          => true, // Uses signed native GET /crud/options endpoint
    ],
]
```

:::

When `'async' => true`, `OptionsService` encrypts the approved model/columns into a user-scoped `options_config` token so the native `GET /crud/options` endpoint safely searches large tables with debounced queries and pre-hydrates selected labels on edit.

For non-async relationships (`'async' => false`, the default), you can also pass `'query' => fn ($q) => $q->where('is_active', true)` along with `'order_by'`, `'order_dir'`, and `'limit'` to scope the eager option list.
