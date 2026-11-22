# Interactive Generator

Scaffold complete, v2-normalized CRUD resources using `php artisan crud:generate`.

## Interactive Wizard

Run without arguments to launch the 7-step interactive wizard:

```bash
php artisan crud:generate
```

The command renders a package and environment overview banner (version, PHP/Laravel versions, active `RoleResolver`, detected pages directory, chunk upload endpoint status, and config status) and walks you through:

1. **Select a model** — Recursively scans `config('crud.generator.model_path', 'app/Models')`, including nested subdirectories such as `Billing/Invoice`.
2. **Route prefix** — Suggests kebab-case plural (e.g., `products`).
3. **Inertia page prefix** — Suggests `Dashboard/Products` and automatically detects `resources/js/pages` vs `resources/js/Pages`.
4. **Database columns** — Inspects your schema via `Schema::getColumns()`, maps DB column types to CRUD types, and lets you pick visible index columns.
5. **Enabled actions** — Select from `create`, `edit`, `delete`, `show`, `export`, and `import`.
6. **Artifacts** — Choose any combination of Controller, Policy, Inertia TSX pages (`Index.tsx`, `Form.tsx`, plus `Show.tsx` automatically when `'show'` is selected), and `config/crud.php` permission entry.
7. **Summary & generation** — Confirms target paths in a formatted table and reports `CREATED`, `OVERWRITTEN`, `UPDATED`, or `SKIPPED` per artifact.

---

## Non-Interactive Mode

Pass `--model` (or `-M`) for scriptable, non-interactive scaffolding:

::: code-group

```bash [Generate Everything]
php artisan crud:generate --model=Product --all
```

```bash [Custom Actions & Paths]
php artisan crud:generate \
  --model=Billing/Invoice \
  --route-prefix=invoices \
  --page-prefix=Admin/Billing/Invoices \
  --actions=create,edit,delete,show,export,import \
  --all
```

```bash [Controller + Policy Only (Overwrite)]
php artisan crud:generate --model=Product --policy --force
```

:::

## CLI Options

| Option | Description |
| --- | --- |
| `--model`, `-M` | Model name relative to `app/Models` (e.g. `Product` or `Billing/Invoice`) |
| `--route-prefix` | Route prefix & permission key (defaults to kebab-plural model name) |
| `--page-prefix` | Inertia page directory (e.g. `Dashboard/Products`) |
| `--controller-namespace` | Target controller namespace override |
| `--actions` | Comma-separated actions (`create,edit,delete,show,export,import`) |
| `--policy` | Generate a `BaseCrudPolicy` subclass |
| `--pages` | Generate normalized `Index.tsx`, `Form.tsx`, and `Show.tsx` (when `'show'` is in actions) |
| `--permissions` | Add resource entry to `config/crud.php` |
| `--all` | Generate controller, policy, pages, and config entry |
| `--force` | Overwrite existing files |

---

## Customizing Stubs

Publish the package stubs to `stubs/crud/`:

```bash
php artisan vendor:publish --tag=crud-stubs
```

The generator automatically checks `stubs/crud/` before falling back to package defaults:
- `stubs/crud/CrudController.stub`
- `stubs/crud/CrudPolicy.stub`
- `stubs/crud/IndexPage.stub`
- `stubs/crud/FormPage.stub`
- `stubs/crud/ShowPage.stub`

::: tip Verify After Scaffolding
Run `php artisan crud:doctor` after registering your route to verify policy registration, `config/crud.php` resource keys, routes, storage disks, and queue settings.
:::

