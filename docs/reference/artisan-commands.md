# Artisan Commands & Publish Tags

## Commands

### `php artisan crud:generate`

Generates config-driven CRUD scaffolding (controller, policy, normalized Inertia TSX pages, and `config/crud.php` permissions entry).

```bash
# Interactive 7-step wizard
php artisan crud:generate

# Non-interactive full scaffold
php artisan crud:generate --model=Product --all --actions=create,edit,delete,export
```

See the [Interactive Generator guide](/guide/generator) for all CLI options.

---

### `php artisan crud:cleanup-chunks`

Deletes expired partial chunk upload directories and unclaimed completed uploads older than `config('crud.chunk_upload.ttl_hours', 6)`.

```bash
# Use configured TTL (default 6 hours)
php artisan crud:cleanup-chunks

# Override TTL to 2 hours
php artisan crud:cleanup-chunks --hours=2
```

---

## Publish Tags

```bash
php artisan vendor:publish --tag=<tag>
```

| Tag | Published Destination(s) |
| --- | --- |
| `crud-config` | `config/crud.php` |
| `crud-components` | `resources/js/components/crud/` |
| `crud-lib` | `resources/js/lib/crud-theme.ts` and `resources/js/hooks/use-nav-items.ts` |
| `crud-assets` | All frontend assets (`crud-components` + `crud-lib`) |
| `crud-stubs` | `stubs/crud/` |

