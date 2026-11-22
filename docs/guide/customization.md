# Customization & Hooks

Every `CrudController` method and React component is built for clean extension without rewriting CRUD boilerplate.

## Backend Lifecycle Hooks

### `beforeSave()` and `afterSave()`

```php
protected function beforeSave($model, array $data, string $action): void
{
    if (!empty($data['password'])) {
        $model->password = bcrypt($data['password']);
    }
}

protected function afterSave($model, array $data, string $action): void
{
    if (isset($data['tags'])) {
        $model->tags()->sync($data['tags']);
    }
}
```

::: info Order of Execution
In `store()` and `update()`, `CrudController` executes in this order:
1. Policy authorization (`create` / `update`)
2. Validation (`buildValidationRules()`)
3. `fillModel()`
4. `processUploadedFiles()` (handles standard `file`, `image`, and `chunked-file` uploads so `beforeSave()` already sees resolved file paths)
5. Audit columns (`created_by` / `updated_by` if fillable)
6. `beforeSave($model, $data, $action)`
7. `$model->save()`
8. `afterSave($model, $data, $action)`
:::

### Scoped `baseQuery()`

Override `baseQuery()` to enforce tenant isolation or custom scopes across `index`, `show`, `edit`, and `export`:

```php
protected function baseQuery()
{
    return parent::baseQuery()
        ->where('company_id', auth()->user()->company_id);
}
```

### KPI Stats Hook (`stats()`)

In addition to defining `'stats' => fn ($query) => [...]` inside `resourceConfig()`, you can override `stats($baseQuery)` directly on your controller:

```php
protected function stats($baseQuery): array
{
    return [
        ['title' => 'Total',     'value' => (clone $baseQuery)->count(), 'icon' => 'Package'],
        ['title' => 'Published', 'value' => (clone $baseQuery)->where('status', 'published')->count(), 'icon' => 'CheckCircle'],
    ];
}
```

### UUID & ULID Primary Keys

`CrudController` type-hints `int|string $id` on `show()`, `edit()`, `update()`, `destroy()`, `restore()`, and `forceDestroy()`, and bulk endpoints (`bulkDestroy()`, `bulkRestore()`, `bulkForceDestroy()`, `handleAction()`) use `whereKey()`, so UUID and ULID models work without any controller overrides.

---

## Frontend Component Overrides

### Custom Cells, Row Actions & Header Actions

```tsx
import ResourceIndex from '@/components/crud/ResourceIndex';
import type { ResourceIndexProps } from '@/components/crud/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';

export default function Index(props: ResourceIndexProps) {
    return (
        <ResourceIndex
            {...props}
            cellOverrides={{
                sku: (item) => (
                    <Badge variant="secondary" className="font-mono">
                        {item.sku}
                    </Badge>
                ),
            }}
            rowActions={(item) => (
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={() => console.log('Preview', item.id)}
                >
                    Preview
                </Button>
            )}
            headerActions={
                <Button type="button" size="sm" variant="outline">
                    Sync Catalog
                </Button>
            }
        />
    );
}

Index.layout = (page: any) => (
    <AppLayout breadcrumbs={page.props?.meta?.breadcrumbs ?? []}>
        {page}
    </AppLayout>
);
```

### Extra Form Sections (`children`) & Embedded Modal/Drawer Props

Pass custom JSX inside `<ResourceForm>` to append extra UI below config-generated fields, or render it embedded inside a custom dialog/drawer:

```tsx
<ResourceForm
    {...props}
    embedded
    onSuccess={() => setOpen(false)}
    onCancel={() => setOpen(false)}
>
    <div className="rounded-lg border p-4 text-sm text-muted-foreground">
        Changes will notify subscribed kiosks immediately.
    </div>
</ResourceForm>
```

### Customizing `ResourceShow` (`fieldOverrides`, `headerActions`, `children`)

```tsx
<ResourceShow
    {...props}
    fieldOverrides={{
        status: (value) => <Badge variant="secondary">{String(value)}</Badge>,
    }}
    headerActions={
        <Button type="button" size="sm" variant="outline">
            Print Invoice
        </Button>
    }
>
    <div className="rounded-lg border p-4 text-sm">
        Related activity timeline or audit log...
    </div>
</ResourceShow>
```

