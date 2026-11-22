# Form Fields & Validation

Define create and edit form inputs alongside their Laravel validation rules inside `resourceConfig()['fields']`.

## Field Configuration

```php
'fields' => [
    ['key' => 'name',     'label' => 'Full Name', 'type' => 'text',     'rules' => 'required|string|max:255', 'required' => true],
    ['key' => 'email',    'label' => 'Email',     'type' => 'email',    'rules' => 'required|email|unique:users,email,{id}', 'required' => true],
    ['key' => 'password', 'label' => 'Password',  'type' => 'password', 'rules' => 'required|min:8', 'optional_on_update' => true],
    [
        'key'      => 'role',
        'label'    => 'Role',
        'type'     => 'select',
        'rules'    => 'required|in:super_admin,admin,viewer',
        'required' => true,
        'options'  => [
            ['label' => 'Super Admin', 'value' => 'super_admin'],
            ['label' => 'Admin',       'value' => 'admin'],
            ['label' => 'Viewer',      'value' => 'viewer'],
        ],
    ],
    ['key' => 'publish_at', 'label' => 'Publish At', 'type' => 'datetime', 'rules' => 'nullable|date'],
    ['key' => 'is_active',  'label' => 'Active',     'type' => 'checkbox', 'default' => true, 'placeholder' => 'Show in public catalog'],
    ['key' => 'cover',      'label' => 'Cover Image','type' => 'file',     'accept' => 'image/*', 'rules' => 'nullable|image|max:2048', 'ignore_on_save' => true],
],
```

## Supported Field Types

| Type | Input Rendered | Notes |
| --- | --- | --- |
| `text` | `<Input type="text" />` | Default fallback type |
| `email` | `<Input type="email" />` | Standard email input |
| `password` | `<Input type="password" />` | Ignored on save when blank; pair with `optional_on_update => true` |
| `number` | `<Input type="number" />` | Numeric input |
| `textarea` | `<Textarea rows={4} />` | Multi-line text input |
| `select` | Searchable `Command` Combobox | Set `'multiple' => true` for multi-select tags |
| `date` | `<Input type="date" />` | Timezone-safe `YYYY-MM-DD` formatting |
| `datetime` | `<Input type="datetime-local" />` | Automatic `formatDateTimeLocal()` conversion for Laravel timestamps |
| `checkbox` / `toggle` | Card-styled `<Checkbox />` | Coerces `'0'`, `false`, `true`, `1` safely; displays `placeholder` as helper text |
| `file` | `<Input type="file" />` | Forwards `accept`; automatically uses `POST` + `_method=put` on edit |
| `chunked-file` | `<ChunkedFileInput />` | Native chunked uploader ([see Chunked File Uploads](/guide/chunked-uploads)) |
| `hidden` | Not rendered | Value still included in form state |
| `options-builder` | Dynamic list of options + correct answer selector | Ideal for quiz / poll options |
| `repeater` | Dynamic nested sub-form rows | Recursive validation rules (`key.*.subkey`) |

---

## Field Keys Reference

| Key | Type | Description |
| --- | --- | --- |
| `key` | `string` | Form field name & model attribute |
| `label` | `string` | Input label |
| `type` | `string` | One of the field types listed above |
| `rules` | `string\|array` | Laravel validation rules — supports pipe strings, arrays, `Rule` objects, and `{id}` placeholder |
| `rules_update` | `string\|array` | Completely override validation rules on `update()` |
| `required` | `bool` | Displays `*` indicator next to label |
| `optional_on_update` | `bool` | Automatically swaps `'required'` → `'sometimes'` during `update()` |
| `options` | `array` | Options for `select`: `[['label' => '...', 'value' => '...']]` |
| `multiple` | `bool` | Enables multi-select combobox on `select` fields |
| `accept` | `string` | File input `accept` filter (e.g. `image/*`, `.pdf,.zip`) |
| `default` | `mixed` | Default value in create mode |
| `disabled` | `bool` | Disables the field |
| `placeholder` | `string` | Placeholder text (or subtitle text for `checkbox`/`toggle`) |
| `hide_on_create` | `bool` | Excludes field from create form |
| `hide_on_edit` | `bool` | Excludes field from edit form |
| `hidden` | `bool` | Excludes field from both forms |
| `ignore_on_save` | `bool` | Skips auto-assigning attribute in `CrudService::fillModel()` |

---

## Validation Highlights

### Automatic Unique Exclusion (`{id}`)

Use `{id}` in `unique:` rules — `CrudService::buildValidationRules()` automatically strips it on create and replaces it with `$model->getKey()` on update:

```php
'rules' => 'required|email|unique:users,email,{id}',
```

Even without `{id}`, a shorthand `unique:products,sku` rule is automatically expanded to `unique:products,sku,{model_id},{key_name}` during updates.

### `Rule` Objects & Partial `PATCH` Requests

You can freely mix `Illuminate\Validation\Rule` objects into array rules:

```php
'rules' => ['required', 'string', Rule::in(['draft', 'published'])],
```

On `PATCH` requests (such as inline `status` column updates), `CrudController::update()` safely converts string `required*` rules to `sometimes` without breaking `Rule` objects.

### Repeater Fields

Define nested `fields` inside a `repeater`:

```php
[
    'key'    => 'items',
    'label'  => 'Line Items',
    'type'   => 'repeater',
    'rules'  => 'required|array|min:1',
    'fields' => [
        ['key' => 'product_id', 'label' => 'Product', 'type' => 'select', 'rules' => 'required|integer', 'options' => $products],
        ['key' => 'quantity',   'label' => 'Qty',     'type' => 'number', 'rules' => 'required|integer|min:1', 'default' => 1],
    ],
],
```

Repeater fields are skipped by `fillModel()` so you can persist them cleanly in `afterSave()`.

