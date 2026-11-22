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
4. `processChunkedFiles()` (so `beforeSave()` already sees resolved file paths)
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

### UUID & ULID Primary Keys

`CrudController` type-hints `int|string $id` on `show()`, `edit()`, `update()`, and `destroy()`, and `bulkDestroy()` uses `whereKey()`, so UUID and ULID models work without any controller overrides.

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

### Extra Form Sections (`children`)

Pass custom JSX inside `<ResourceForm>` to append extra UI below config-generated fields:

```tsx
<ResourceForm {...props}>
    <div className="rounded-lg border p-4 text-sm text-muted-foreground">
        Changes will notify subscribed kiosks immediately.
    </div>
</ResourceForm>
```

